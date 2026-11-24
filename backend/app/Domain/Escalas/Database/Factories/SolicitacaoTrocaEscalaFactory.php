<?php

namespace App\Domain\Escalas\Database\Factories;

use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SolicitacaoTrocaEscala> */
class SolicitacaoTrocaEscalaFactory extends Factory
{
    protected $model = SolicitacaoTrocaEscala::class;

    public function definition(): array
    {
        return [
            'escala_membro_id' => EscalaMembro::factory(),
            // Por padrao o pedido e do proprio membro escalado.
            'solicitante_id' => fn (array $atributos): string => (string) EscalaMembro::query()
                ->whereKey($atributos['escala_membro_id'])->value('usuario_id'),
            'motivo' => $this->faker->sentence(6),
            'status' => SolicitacaoTrocaEscala::STATUS_PENDENTE,
        ];
    }
}
