<?php

namespace App\Domain\Escalas\Database\Factories;

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EscalaMembro> */
class EscalaMembroFactory extends Factory
{
    protected $model = EscalaMembro::class;

    public function definition(): array
    {
        return [
            'escala_id' => Escala::factory(),
            'usuario_id' => (string) str()->ulid(),
            'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
            'status' => EscalaMembro::STATUS_ESCALADO,
        ];
    }
}
