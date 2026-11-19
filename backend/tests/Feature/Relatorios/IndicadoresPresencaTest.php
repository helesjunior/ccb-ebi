<?php

use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Contracts\ConsultarIndicadoresPresenca;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->no = arvoreRelatorio();
    $this->indicadores = app(ConsultarIndicadoresPresenca::class);
});

function sessaoRelatorio(string $igrejaId, string $ebiId, string $inicio, string $status = Sessao::STATUS_ENCERRADA, bool $realizada = true): Sessao
{
    return Sessao::factory()->create([
        'igreja_id' => $igrejaId,
        'ebi_id' => $ebiId,
        'inicio_previsto' => $inicio,
        'fim_previsto' => CarbonImmutable::parse($inicio)->addHour()->format('Y-m-d H:i:s'),
        'status' => $status,
        'realizada' => $realizada,
    ]);
}

it('conta presenca valida, ignora estornada e sessoes nao realizadas', function () {
    $s1 = sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-11-10 13:00:00');
    sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-11-11 13:00:00');
    $p = Participante::factory()->create();
    Presenca::factory()->create(['sessao_id' => $s1->id, 'participante_id' => $p->id, 'status' => Presenca::STATUS_VALIDA]);
    Presenca::factory()->create(['sessao_id' => $s1->id, 'participante_id' => Participante::factory()->create()->id, 'status' => Presenca::STATUS_ESTORNADA]);
    sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-11-12 13:00:00', Sessao::STATUS_ENCERRADA, false);

    $totais = $this->indicadores->totais([$this->no['igreja']->id], [$this->no['ebi']->id], '2026-11-01', '2026-11-30');

    expect($totais["ebi:{$this->no['ebi']->id}"])->toBe([
        'participantes_distintos' => 1, 'presencas' => 1, 'sessoes_realizadas' => 2,
    ])->and($totais["igreja:{$this->no['igreja']->id}"])->toBe([
        'participantes_distintos' => 1, 'presencas' => 1, 'sessoes_realizadas' => 2,
    ]);
});

it('conta sessao realizada sem presenca em M-05', function () {
    sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-11-10 13:00:00');

    $totais = $this->indicadores->totais([$this->no['igreja']->id], [], '2026-11-01', '2026-11-30');

    expect($totais["igreja:{$this->no['igreja']->id}"]['sessoes_realizadas'])->toBe(1)
        ->and($totais["igreja:{$this->no['igreja']->id}"]['presencas'])->toBe(0);
});

it('recorta o periodo pelo fuso da igreja', function () {
    $this->no['igreja']->update(['timezone' => 'America/Sao_Paulo']);
    // 00:30 UTC de 01/12 = 21:30 de 30/11 em Brasilia (-3): entra no periodo.
    sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-12-01 00:30:00');

    $totais = $this->indicadores->totais([$this->no['igreja']->id], [], '2026-11-01', '2026-11-30');

    expect($totais["igreja:{$this->no['igreja']->id}"]['sessoes_realizadas'])->toBe(1);
});

it('deixa de fora a sessao do dia seguinte', function () {
    sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-12-01 03:30:00');

    expect($this->indicadores->totais([$this->no['igreja']->id], [], '2026-11-01', '2026-11-30')["igreja:{$this->no['igreja']->id}"]['sessoes_realizadas'])->toBe(0);
});

it('deduplica pelo id canonico da mesclagem', function () {
    $s = sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-11-10 13:00:00');
    $principal = Participante::factory()->create();
    $duplicado = Participante::factory()->create();
    Presenca::factory()->create(['sessao_id' => $s->id, 'participante_id' => $principal->id, 'status' => Presenca::STATUS_VALIDA]);
    Presenca::factory()->create(['sessao_id' => $s->id, 'participante_id' => $duplicado->id, 'status' => Presenca::STATUS_VALIDA]);

    $canonico = [$duplicado->id => $principal->id];
    $totais = $this->indicadores->totais([$this->no['igreja']->id], [], '2026-11-01', '2026-11-30', $canonico);

    expect($totais["igreja:{$this->no['igreja']->id}"])
        ->toBe(['participantes_distintos' => 1, 'presencas' => 2, 'sessoes_realizadas' => 1]);
});

it('lista quem tem presenca valida desde uma data, pelo id canonico', function () {
    $s = sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-10-01 13:00:00');
    $p = Participante::factory()->create();
    Presenca::factory()->create(['sessao_id' => $s->id, 'participante_id' => $p->id, 'status' => Presenca::STATUS_VALIDA]);

    expect($this->indicadores->participantesComPresencaDesde([], CarbonImmutable::parse('2026-09-01')))->toBe([$p->id])
        ->and($this->indicadores->participantesComPresencaDesde([], CarbonImmutable::parse('2026-10-02')))->toBe([]);
});

it('devolve os participantes com presenca por igreja, pelo id canonico', function () {
    $s = sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-10-01 13:00:00');
    $principal = Participante::factory()->create();
    $duplicado = Participante::factory()->create();
    Presenca::factory()->create(['sessao_id' => $s->id, 'participante_id' => $principal->id, 'status' => Presenca::STATUS_VALIDA]);
    Presenca::factory()->create(['sessao_id' => $s->id, 'participante_id' => $duplicado->id, 'status' => Presenca::STATUS_VALIDA]);

    $porIgreja = $this->indicadores->igrejasComPresencaDesde([$duplicado->id => $principal->id], CarbonImmutable::parse('2026-09-01'));

    expect($porIgreja)->toBe([$this->no['igreja']->id => [$principal->id => true]]);
});

it('lista as ebis com sessao realizada nos ultimos 90 dias', function () {
    sessaoRelatorio($this->no['igreja']->id, $this->no['ebi']->id, '2026-08-22 13:00:00');

    expect($this->indicadores->ebisComSessaoDesde(CarbonImmutable::parse('2026-08-21')))->toBe([$this->no['ebi']->id])
        ->and($this->indicadores->ebisComSessaoDesde(CarbonImmutable::parse('2026-08-22')))->toBe([$this->no['ebi']->id])
        ->and($this->indicadores->ebisComSessaoDesde(CarbonImmutable::parse('2026-08-23')))->toBe([]);
});

it('devolve o fuso de cada igreja', function () {
    expect($this->indicadores->fusos([$this->no['igreja']->id]))->toBe([$this->no['igreja']->id => 'America/Sao_Paulo']);
});
