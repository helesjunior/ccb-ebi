<?php

use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Sessoes\Contracts\ConsultarIndicadoresPresenca;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->igreja = Igreja::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $this->ebi = Ebi::factory()->create(['igreja_id' => $this->igreja->id]);
    $this->indicadores = app(ConsultarIndicadoresPresenca::class);
});

/** Sessao na EBI padrao com inicio local e status dados. */
function sessaoEm(string $inicioUtc, string $status, ?bool $realizada = null): Sessao
{
    return Sessao::factory()->create([
        'ebi_id' => test()->ebi->id,
        'igreja_id' => test()->igreja->id,
        'inicio_previsto' => $inicioUtc,
        'fim_previsto' => CarbonImmutable::parse($inicioUtc)->addHour()->format('Y-m-d H:i:s'),
        'status' => $status,
        'realizada' => $realizada,
    ]);
}

it('conta prevista, aberta e encerrada e exclui cancelada', function () {
    sessaoEm('2026-11-10 13:00:00', Sessao::STATUS_PREVISTA);
    sessaoEm('2026-11-11 13:00:00', Sessao::STATUS_ABERTA);
    sessaoEm('2026-11-12 13:00:00', Sessao::STATUS_ENCERRADA, true);
    sessaoEm('2026-11-13 13:00:00', Sessao::STATUS_ENCERRADA, false);
    sessaoEm('2026-11-14 13:00:00', Sessao::STATUS_CANCELADA);

    $previstas = $this->indicadores->sessoesPrevistas(
        [$this->igreja->id], [], '2026-11-01', '2026-11-30', CarbonImmutable::parse('2026-12-01 00:00:00'),
    );

    expect(array_column($previstas, 'id'))->toHaveCount(4)
        ->and(collect($previstas)->pluck('data_local')->unique()->values()->all())
        ->toBe(['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13']);
});

it('nao conta sessao depois da geracao, mesmo dentro do periodo', function () {
    // 00:00 UTC de 21/11 e 21:00 de 20/11 em Sao Paulo: ja "aconteceu".
    $ontem = sessaoEm('2026-11-21 00:00:00', Sessao::STATUS_PREVISTA);
    // 22:00 UTC de 20/11 = 19:00 de 20/11 em Sao Paulo: tambem ja aconteceu.
    $hoje = sessaoEm('2026-11-20 22:00:00', Sessao::STATUS_PREVISTA);
    // 10h locais de 21/11: ainda no futuro na virada do dia (02h UTC de 21/11).
    $adiante = sessaoEm('2026-11-21 13:00:00', Sessao::STATUS_PREVISTA);

    $geracao = CarbonImmutable::parse('2026-11-21 02:00:00');

    $previstas = $this->indicadores->sessoesPrevistas(
        [$this->igreja->id], [], '2026-11-01', '2026-11-30', $geracao,
    );

    expect(array_column($previstas, 'id'))->toBe([$hoje->id, $ontem->id])
        ->and(array_column($previstas, 'id'))->not->toContain($adiante->id);
});
