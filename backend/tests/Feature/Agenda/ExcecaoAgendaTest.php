<?php

use App\Domain\Agenda\Contracts\ConsultarAgenda;
use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
});

it('feriado criado depois cancela apenas as sessoes previstas do dia', function () {
    $prevista = Sessao::factory()->create(['ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id, 'inicio_previsto' => '2026-12-25 13:00:00', 'fim_previsto' => '2026-12-25 14:30:00', 'status' => Sessao::STATUS_PREVISTA]);
    $aberta = Sessao::factory()->create(['ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id, 'inicio_previsto' => '2026-12-25 15:00:00', 'fim_previsto' => '2026-12-25 16:30:00', 'status' => Sessao::STATUS_ABERTA]);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", ['tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'Natal'], $this->auth)->assertCreated();

    expect($prevista->refresh()->status)->toBe(Sessao::STATUS_CANCELADA)
        ->and($prevista->motivo_cancelamento)->toBe('agenda: Natal')
        ->and($aberta->refresh()->status)->toBe(Sessao::STATUS_ABERTA)
        ->and(EventoAuditoria::query()->where('acao', 'sessao.cancelada')->where('motivo', 'agenda: Natal')->exists())->toBeTrue();
});

it('horario especial substitui a faixa naquele dia', function () {
    $faixa = AgendaEbi::factory()->create(['ebi_id' => $this->ebi->id, 'dia_semana' => 4, 'hora_inicio' => '10:00', 'hora_fim' => '11:30', 'inicio_vigencia' => '2026-01-01']);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", ['tipo' => 'horario_especial', 'data' => '2026-12-24', 'agenda_ebi_id' => $faixa->id, 'hora_inicio' => '09:00', 'hora_fim' => '10:30', 'motivo' => 'véspera de Natal'], $this->auth)->assertCreated();

    $ocorrencias = app(ConsultarAgenda::class)->ocorrenciasDoDia($this->ebi->id, '2026-12-24');
    expect($ocorrencias)->toHaveCount(1)->and($ocorrencias[0]->horaInicio)->toBe('09:00')->and($ocorrencias[0]->horaFim)->toBe('10:30');
});

it('feriado zera as ocorrencias do dia', function () {
    AgendaEbi::factory()->create(['ebi_id' => $this->ebi->id, 'dia_semana' => 5, 'inicio_vigencia' => '2026-01-01']);
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", ['tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'Natal'], $this->auth)->assertCreated();
    expect(app(ConsultarAgenda::class)->ocorrenciasDoDia($this->ebi->id, '2026-12-25'))->toBe([]);
});

it('limita o motivo a 3..247 para caber "agenda: " na auditoria', function () {
    Sessao::factory()->create(['ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id, 'inicio_previsto' => '2026-12-25 13:00:00', 'fim_previsto' => '2026-12-25 14:30:00', 'status' => Sessao::STATUS_PREVISTA]);
    $url = "/api/v1/ebis/{$this->ebi->id}/agenda-exceptions";

    $this->postJson($url, ['tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => str_repeat('a', 248)], $this->auth)->assertStatus(422);
    $this->postJson($url, ['tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'ab'], $this->auth)->assertStatus(422);
    $this->postJson($url, ['tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => str_repeat('a', 247)], $this->auth)->assertCreated();

    expect(EventoAuditoria::query()->where('acao', 'sessao.cancelada')->value('motivo'))
        ->toBe('agenda: '.str_repeat('a', 247))->toHaveLength(255);
});

it('recusa excecao repetida', function () {
    $dados = ['tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'Natal'];
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", $dados, $this->auth)->assertCreated();
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", $dados, $this->auth)->assertStatus(422)->assertJsonPath('code', 'agenda_excecao_duplicada');
});
