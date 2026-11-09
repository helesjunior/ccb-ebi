<?php

use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;

it('vinculo de setor nao cobre igreja de setor irmao', function () {
    $central = CentralNacional::factory()->create();
    $regional = Regional::factory()->create(['central_nacional_id' => $central->id]);
    $setorA = Setor::factory()->create(['regional_id' => $regional->id]);
    $setorB = Setor::factory()->create(['regional_id' => $regional->id]);
    $igrejaB = Igreja::factory()->create();
    // A ligacao igreja->setor tem vigencia (igreja_vinculacao), nao coluna em igrejas.
    IgrejaVinculacao::create([
        'igreja_id' => $igrejaB->id,
        'setor_id' => $setorB->id,
        'inicio' => '2026-01-01',
        'fim' => null,
    ]);

    $resolvedor = app(ResolverEscopo::class);
    expect($resolvedor->cobre('setor', $setorA->id, 'igreja', $igrejaB->id))->toBeFalse()
        ->and($resolvedor->cobre('setor', $setorB->id, 'igreja', $igrejaB->id))->toBeTrue()
        ->and($resolvedor->cobre('regional', $regional->id, 'igreja', $igrejaB->id))->toBeTrue();
});

it('nao cobre por um nivel acima do vinculo (vertical)', function () {
    $central = CentralNacional::factory()->create();
    $regional = Regional::factory()->create(['central_nacional_id' => $central->id]);
    $setor = Setor::factory()->create(['regional_id' => $regional->id]);
    $igreja = Igreja::factory()->create();
    IgrejaVinculacao::create([
        'igreja_id' => $igreja->id,
        'setor_id' => $setor->id,
        'inicio' => '2026-01-01',
        'fim' => null,
    ]);

    $resolvedor = app(ResolverEscopo::class);
    // Vinculo de setor NUNCA cobre um no acima dele (a propria regional).
    expect($resolvedor->cobre('setor', $setor->id, 'regional', $regional->id))->toBeFalse();
});
