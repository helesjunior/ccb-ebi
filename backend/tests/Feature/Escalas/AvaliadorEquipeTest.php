<?php

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Services\AvaliadorEquipe;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\IntervaloSessao;
use App\Domain\Sessoes\Models\Sessao;
use App\Support\EquipeMinima\CalculadoraEquipeMinima;

beforeEach(function () {
    $this->igreja = Igreja::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $this->ebi = Ebi::factory()->create(['igreja_id' => $this->igreja->id]);
    $this->calculadora = app(CalculadoraEquipeMinima::class);
});

/** Vigencias de um usuario como `ConsultarEquipeEbi::vigencias` devolve. */
function vigencias(string $dataInicio, ?string $dataFim, string $papel = 'coordenadora'): array
{
    return [['papel' => $papel, 'inicio' => $dataInicio, 'fim' => $dataFim]];
}

it('paraEscala arredonda para cima e nao divide por zero', function () {
    expect($this->calculadora->paraEscala(0, 4, 1, 0)['auxiliares_necessarios'])->toBe(0)
        ->and($this->calculadora->paraEscala(0, 4, 1, 0)['atingida'])->toBeTrue()
        ->and($this->calculadora->paraEscala(1, 4, 1, 0)['auxiliares_necessarios'])->toBe(1)
        ->and($this->calculadora->paraEscala(4, 4, 1, 1)['atingida'])->toBeTrue()
        ->and($this->calculadora->paraEscala(5, 4, 1, 1)['auxiliares_necessarios'])->toBe(2)
        ->and($this->calculadora->paraEscala(5, 4, 1, 1)['atingida'])->toBeFalse()
        ->and($this->calculadora->paraEscala(5, 3, 1, 1)['auxiliares_necessarios'])->toBe(2);
});

it('paraEscala e calcular usam a mesma formula de auxiliares', function () {
    expect($this->calculadora->paraEscala(9, 4, 1, 0)['auxiliares_necessarios'])
        ->toBe($this->calculadora->calcular(9, 4, null)['auxiliares_necessarios']);
});

it('vigenteEm respeita inicio e fim pela data local da sessao', function () {
    $avaliador = app(AvaliadorEquipe::class);
    $sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-21 01:00:00', 'fim_previsto' => '2026-11-21 03:00:00',
    ]);
    $intervalo = app(ConsultarSessao::class)->intervalos([$sessao->id])[$sessao->id];

    // 01:00 UTC de 21/11 e 20/11, 22h, em Sao Paulo: o fim em 20/11 ainda vale.
    expect($avaliador->vigenteEm(vigencias('2026-01-01', '2026-11-20'), $intervalo->dataLocal, 'coordenadora'))->toBeTrue()
        ->and($avaliador->vigenteEm(vigencias('2026-01-01', '2026-11-19'), $intervalo->dataLocal, 'coordenadora'))->toBeFalse()
        ->and($avaliador->vigenteEm(vigencias('2026-11-21', null), $intervalo->dataLocal, 'coordenadora'))->toBeFalse()
        ->and($avaliador->vigenteEm(vigencias('2026-11-20', null), $intervalo->dataLocal, 'coordenadora'))->toBeTrue();
});

it('funcao e papel precisam casar: auxiliar escalado como coordenadora nao conta', function () {
    $escala = escalaCom([['usuario_id' => 'u-aux', 'funcao' => EscalaMembro::FUNCAO_COORDENADORA]]);
    $membroId = $escala->membros->first()->id;
    $intervalo = intervaloDa($escala);

    $this->mock(ConsultarEquipeEbi::class, fn ($mock) => $mock
        ->shouldReceive('vigencias')->andReturn(['u-aux' => vigencias('2026-01-01', null, 'auxiliar')]));

    $avaliador = app(AvaliadorEquipe::class);

    expect($avaliador->contagem($escala, $intervalo))->toBe(['coordenadoras' => 0, 'auxiliares' => 0])
        ->and(array_keys($avaliador->semVinculo($escala, $intervalo)))->toBe([$membroId]);

    // A chefe ocupa a funcao de coordenadora.
    $this->mock(ConsultarEquipeEbi::class, fn ($mock) => $mock
        ->shouldReceive('vigencias')->andReturn(['u-aux' => vigencias('2026-01-01', null, 'coordenadora_chefe')]));

    $avaliador = app(AvaliadorEquipe::class);

    expect($avaliador->contagem($escala, $intervalo))->toBe(['coordenadoras' => 1, 'auxiliares' => 0]);
});

it('soConfirmados ignora quem esta em troca ou apenas escalado', function () {
    $escala = escalaCom([
        ['usuario_id' => 'u-c', 'funcao' => EscalaMembro::FUNCAO_COORDENADORA, 'status' => EscalaMembro::STATUS_CONFIRMADO, 'confirmado_em' => now()],
        ['usuario_id' => 'u-t', 'funcao' => EscalaMembro::FUNCAO_AUXILIAR, 'status' => EscalaMembro::STATUS_TROCA_SOLICITADA],
        ['usuario_id' => 'u-s', 'funcao' => EscalaMembro::FUNCAO_AUXILIAR],
        ['usuario_id' => 'u-x', 'funcao' => EscalaMembro::FUNCAO_AUXILIAR, 'status' => EscalaMembro::STATUS_SUBSTITUIDO],
    ]);
    $intervalo = intervaloDa($escala);

    $this->mock(ConsultarEquipeEbi::class, fn ($mock) => $mock
        ->shouldReceive('vigencias')->andReturn([
            'u-c' => vigencias('2026-01-01', null, 'coordenadora'),
            'u-t' => vigencias('2026-01-01', null, 'auxiliar'),
            'u-s' => vigencias('2026-01-01', null, 'auxiliar'),
            'u-x' => vigencias('2026-01-01', null, 'auxiliar'),
        ]));

    $avaliador = app(AvaliadorEquipe::class);

    // O substituido ja saiu pelos ativos; sobram o de troca e o escalado.
    expect($avaliador->contagem($escala, $intervalo))->toBe(['coordenadoras' => 1, 'auxiliares' => 2])
        ->and($avaliador->contagem($escala, $intervalo, soConfirmados: true))->toBe(['coordenadoras' => 1, 'auxiliares' => 0]);
});

it('equipeMinima usa o divisor gravado na escala', function () {
    $escala = escalaCom([['usuario_id' => 'u-c', 'funcao' => EscalaMembro::FUNCAO_COORDENADORA]], 9, 4);
    $intervalo = intervaloDa($escala);

    $this->mock(ConsultarEquipeEbi::class, fn ($mock) => $mock
        ->shouldReceive('vigencias')->andReturn(['u-c' => vigencias('2026-01-01', null, 'coordenadora')]));

    $avaliador = app(AvaliadorEquipe::class);
    $minima = $avaliador->equipeMinima($escala, $intervalo);

    expect($minima['estimativa'])->toBe(9)
        ->and($minima['participantes_por_auxiliar'])->toBe(4)
        ->and($minima['coordenadoras_necessarias'])->toBe(1)
        ->and($minima['auxiliares_necessarios'])->toBe(3)
        ->and($minima['coordenadoras_escaladas'])->toBe(1)
        ->and($minima['auxiliares_escalados'])->toBe(0)
        ->and($minima['atingida'])->toBeFalse();
});

/** Escala na EBI/igreja do teste com os membros dados. */
function escalaCom(array $membros, int $estimativa = 20, int $porAuxiliar = 10): Escala
{
    $sessao = Sessao::factory()->create([
        'ebi_id' => test()->ebi->id, 'igreja_id' => test()->igreja->id,
        'inicio_previsto' => '2026-11-15 13:00:00', 'fim_previsto' => '2026-11-15 15:00:00',
    ]);
    $escala = Escala::factory()->create([
        'sessao_id' => $sessao->id, 'ebi_id' => test()->ebi->id, 'igreja_id' => test()->igreja->id,
        'estimativa_participantes' => $estimativa, 'participantes_por_auxiliar' => $porAuxiliar,
    ]);

    foreach ($membros as $membro) {
        EscalaMembro::factory()->for($escala)->create($membro);
    }

    return $escala->refresh();
}

function intervaloDa(Escala $escala): IntervaloSessao
{
    return app(ConsultarSessao::class)->intervalos([$escala->sessao_id])[$escala->sessao_id];
}
