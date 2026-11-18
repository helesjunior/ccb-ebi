<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-18 13:00:00',
        'fim_previsto' => '2026-11-18 14:30:00',
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
    $this->participante = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
});

it('lanca presenca em papel em sessao encerrada com motivo e auditoria', function () {
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id,
        'motivo' => 'queda de energia — lista em papel',
    ], $this->auth)->assertCreated()->assertJsonPath('presenca.origem', 'contingencia');

    $evento = EventoAuditoria::query()->where('acao', 'presenca.contingencia')->firstOrFail();
    expect($evento->motivo)->toBe('queda de energia — lista em papel');
    expect($evento->escopo)->toBe("ebi:{$this->ebi->id}");
});

it('recusa sessao prevista, aberta ou cancelada', function () {
    foreach ([Sessao::STATUS_PREVISTA, Sessao::STATUS_ABERTA, Sessao::STATUS_CANCELADA] as $status) {
        $this->sessao->update(['status' => $status]);
        $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
            'participante_id' => $this->participante->id, 'motivo' => 'papel',
        ], $this->auth)->assertStatus(409);
    }
});

it('respeita a elegibilidade e a unicidade', function () {
    $bloqueado = Participante::factory()->create(['data_nascimento' => '2013-01-01']);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $bloqueado->id, 'motivo' => 'lista em papel',
    ], $this->auth)->assertStatus(422)
        ->assertJsonPath('code', 'inelegivel')
        ->assertJsonPath('motivo_bloqueio', 'excecao_ausente_ou_invalida');

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => 'lista em papel',
    ], $this->auth)->assertCreated();

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => 'lista em papel',
    ], $this->auth)->assertOk();

    expect(Presenca::query()->where('sessao_id', $this->sessao->id)->count())->toBe(1);
});

it('valida o limite de tamanho do motivo e aceita 500 caracteres', function () {
    foreach ([str_repeat('a', 2), str_repeat('a', 501)] as $motivo) {
        $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
            'participante_id' => $this->participante->id, 'motivo' => $motivo,
        ], $this->auth)->assertStatus(422);
    }

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => str_repeat('a', 500),
    ], $this->auth)->assertCreated();
});

it('auxiliar nao lanca contingencia', function () {
    $aux = criarUsuarioComPapel('auxiliar', 'ebi', $this->ebi->id);
    $auth = [
        'Authorization' => 'Bearer '.$aux->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=auxiliar;escopo=ebi:{$this->ebi->id}",
    ];

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => 'lista em papel',
    ], $auth)->assertForbidden();
});
