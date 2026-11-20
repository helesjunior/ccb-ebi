<?php

use App\Domain\Participantes\Contracts\ConsultarIndicadoresParticipante;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Services\GerenciadorMesclagem;
use Carbon\CarbonImmutable;

// Funcoes compartilhadas da arvore de teste (o arquivo nao roda sozinho porque so as declara).

beforeEach(function () {
    $this->no = arvoreRelatorio();
    $this->indicadores = app(ConsultarIndicadoresParticipante::class);
});

function mesclar(string $principalId, string $duplicadoId): Participante
{
    return app(GerenciadorMesclagem::class)->mesclar('ator-teste', $principalId, $duplicadoId);
}

function partRelatorio(string $igrejaId, string $status = Participante::STATUS_ATIVO, ?string $criadoEm = null): Participante
{
    $p = Participante::factory()->create(['igreja_referencia_id' => $igrejaId, 'status' => $status]);
    if ($criadoEm !== null) {
        $p->forceFill(['created_at' => $criadoEm])->save();
    }

    return $p;
}

it('mapeia duplicado para o principal', function () {
    $a = partRelatorio($this->no['igreja']->id);
    $b = partRelatorio($this->no['igreja']->id);
    mesclar($a->id, $b->id);

    expect($this->indicadores->mapaCanonico())->toBe([$b->id => $a->id]);
});

it('resolve a cadeia de mesclagem ate o fim', function () {
    $a = partRelatorio($this->no['igreja']->id);
    $b = partRelatorio($this->no['igreja']->id);
    $c = partRelatorio($this->no['igreja']->id);
    mesclar($a->id, $b->id);
    mesclar($c->id, $a->id);

    $mapa = $this->indicadores->mapaCanonico();

    expect($mapa[$b->id])->toBe($c->id)
        ->and($mapa[$a->id])->toBe($c->id)
        ->and($mapa)->not->toHaveKey($c->id);
});

it('desempata o ciclo pelo menor id', function () {
    $ids = collect(range(1, 2))->map(fn () => Participante::factory()->create())->pluck('id')->sort()->values();
    [$menor, $maior] = [$ids[0], $ids[1]];
    // Ciclo A -> B -> A gravado direto no snapshot do principal (o GerenciadorMesclagem
    // nao permite hoje; divida registrada).
    foreach ([[$menor, $maior], [$maior, $menor]] as [$principal, $duplicado]) {
        Participante::query()->whereKey($principal)->first()->forceFill([
            'snapshot_mesclagem' => ['principal' => ['id' => $principal], 'duplicado' => ['id' => $duplicado]],
        ])->save();
    }

    expect($this->indicadores->mapaCanonico())->toEqual([$maior => $menor, $menor => $menor]);
});

it('conta cadastrados ativos por igreja de referencia', function () {
    partRelatorio($this->no['igreja']->id);
    partRelatorio($this->no['igreja']->id);
    partRelatorio($this->no['igreja']->id, 'inativo');
    Participante::factory()->create(['igreja_referencia_id' => null]);

    expect($this->indicadores->cadastradosPorIgreja())->toBe([$this->no['igreja']->id => 2]);
});

it('lista ativos por presenca recente ou cadastro recente', function () {
    $porPresenca = partRelatorio($this->no['igreja']->id, criadoEm: '2020-01-01 10:00:00');
    $porCadastro = partRelatorio($this->no['igreja']->id, criadoEm: '2026-11-01 10:00:00');
    $antigo = partRelatorio($this->no['igreja']->id, criadoEm: '2020-01-01 10:00:00');
    $inativo = partRelatorio($this->no['igreja']->id, 'inativo', '2026-11-01 10:00:00');

    $ids = $this->indicadores->idsAtivos(
        [],
        CarbonImmutable::parse('2026-08-21 00:00:00'),
        [$porPresenca->id],
    );

    expect($ids)->toEqualCanonicalizing([$porPresenca->id, $porCadastro->id])
        ->and($ids)->not->toContain($antigo->id, $inativo->id);
});

it('devolve a igreja de referencia do id canonico', function () {
    $a = partRelatorio($this->no['igreja']->id);
    $b = partRelatorio($this->no['igreja']->id);
    mesclar($a->id, $b->id);

    expect($this->indicadores->igrejaPorParticipante([$b->id => $a->id], [$a->id, $b->id]))
        ->toBe([$a->id => $this->no['igreja']->id]);
});
