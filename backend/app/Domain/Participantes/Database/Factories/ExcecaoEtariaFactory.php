<?php

namespace App\Domain\Participantes\Database\Factories;

use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExcecaoEtaria>
 */
class ExcecaoEtariaFactory extends Factory
{
    protected $model = ExcecaoEtaria::class;

    public function definition(): array
    {
        return [
            'participante_id' => Participante::factory(),
            'tipo_necessidade' => 'neurodivergencia',
            'apoios_necessarios' => 'Ambiente com menos estímulos.',
            'justificativa_funcional' => 'Acompanhamento contínuo.',
            'status' => ExcecaoEtaria::STATUS_SOLICITADA,
            'solicitado_por' => null,
            'decidido_por' => null,
            'responsavel_concordante_id' => null,
            'decidido_em' => null,
            'vigente_ate' => null,
            'revisao_prevista_em' => null,
            'revogado_em' => null,
            'motivo' => null,
        ];
    }

    public function aprovada(): static
    {
        return $this->state(fn (): array => [
            'status' => ExcecaoEtaria::STATUS_APROVADA,
            'decidido_em' => now(),
        ]);
    }
}
