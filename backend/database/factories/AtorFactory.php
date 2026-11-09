<?php

namespace Database\Factories;

use App\Domain\Identidade\Models\Ator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Ator>
 */
class AtorFactory extends Factory
{
    protected $model = Ator::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'papel' => 'responsavel',
            'senha' => Hash::make('senha'),
        ];
    }
}
