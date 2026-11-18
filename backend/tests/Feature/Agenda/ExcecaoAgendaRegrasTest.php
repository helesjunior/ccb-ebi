<?php

use App\Domain\Agenda\Contracts\ConsultarAgenda;
use App\Domain\Agenda\Contracts\GerirAgenda;
use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Agenda\Models\AgendaExcecao;
use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
});

it('cancela pela data local e registra o autor e um evento por sessao', function () {
    $this->igreja->update(['timezone' => 'America/Sao_Paulo']);
    $sessoes = collect(['2026-12-25 03:00:00', '2026-12-26 02:30:00', '2026-12-25 02:30:00', '2026-12-26 03:00:00'])
        ->map(fn ($inicio) => Sessao::factory()->create([
            'ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id,
            'inicio_previsto' => $inicio, 'fim_previsto' => CarbonImmutable::parse($inicio)->addHour(),
            'status' => Sessao::STATUS_PREVISTA,
        ]));

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
        'tipo' => 'cancelamento', 'data' => '2026-12-25', 'motivo' => 'Recesso',
    ], $this->auth)->assertCreated()->assertJsonStructure(['agenda_ebi_id', 'hora_inicio'])
        ->assertJsonPath('agenda_ebi_id', null)->assertJsonPath('hora_inicio', null);

    foreach ($sessoes as $indice => $sessao) {
        expect($sessao->refresh()->status)->toBe($indice < 2 ? Sessao::STATUS_CANCELADA : Sessao::STATUS_PREVISTA);
        if ($indice < 2) {
            expect($sessao->cancelada_por)->toBe($this->coord->id);
        }
    }
    $eventos = EventoAuditoria::query()->where('acao', 'sessao.cancelada')->get();
    expect($eventos)->toHaveCount(2);
    foreach ($eventos as $evento) {
        expect($evento->recurso)->toBe('sessao')
            ->and($evento->escopo)->toBe("ebi:{$this->ebi->id}")
            ->and($evento->ator_id)->toBe($this->coord->id)
            ->and($evento->motivo)->toBe('agenda: Recesso')
            ->and($evento->metadados_minimizados['sessao_id'])->toBeIn($sessoes->take(2)->pluck('id')->all());
    }
    expect(app(ConsultarAgenda::class)->ocorrenciasDoDia($this->ebi->id, '2026-12-25'))->toBe([]);
});

it('valida os campos por tipo mesmo ao usar o contrato diretamente', function (array $campos) {
    expect(fn () => app(GerirAgenda::class)->criarExcecao($this->ebi->id, [
        'tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'Natal', ...$campos,
    ]))->toThrow(ValidationException::class);
})->with([
    'feriado com horario' => [['hora_inicio' => '10:00']],
    'cancelamento com horario' => [['tipo' => 'cancelamento', 'hora_fim' => '11:00']],
    'especial sem faixa' => [['tipo' => 'horario_especial', 'hora_inicio' => '10:00', 'hora_fim' => '11:00']],
]);

it('recusa faixa de outra ebi e horario especial invertido ou duplicado', function () {
    ['ebi' => $outra] = igrejaComEbi();
    $faixa = AgendaEbi::factory()->create(['ebi_id' => $outra->id]);
    $dados = ['tipo' => 'horario_especial', 'data' => '2026-12-24', 'agenda_ebi_id' => $faixa->id, 'hora_inicio' => '09:00', 'hora_fim' => '10:00', 'motivo' => 'Especial'];
    $url = "/api/v1/ebis/{$this->ebi->id}/agenda-exceptions";
    $this->postJson($url, $dados, $this->auth)->assertUnprocessable();
    $faixa->update(['ebi_id' => $this->ebi->id]);
    $this->postJson($url, [...$dados, 'hora_fim' => '08:00'], $this->auth)->assertUnprocessable();
    $this->postJson($url, $dados, $this->auth)->assertCreated();
    $this->postJson($url, $dados, $this->auth)->assertUnprocessable()->assertJsonPath('code', 'agenda_excecao_duplicada');
});

it('aplica especial somente na faixa e respeita ambos os limites de vigencia', function () {
    $base = ['ebi_id' => $this->ebi->id, 'dia_semana' => 4, 'inicio_vigencia' => '2026-12-24', 'fim_vigencia' => '2026-12-24'];
    $faixa = AgendaEbi::factory()->create([...$base, 'hora_inicio' => '09:00', 'hora_fim' => '10:00']);
    AgendaEbi::factory()->create([...$base, 'hora_inicio' => '11:00', 'hora_fim' => '12:00']);
    AgendaEbi::factory()->create([...$base, 'inicio_vigencia' => '2026-12-25', 'fim_vigencia' => null]);
    AgendaEbi::factory()->create([...$base, 'inicio_vigencia' => '2026-01-01', 'fim_vigencia' => '2026-12-23']);
    app(GerirAgenda::class)->criarExcecao($this->ebi->id, [
        'tipo' => 'horario_especial', 'data' => '2026-12-24', 'agenda_ebi_id' => $faixa->id,
        'hora_inicio' => '08:00', 'hora_fim' => '09:00', 'motivo' => 'Especial',
    ]);

    $ocorrencias = app(ConsultarAgenda::class)->ocorrenciasDoDia($this->ebi->id, '2026-12-24');
    expect($ocorrencias)->toHaveCount(2)
        ->and($ocorrencias[0]->horaInicio)->toBe('08:00')
        ->and($ocorrencias[1]->horaInicio)->toBe('11:00');
});

it('traduz colisao do indice unico mesmo depois da prechecagem', function () {
    AgendaExcecao::creating(function (AgendaExcecao $excecao): void {
        DB::table('agenda_excecao')->insert([...$excecao->getAttributes(), 'id' => (string) Str::ulid()]);
    });

    try {
        $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
            'tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'Natal',
        ], $this->auth)->assertUnprocessable()->assertJsonPath('code', 'agenda_excecao_duplicada');
        expect(AgendaExcecao::query()->count())->toBe(0);
    } finally {
        Event::forget('eloquent.creating: '.AgendaExcecao::class);
    }
});

it('filtra por limite superior isolado e remove somente com autorizacao', function () {
    $gerir = app(GerirAgenda::class);
    $excecao = $gerir->criarExcecao($this->ebi->id, ['tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'Natal']);
    $gerir->criarExcecao($this->ebi->id, ['tipo' => 'feriado', 'data' => '2026-12-26', 'motivo' => 'Recesso']);
    $this->getJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions?ate=2026-12-25", $this->auth)
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $excecao->id);

    $auxiliar = criarUsuarioComPapel('auxiliar', 'ebi', $this->ebi->id);
    app('auth')->forgetGuards();
    $this->deleteJson("/api/v1/agenda-exceptions/{$excecao->id}", [], [
        'Authorization' => 'Bearer '.$auxiliar->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=auxiliar;escopo=ebi:{$this->ebi->id}",
    ])->assertForbidden();
    app('auth')->forgetGuards();
    $this->deleteJson("/api/v1/agenda-exceptions/{$excecao->id}", [], $this->auth)->assertNoContent();
    $this->deleteJson("/api/v1/agenda-exceptions/{$excecao->id}", [], $this->auth)->assertNotFound();
});
