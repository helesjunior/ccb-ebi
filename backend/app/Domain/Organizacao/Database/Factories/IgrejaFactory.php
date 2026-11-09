<?php

namespace App\Domain\Organizacao\Database\Factories;

use App\Domain\Organizacao\Models\Igreja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Igreja>
 */
class IgrejaFactory extends Factory
{
    protected $model = Igreja::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('######'),
            'nome' => 'Igreja '.fake()->city(),
            'endereco' => fake()->address(),
            'latitude' => null,
            'longitude' => null,
            'timezone' => 'America/Sao_Paulo',
            'status' => 'ativo',
        ];
    }
}
