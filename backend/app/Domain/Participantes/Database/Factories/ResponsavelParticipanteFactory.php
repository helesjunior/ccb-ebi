<?php

namespace App\Domain\Participantes\Database\Factories;

use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResponsavelParticipante>
 */
class ResponsavelParticipanteFactory extends Factory
{
    protected $model = ResponsavelParticipante::class;

    public function definition(): array
    {
        return [
            'usuario_id' => null,
            'nome' => fake()->name(),
            'telefone' => fake()->optional()->phoneNumber(),
            'participante_id' => Participante::factory(),
            'tipo_relacao' => 'mae',
            'permissoes' => [],
            'restricoes' => null,
            'status' => 'ativo',
            'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
            'conferido_em' => null,
            'conferido_por' => null,
            'inicio' => now()->toDateString(),
            'fim' => null,
        ];
    }

    public function aprovado(): static
    {
        return $this->state(fn (): array => ['validacao' => ResponsavelParticipante::VALIDACAO_APROVADA]);
    }
}
