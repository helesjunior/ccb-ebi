<?php

namespace App\Domain\Escalas\Database\Factories;

use App\Domain\Escalas\Models\OfertaVagaEscala;
use App\Domain\Escalas\Models\VagaEscala;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OfertaVagaEscala> */
class OfertaVagaEscalaFactory extends Factory
{
    protected $model = OfertaVagaEscala::class;

    public function definition(): array
    {
        return [
            'vaga_id' => VagaEscala::factory(),
            'usuario_id' => (string) str()->ulid(),
            'status' => OfertaVagaEscala::STATUS_PENDENTE,
        ];
    }
}
