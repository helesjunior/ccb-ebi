<?php

namespace App\Domain\Escalas\Database\Factories;

use App\Domain\Escalas\Models\Escala;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Escala> */
class EscalaFactory extends Factory
{
    protected $model = Escala::class;

    public function definition(): array
    {
        // `sessao_id` e unico: cada escala nova recebe a sua sessao, com o
        // mesmo EBI e a mesma igreja.
        $sessao = Sessao::factory();

        return [
            'sessao_id' => $sessao,
            'ebi_id' => fn (array $atributos): string => (string) $this->ebiDaSessao($atributos['sessao_id']),
            'igreja_id' => fn (array $atributos): string => (string) $this->igrejaDaSessao($atributos['sessao_id']),
            'estimativa_participantes' => 20,
            'participantes_por_auxiliar' => 10,
            'status' => Escala::STATUS_PREVISTA,
            'criado_por' => (string) str()->ulid(),
        ];
    }

    private function ebiDaSessao(string $sessaoId): string
    {
        return (string) Sessao::query()->whereKey($sessaoId)->value('ebi_id');
    }

    private function igrejaDaSessao(string $sessaoId): string
    {
        return (string) Sessao::query()->whereKey($sessaoId)->value('igreja_id');
    }
}
