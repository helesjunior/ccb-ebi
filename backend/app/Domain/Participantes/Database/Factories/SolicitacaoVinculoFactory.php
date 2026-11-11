<?php

namespace App\Domain\Participantes\Database\Factories;

use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\SolicitacaoVinculo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @extends Factory<SolicitacaoVinculo>
 */
class SolicitacaoVinculoFactory extends Factory
{
    protected $model = SolicitacaoVinculo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => null,
            'participante_id' => Participante::factory(),
            'responsavel_participante_id' => null,
            'tipo' => SolicitacaoVinculo::TIPO_SOLICITACAO,
            'status' => SolicitacaoVinculo::STATUS_PENDENTE,
            'decidido_por' => null,
            'decidido_em' => null,
            'motivo' => null,
        ];
    }

    /**
     * Informa o usuario que pede o vinculo. `usuario_id` e uma FK obrigatoria de
     * outro modulo (Identidade); informa-la aqui evita que o modulo Participantes
     * importe o modelo `Usuario` (fronteira modular, ADR-0002).
     */
    public function forUsuario(string $usuarioId): static
    {
        return $this->state(['usuario_id' => $usuarioId]);
    }

    /**
     * @param  Collection<int, SolicitacaoVinculo>  $results
     */
    protected function store(Collection $results): void
    {
        foreach ($results as $model) {
            if ($model->getAttribute('usuario_id') === null) {
                throw new LogicException(
                    'SolicitacaoVinculo exige um usuario: use forUsuario($usuarioId) '
                    .'ou passe ["usuario_id" => $usuarioId].',
                );
            }
        }

        parent::store($results);
    }
}
