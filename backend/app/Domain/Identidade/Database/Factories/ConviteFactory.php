<?php

namespace App\Domain\Identidade\Database\Factories;

use App\Domain\Identidade\Models\Convite;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Convite>
 */
class ConviteFactory extends Factory
{
    protected $model = Convite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_normalizado' => $this->faker->unique()->safeEmail(),
            'papel_id' => Papel::factory(),
            'tipo_escopo' => 'central',
            'escopo_id' => (string) Str::ulid(),
            'token_hash' => hash('sha256', (string) Str::uuid()),
            'expira_em' => now()->addDays(7),
            'convidante_id' => Usuario::factory(),
            'aceito_em' => null,
            'cancelado_em' => null,
        ];
    }
}
