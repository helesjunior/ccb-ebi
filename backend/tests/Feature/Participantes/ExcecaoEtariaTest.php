<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];

    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->authResp = ['Authorization' => 'Bearer '.$this->responsavel->createToken('t')->plainTextToken];
});

it('responsavel com vinculo aprovado solicita excecao inclusiva', function () {
    $participante = Participante::factory()->create(['data_nascimento' => '2014-06-15']);

    ResponsavelParticipante::factory()->aprovado()->create([
        'usuario_id' => $this->responsavel->id,
        'participante_id' => $participante->id,
    ]);

    $this->postJson("/api/v1/participants/{$participante->id}/age-exceptions", [
        'tipo_necessidade' => 'neurodivergencia',
        'apoios_necessarios' => 'Ambiente com menos estímulos.',
        'justificativa_funcional' => 'Acompanhamento contínuo.',
    ], $this->authResp + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('status', 'solicitada');

    expect(ExcecaoEtaria::query()->where('participante_id', $participante->id)->exists())->toBeTrue();
});

it('coordenadora aprova a excecao e registra auditoria', function () {
    $excecao = ExcecaoEtaria::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id, 'status' => ExcecaoEtaria::STATUS_SOLICITADA,
    ]);

    $this->patchJson("/api/v1/age-exceptions/{$excecao->id}", [
        'decisao' => 'aprovar',
        'vigente_ate' => now()->addYear()->toDateString(),
    ], $this->auth)->assertOk()->assertJsonPath('status', 'aprovada');

    expect($excecao->fresh()->decidido_por)->toBe($this->coordenadora->id);
    expect(EventoAuditoria::query()->where('acao', 'excecao.decidida')->exists())->toBeTrue();
});

it('coordenadora revoga a excecao', function () {
    $excecao = ExcecaoEtaria::factory()->aprovada()->create(['participante_id' => participanteNaIgreja($this->igreja)->id]);

    $this->patchJson("/api/v1/age-exceptions/{$excecao->id}", [
        'decisao' => 'revogar', 'motivo' => 'Fim do ciclo',
    ], $this->auth)->assertOk()->assertJsonPath('status', 'revogada');

    expect($excecao->fresh()->revogado_em)->not->toBeNull();
});

it('responsavel sem permissao nao decide excecao', function () {
    $excecao = ExcecaoEtaria::factory()->create(['status' => ExcecaoEtaria::STATUS_SOLICITADA]);

    $this->patchJson("/api/v1/age-exceptions/{$excecao->id}", [
        'decisao' => 'aprovar',
    ], $this->authResp)->assertForbidden();
});

function decidirExcecao(array $auth, string $id)
{
    app('auth')->forgetGuards();

    return test()->patchJson("/api/v1/age-exceptions/{$id}", ['decisao' => 'aprovar'], $auth);
}

function excecaoSolicitadaDe(Participante $participante): ExcecaoEtaria
{
    return ExcecaoEtaria::factory()->create(['participante_id' => $participante->id, 'status' => ExcecaoEtaria::STATUS_SOLICITADA]);
}

it('CT-37 coordenadora nao decide excecao fora do territorio nem inexistente', function () {
    ['igreja' => $igrejaB] = igrejaComEbi();
    $excecao = excecaoSolicitadaDe(participanteNaIgreja($igrejaB));

    foreach ([$excecao->id, (string) Str::ulid()] as $id) {
        decidirExcecao($this->auth, $id)->assertNotFound()
            ->assertJsonPath('code', 'not_found')
            ->assertJsonPath('detail', 'Exceção não encontrada.');
    }

    expect($excecao->fresh()->status)->toBe(ExcecaoEtaria::STATUS_SOLICITADA)
        ->and($excecao->fresh()->decidido_por)->toBeNull()
        ->and(EventoAuditoria::query()->where('acao', 'excecao.decidida')->exists())->toBeFalse();
});

it('CT-38 coordenadora decide excecao do proprio territorio', function () {
    $excecao = excecaoSolicitadaDe(participanteNaIgreja($this->igreja));

    decidirExcecao($this->auth, $excecao->id)->assertOk()->assertJsonPath('status', 'aprovada');

    expect(EventoAuditoria::query()->where('acao', 'excecao.decidida')->count())->toBe(1);
});

it('CT-39 participante sem igreja: presenca valida liga; sem presenca so o super_admin decide', function () {
    $comPresenca = Participante::factory()->create(['igreja_referencia_id' => null]);
    $sessao = Sessao::factory()->create(['ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id]);
    Presenca::factory()->create([
        'sessao_id' => $sessao->id, 'participante_id' => $comPresenca->id,
        'registrado_por' => $this->coordenadora->id, 'status' => Presenca::STATUS_VALIDA,
    ]);
    $semPresenca = excecaoSolicitadaDe(Participante::factory()->create(['igreja_referencia_id' => null]));

    decidirExcecao($this->auth, excecaoSolicitadaDe($comPresenca)->id)->assertOk();
    decidirExcecao($this->auth, $semPresenca->id)->assertNotFound();

    $admin = criarUsuarioComPapel('super_admin', 'central', 'c1');
    decidirExcecao([
        'Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken,
        'X-Acting-Scope' => 'papel=super_admin;escopo=central:c1',
    ], $semPresenca->id)->assertOk()->assertJsonPath('status', 'aprovada');
});
