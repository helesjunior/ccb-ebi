<?php

namespace App\Domain\Identidade\Database\Factories;

use App\Domain\Identidade\Models\Papel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Papel>
 */
class PapelFactory extends Factory
{
    protected $model = Papel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->slug(2),
            'descricao' => fake()->sentence(3),
        ];
    }
}
