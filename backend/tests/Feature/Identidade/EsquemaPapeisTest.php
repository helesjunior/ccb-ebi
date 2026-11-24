<?php

use App\Domain\Identidade\Models\Papel;
use Database\Seeders\PapelSeeder;

it('semeia os onze papeis com codigo unico', function () {
    $this->seed(PapelSeeder::class);

    expect(Papel::query()->count())->toBe(12)
        ->and(Papel::query()->where('codigo', 'coordenadora')->exists())->toBeTrue()
        ->and(Papel::query()->where('codigo', 'coordenadora_chefe')->exists())->toBeTrue()
        ->and(Papel::query()->where('codigo', 'ministerio_central')->exists())->toBeTrue();

    // Reexecutar nao duplica.
    $this->seed(PapelSeeder::class);
    expect(Papel::query()->count())->toBe(12);
});
