<?php

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Setor;

it('devolve null quando a igreja nao tem vinculo vigente', function () {
    $igreja = Igreja::factory()->create();
    expect(app(ConsultarOrganizacao::class)->setorVigente($igreja->id))->toBeNull();
});

it('resolve o setor vigente de uma igreja vinculada', function () {
    $igreja = Igreja::factory()->create();
    $setor = Setor::factory()->create();
    IgrejaVinculacao::create([
        'igreja_id' => $igreja->id,
        'setor_id' => $setor->id,
        'inicio' => now()->subYear(),
        'fim' => null,
    ]);

    expect(app(ConsultarOrganizacao::class)->setorVigente($igreja->id)?->id)->toBe($setor->id);
});

it('resolve a regional de um setor', function () {
    $setor = Setor::factory()->create();
    expect(app(ConsultarOrganizacao::class)->regionalDoSetor($setor->id)?->id)->toBe($setor->regional_id);
});
