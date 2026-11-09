<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use Database\Seeders\PapelSeeder;

it('vigente e o vinculo sem fim', function () {
    $this->seed(PapelSeeder::class);
    $papel = Papel::query()->where('codigo', 'coordenadora')->firstOrFail();

    $u = Usuario::factory()->create();
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel->id, 'tipo_escopo' => 'ebi', 'escopo_id' => '01HZZZ', 'inicio' => '2026-01-01', 'fim' => null]);
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel->id, 'tipo_escopo' => 'ebi', 'escopo_id' => '01HYYY', 'inicio' => '2025-01-01', 'fim' => '2026-01-01']);

    expect($u->vinculosVigentes()->count())->toBe(1);
});
