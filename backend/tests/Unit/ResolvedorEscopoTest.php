<?php

use App\Domain\Identidade\Services\ResolvedorEscopo;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;

it('vinculo de setor cobre igreja descendente', function () {
    $hierarquia = Mockery::mock(ConsultarHierarquia::class);
    $hierarquia->shouldReceive('ancestrais')->with('igreja', 'ig1')
        ->andReturn(['igreja' => 'ig1', 'setor' => 'set1', 'regional' => 'reg1', 'central' => 'c1']);

    $resolvedor = new ResolvedorEscopo($hierarquia);

    expect($resolvedor->cobre('setor', 'set1', 'igreja', 'ig1'))->toBeTrue()
        ->and($resolvedor->cobre('setor', 'set2', 'igreja', 'ig1'))->toBeFalse() // irmao
        ->and($resolvedor->cobre('regional', 'reg1', 'igreja', 'ig1'))->toBeTrue();
});

it('vinculo de igreja nao cobre outra igreja', function () {
    $hierarquia = Mockery::mock(ConsultarHierarquia::class);
    $hierarquia->shouldReceive('ancestrais')->with('igreja', 'igX')
        ->andReturn(['igreja' => 'igX', 'setor' => 'set9', 'regional' => 'reg9', 'central' => 'c1']);
    $hierarquia->shouldReceive('ancestrais')->with('igreja', 'igY')
        ->andReturn(['igreja' => 'igY', 'setor' => 'set8', 'regional' => 'reg8', 'central' => 'c1']);
    $resolvedor = new ResolvedorEscopo($hierarquia);

    expect($resolvedor->cobre('igreja', 'igX', 'igreja', 'igX'))->toBeTrue()
        ->and($resolvedor->cobre('igreja', 'igX', 'igreja', 'igY'))->toBeFalse();
});
