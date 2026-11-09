<?php

namespace App\Domain\Organizacao\Database\Factories;

use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Regional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Regional>
 */
class RegionalFactory extends Factory
{
    protected $model = Regional::class;

    public function definition(): array
    {
        return [
            'central_nacional_id' => CentralNacional::factory(),
            'nome' => 'Regional '.fake()->city(),
            'codigo' => fake()->unique()->numerify('##'),
            'status' => 'ativo',
        ];
    }
}
