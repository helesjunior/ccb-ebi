<?php

use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    $this->igreja = Igreja::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $this->ebi = Ebi::factory()->create(['igreja_id' => $this->igreja->id]);
    $this->sessoes = app(ConsultarSessao::class);
});

it('intervalos trazem status e a data local no fuso da igreja', function () {
    $sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-21 01:00:00',
        'fim_previsto' => '2026-11-21 03:00:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);

    $intervalo = $this->sessoes->intervalos([$sessao->id])[$sessao->id];

    // 01:00 UTC de sabado ainda e sexta-feira, 22:00, em Sao Paulo.
    expect($intervalo->dataLocal)->toBe('2026-11-20')
        ->and($intervalo->status)->toBe(Sessao::STATUS_PREVISTA)
        ->and($intervalo->ebiId)->toBe($this->ebi->id)
        ->and($intervalo->igrejaId)->toBe($this->igreja->id)
        ->and($intervalo->timezone)->toBe('America/Sao_Paulo')
        ->and($intervalo->inicioPrevisto->toIso8601String())->toBe('2026-11-21T01:00:00+00:00')
        ->and($intervalo->fimPrevisto->toIso8601String())->toBe('2026-11-21T03:00:00+00:00');
});

it('intervalos ignoram ids inexistentes', function () {
    $sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-21 14:00:00', 'fim_previsto' => '2026-11-21 16:00:00',
    ]);

    $mapa = $this->sessoes->intervalos([$sessao->id, 'inexistente']);

    expect(array_keys($mapa))->toBe([$sessao->id])
        ->and($mapa[$sessao->id]->dataLocal)->toBe('2026-11-21');
});
