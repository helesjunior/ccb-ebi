<?php

namespace App\Domain\Identidade\Database\Factories;

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'nome' => fake()->name(),
            'email_normalizado' => Usuario::emailNormalizado($email),
            'email_verificado_em' => now(),
            'senha' => Hash::make('senha'),
            'identidade_federada' => null,
            'status' => 'ativo',
        ];
    }
}
