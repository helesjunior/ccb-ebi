<?php

use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Sessoes\Contracts\GerarSessoesPrevistas;
use App\Domain\Sessoes\Models\Sessao;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->auth = function (string $papel = 'coordenadora', ?string $ebiId = null) {
        $u = criarUsuarioComPapel($papel, 'ebi', $ebiId ?? $this->ebi->id);

        return [
            'Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken,
            'X-Acting-Scope' => "papel={$papel};escopo=ebi:".($ebiId ?? $this->ebi->id),
        ];
    };
});

it('gera no fuso da igreja, nao duplica e nao ressuscita cancelada', function () {
    $this->igreja->update(['timezone' => 'America/Manaus']);
    AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'dia_semana' => 1,
        'hora_inicio' => '19:30',
        'hora_fim' => '21:00',
        'inicio_vigencia' => '2026-01-01',
    ]);

    $auth = ($this->auth)();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 1);

    $sessao = Sessao::query()->firstOrFail();
    expect($sessao->inicio_previsto->utc()->format('Y-m-d H:i'))->toBe('2026-11-16 23:30');

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 0)->assertJsonPath('ignoradas', 1);

    $sessao->update(['status' => Sessao::STATUS_CANCELADA, 'motivo_cancelamento' => 'sem equipe']);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 0);

    expect($sessao->refresh()->status)->toBe(Sessao::STATUS_CANCELADA);
});

it('respeita feriado e horario especial', function () {
    $this->igreja->update(['timezone' => 'America/Sao_Paulo']);
    $faixa = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'dia_semana' => 4,
        'inicio_vigencia' => '2026-01-01',
    ]);

    $auth = ($this->auth)();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
        'tipo' => 'horario_especial', 'data' => '2026-12-24', 'agenda_ebi_id' => $faixa->id,
        'hora_inicio' => '09:00', 'hora_fim' => '10:30', 'motivo' => 'véspera',
    ], $auth)->assertCreated();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-12-24', 'ate' => '2026-12-24',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 1);

    expect(Sessao::query()->firstOrFail()->inicio_previsto->setTimezone('America/Sao_Paulo')->format('H:i'))->toBe('09:00');

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
        'tipo' => 'feriado', 'data' => '2026-12-31', 'motivo' => 'recesso',
    ], $auth)->assertCreated();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-12-31', 'ate' => '2026-12-31',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 0);
});

it('recusa intervalo maior que doze semanas', function () {
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-01-01', 'ate' => '2026-06-01',
    ], ($this->auth)())->assertStatus(422)->assertJsonPath('code', 'intervalo_invalido');
});

it('o comando gera para todas as EBIs ativas', function () {
    $outra = igrejaComEbi()['ebi'];

    foreach ([$this->ebi, $outra] as $ebi) {
        AgendaEbi::factory()->create([
            'ebi_id' => $ebi->id,
            'dia_semana' => now()->dayOfWeekIso,
            'inicio_vigencia' => now()->subYear()->toDateString(),
        ]);
    }

    $this->artisan('sessoes:gerar', ['--dias' => 7])->assertSuccessful();

    expect(Sessao::query()->count())->toBeGreaterThanOrEqual(2);
});

it('auxiliar nao gera sessoes', function () {
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], ($this->auth)('auxiliar'))->assertForbidden();
});

it('ignora sobreposicao ativa mas permite intervalos adjacentes e sobreposicao cancelada', function () {
    $this->igreja->update(['timezone' => 'UTC']);
    AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'dia_semana' => 1,
        'hora_inicio' => '19:30',
        'hora_fim' => '21:00',
        'inicio_vigencia' => '2026-01-01',
    ]);
    $existente = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-16 20:00:00+00',
        'fim_previsto' => '2026-11-16 22:00:00+00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
    $gerador = app(GerarSessoesPrevistas::class);

    expect($gerador->gerar($this->ebi->id, '2026-11-16', '2026-11-16')->ignoradas)->toBe(1);

    $existente->update(['status' => Sessao::STATUS_CANCELADA, 'motivo_cancelamento' => 'sem equipe']);
    expect($gerador->gerar($this->ebi->id, '2026-11-16', '2026-11-16')->criadas)->toBe(1);

    Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-23 21:00:00+00',
        'fim_previsto' => '2026-11-23 22:00:00+00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
    expect($gerador->gerar($this->ebi->id, '2026-11-23', '2026-11-23')->criadas)->toBe(1);
});

it('audita uma vez por chamada inclusive sem criacoes', function () {
    $gerador = app(GerarSessoesPrevistas::class);
    $resultado = $gerador->gerar($this->ebi->id, '2026-11-16', '2026-11-16');
    expect($resultado->de)->toBe('2026-11-16')->and($resultado->ate)->toBe('2026-11-16');

    $eventos = EventoAuditoria::query()->where('acao', 'sessao.gerada')->get();
    expect($eventos)->toHaveCount(1)
        ->and($eventos->first()->escopo)->toBe("ebi:{$this->ebi->id}")
        ->and($eventos->first()->motivo)->toBeNull()
        ->and($eventos->first()->metadados_minimizados)->toBe([
            'criadas' => 0, 'ignoradas' => 0, 'de' => '2026-11-16', 'ate' => '2026-11-16',
        ]);
});

it('recusa intervalo invertido no contrato', function () {
    try {
        app(GerarSessoesPrevistas::class)->gerar($this->ebi->id, '2026-11-22', '2026-11-16');
        test()->fail('Deveria recusar intervalo invertido.');
    } catch (ProblemException $erro) {
        expect($erro->problemCode)->toBe('intervalo_invalido')->and($erro->status)->toBe(422);
    }
});

it('usa quatro semanas por padrao e aceita o limite de doze semanas', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00:00'));
    $auth = ($this->auth)();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [], $auth)
        ->assertCreated()->assertJsonPath('de', '2026-11-16')->assertJsonPath('ate', '2026-12-14');
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'semanas' => 12,
    ], $auth)->assertCreated()->assertJsonPath('ate', '2027-02-08');
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'semanas' => 13,
    ], $auth)->assertUnprocessable()->assertJsonValidationErrors('semanas');
});

it('aceita apenas ate usando hoje como inicio', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00:00'));

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'ate' => '2026-11-22',
    ], ($this->auth)())->assertCreated()->assertJsonPath('de', '2026-11-16')->assertJsonPath('ate', '2026-11-22');
});
