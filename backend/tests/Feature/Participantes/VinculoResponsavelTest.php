<?php

use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Participantes\Models\SolicitacaoVinculo;

beforeEach(function () {
    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->authResp = ['Authorization' => 'Bearer '.$this->responsavel->createToken('t')->plainTextToken];

    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', (string) Str::ulid());
    $this->authCoord = ['Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken];
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
    $vinculo = ResponsavelParticipante::factory()->create(['validacao' => 'pendente']);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], $this->authCoord)
        ->assertOk()->assertJsonPath('validacao', 'aprovada');

    expect($vinculo->fresh()->validacao)->toBe('aprovada');
});

it('duvida muda para aguardando_documento registrando quem conferiu', function () {
    $vinculo = ResponsavelParticipante::factory()->create(['validacao' => 'pendente']);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'pedir_documento'], $this->authCoord)
        ->assertOk()->assertJsonPath('validacao', 'aguardando_documento');

    $atual = $vinculo->fresh();
    expect($atual->validacao)->toBe('aguardando_documento');
    expect($atual->conferido_em)->not->toBeNull();
    expect($atual->conferido_por)->toBe($this->coordenadora->id);
});

it('revoga encerrando o vinculo mas preservando o log', function () {
    $vinculo = ResponsavelParticipante::factory()->aprovado()->create();

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", [
        'decisao' => 'revogar', 'motivo' => 'Pedido do responsável',
    ], $this->authCoord)->assertOk();

    expect($vinculo->fresh()->fim)->not->toBeNull();
    expect(SolicitacaoVinculo::query()->where('tipo', SolicitacaoVinculo::TIPO_REVOGACAO)->exists())->toBeTrue();
});
