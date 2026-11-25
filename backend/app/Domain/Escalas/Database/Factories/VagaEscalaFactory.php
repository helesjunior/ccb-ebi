<?php

namespace App\Domain\Escalas\Database\Factories;

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\VagaEscala;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VagaEscala> */
class VagaEscalaFactory extends Factory
{
    protected $model = VagaEscala::class;

    public function definition(): array
    {
        return [
            'escala_id' => Escala::factory(),
            'funcao' => 'auxiliar',
            'status' => VagaEscala::STATUS_ABERTA,
        ];
    }
}
