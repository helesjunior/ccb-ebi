<?php

namespace App\Domain\Privacidade\Database\Factories;

use App\Domain\Privacidade\Models\SolicitacaoTitular;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SolicitacaoTitular>
 */
class SolicitacaoTitularFactory extends Factory
{
    protected $model = SolicitacaoTitular::class;

    /**
     * `solicitante_id` nao entra no definition: e uma FK obrigatoria de outro
     * modulo (Identidade), entao quem cria a solicitacao informa o titular
     * real — o modulo Privacidade nao importa modelos de terceiros.
     *
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
}
