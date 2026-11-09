<?php

namespace App\Domain\Organizacao\Database\Factories;

use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setor>
 */
class SetorFactory extends Factory
{
    protected $model = Setor::class;

    public function definition(): array
    {
        return [
            'regional_id' => Regional::factory(),
            'nome' => 'Setor '.fake()->city(),
            'codigo' => fake()->unique()->numerify('####'),
            'status' => 'ativo',
        ];
    }
}
