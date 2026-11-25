<?php

namespace App\Domain\Escalas\Database\Factories;

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\EquipeMembro;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EquipeMembro> */
class EquipeMembroFactory extends Factory
{
    protected $model = EquipeMembro::class;

    public function definition(): array
    {
        return [
            'equipe_id' => Equipe::factory(),
            'usuario_id' => (string) str()->ulid(),
        ];
    }
}
