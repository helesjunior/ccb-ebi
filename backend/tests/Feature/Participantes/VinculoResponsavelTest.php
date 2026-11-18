<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Participantes\Contracts\GerirVinculoResponsavel;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Participantes\Models\SolicitacaoVinculo;
use App\Support\ProblemException;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();

    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->authResp = ['Authorization' => 'Bearer '.$this->responsavel->createToken('t')->plainTextToken];

    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->authCoord = [
        'Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
});

it('responsavel solicita vinculo e fica pendente', function () {
    $participante = Participante::factory()->create();

    $resposta = $this->postJson("/api/v1/participants/{$participante->id}/guardians", [
        'nome' => 'Maria Souza', 'tipo_relacao' => 'mae', 'telefone' => '61999999999',
    ], $this->authResp + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('validacao', 'pendente')
        ->json();

    expect(SolicitacaoVinculo::query()
        ->where('participante_id', $participante->id)
        ->where('tipo', SolicitacaoVinculo::TIPO_SOLICITACAO)
        ->exists())->toBeTrue();
    expect($resposta['usuario_id'])->toBe($this->responsavel->id);
});

it('coordenadora aprova e a validacao vira aprovada', function () {
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id, 'validacao' => 'pendente',
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], $this->authCoord)
        ->assertOk()->assertJsonPath('validacao', 'aprovada');

    expect($vinculo->fresh()->validacao)->toBe('aprovada');
});

it('duvida muda para aguardando_documento registrando quem conferiu', function () {
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id, 'validacao' => 'pendente',
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'pedir_documento'], $this->authCoord)
        ->assertOk()->assertJsonPath('validacao', 'aguardando_documento');

    $atual = $vinculo->fresh();
    expect($atual->validacao)->toBe('aguardando_documento');
    expect($atual->conferido_em)->not->toBeNull();
    expect($atual->conferido_por)->toBe($this->coordenadora->id);
});

it('revoga encerrando o vinculo mas preservando o log', function () {
    $vinculo = ResponsavelParticipante::factory()->aprovado()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", [
        'decisao' => 'revogar', 'motivo' => 'Pedido do responsÃ¡vel',
    ], $this->authCoord)->assertOk();

    expect($vinculo->fresh()->fim)->not->toBeNull();
    expect(SolicitacaoVinculo::query()->where('tipo', SolicitacaoVinculo::TIPO_REVOGACAO)->exists())->toBeTrue();
});

it('coordenadora nao decide vinculo de participante fora do seu territorio', function () {
    ['igreja' => $outraIgreja] = igrejaComEbi();
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($outraIgreja)->id, 'validacao' => 'pendente',
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], $this->authCoord)
        ->assertStatus(404);

    expect($vinculo->fresh()->validacao)->toBe('pendente');
});

it('devolve 404 para vinculo inexistente', function () {
    $this->patchJson('/api/v1/guardian-requests/'.Str::ulid(), ['decisao' => 'aprovar'], $this->authCoord)
        ->assertStatus(404);
});

it('o dominio recusa o autor decidindo o proprio pedido', function () {
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);
    $antes = $vinculo->fresh()->toArray();
    $logsAntes = SolicitacaoVinculo::query()->count();
    $auditoriaAntes = EventoAuditoria::query()->count();

    $servico = app(GerirVinculoResponsavel::class);

    expect(fn () => $servico->decidir((string) $this->coordenadora->id, $vinculo->id, 'aprovar'))
        ->toThrow(function (ProblemException $e) {
            expect($e->status)->toBe(403)
                ->and($e->problemCode)->toBe('acesso_negado');
        });

    expect($vinculo->fresh()->toArray())->toBe($antes)
        ->and(SolicitacaoVinculo::query()->count())->toBe($logsAntes)
        ->and(EventoAuditoria::query()->count())->toBe($auditoriaAntes);
});

it('autor recebe 403 com acesso_negado em qualquer decisao', function (string $decisao) {
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => $decisao], $this->authCoord)
        ->assertStatus(403)
        ->assertJsonPath('code', 'acesso_negado');

    $atual = $vinculo->fresh();
    expect($atual->validacao)->toBe(ResponsavelParticipante::VALIDACAO_PENDENTE)
        ->and($atual->conferido_por)->toBeNull()
        ->and($atual->conferido_em)->toBeNull()
        ->and($atual->fim)->toBeNull();
})->with(['aprovar', 'recusar', 'pedir_documento', 'revogar', 'contestar']);

it('autor nao revoga o proprio vinculo ja aprovado', function () {
    $vinculo = ResponsavelParticipante::factory()->aprovado()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => $this->coordenadora->id,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'revogar'], $this->authCoord)
        ->assertStatus(403);

    expect($vinculo->fresh()->fim)->toBeNull();
});

it('outro operador do territorio aprova o pedido do autor', function () {
    $outraCoordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], [
        'Authorization' => 'Bearer '.$outraCoordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ])->assertOk()->assertJsonPath('validacao', 'aprovada');

    expect($vinculo->fresh()->conferido_por)->toBe($outraCoordenadora->id);
});

it('responsavel do participante nao decide vinculo so por propriedade', function () {
    ['igreja' => $igrejaB] = igrejaComEbi();
    $crianca = participanteNaIgreja($igrejaB);

    // O mesmo usuario e responsavel (aprovado) pela crianca da EBI B e
    // coordenador da EBI A. Ele tem a operacao no escopo A, mas nao cobre B.
    $usuario = criarUsuarioComPapel('responsavel', 'central', 'c1');
    ResponsavelParticipante::factory()->aprovado()->create([
        'participante_id' => $crianca->id,
        'usuario_id' => $usuario->id,
    ]);
    VinculoAcesso::factory()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', 'coordenadora')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $this->ebi->id,
    ]);

    $pedido = ResponsavelParticipante::factory()->create([
        'participante_id' => $crianca->id,
        'usuario_id' => null,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$pedido->id}", ['decisao' => 'aprovar'], [
        'Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ])->assertStatus(404);

    expect($pedido->fresh()->validacao)->toBe(ResponsavelParticipante::VALIDACAO_PENDENTE);
});

it('vinculo sem dono continua aprovavel por operador do territorio', function () {
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => null,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], $this->authCoord)
        ->assertOk()->assertJsonPath('validacao', 'aprovada');
});
