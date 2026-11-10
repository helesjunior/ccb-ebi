<?php

namespace App\Domain\Privacidade\Database\Factories;

use App\Domain\Privacidade\Models\SolicitacaoTitular;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

/**
 * @extends Factory<SolicitacaoTitular>
 */
class SolicitacaoTitularFactory extends Factory
{
    protected $model = SolicitacaoTitular::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'participante_id' => null,
            'tipo' => SolicitacaoTitular::TIPO_ACESSO,
            'protocolo' => strtoupper((string) Str::ulid()),
            'responsavel_id' => null,
            'prazo' => null,
            'status' => SolicitacaoTitular::STATUS_RECEBIDA,
            'descricao' => null,
        ];
    }

    /**
     * Informa o titular dono da solicitacao. `solicitante_id` e uma FK
     * obrigatoria de outro modulo (Identidade); informa-la aqui evita que o
     * modulo Privacidade importe o modelo `Usuario` (fronteira modular).
     */
    public function forSolicitante(string $usuarioId): static
    {
        return $this->state(['solicitante_id' => $usuarioId]);
    }

    /**
     * Falha alto e claro quando a factory persiste sem `solicitante_id`, em vez
     * de estourar uma violacao de FK obscura. `make()` segue igual (a FK so e
     * exigida na gravacao).
     *
     * @param  Collection<int, SolicitacaoTitular>  $results
     */
    protected function store(Collection $results): void
    {
        foreach ($results as $model) {
            if ($model->getAttribute('solicitante_id') === null) {
                throw new LogicException(
                    'SolicitacaoTitular exige um solicitante: use forSolicitante($usuarioId) '
                    .'ou passe ["solicitante_id" => $usuarioId].',
                );
            }
        }

        parent::store($results);
    }
}
