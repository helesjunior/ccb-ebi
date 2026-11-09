<?php

namespace App\Domain\Identidade\Database\Factories;

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VinculoAcesso>
 */
class VinculoAcessoFactory extends Factory
{
    protected $model = VinculoAcesso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'papel_id' => Papel::factory(),
            'tipo_escopo' => VinculoAcesso::TIPO_CENTRAL,
            'escopo_id' => (string) Str::ulid(),
            'inicio' => now()->toDateString(),
            'fim' => null,
            'status' => 'ativo',
        ];
    }

    public function noEbi(string $ebiId): static
    {
        return $this->state(fn (): array => [
            'tipo_escopo' => VinculoAcesso::TIPO_EBI,
            'escopo_id' => $ebiId,
        ]);
    }
}
