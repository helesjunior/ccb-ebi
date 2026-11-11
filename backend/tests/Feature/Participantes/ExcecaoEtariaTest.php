<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;

beforeEach(function () {
    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', (string) Str::ulid());
    $this->auth = ['Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken];

    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->authResp = ['Authorization' => 'Bearer '.$this->responsavel->createToken('t')->plainTextToken];
});

it('responsavel solicita excecao inclusiva', function () {
    $participante = Participante::factory()->create(['data_nascimento' => '2014-06-15']);

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
    $excecao = ExcecaoEtaria::factory()->create(['status' => ExcecaoEtaria::STATUS_SOLICITADA]);

    $this->patchJson("/api/v1/age-exceptions/{$excecao->id}", [
        'decisao' => 'aprovar',
        'vigente_ate' => now()->addYear()->toDateString(),
    ], $this->auth)->assertOk()->assertJsonPath('status', 'aprovada');

    expect($excecao->fresh()->decidido_por)->toBe($this->coordenadora->id);
    expect(EventoAuditoria::query()->where('acao', 'excecao.decidida')->exists())->toBeTrue();
});

it('coordenadora revoga a excecao', function () {
    $excecao = ExcecaoEtaria::factory()->aprovada()->create();

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
