<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Contracts\ConsultarEquipeEscalada;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Sessoes\Contracts\ConsultarSessao;

/**
 * Auxiliares definidos pela escala aprovada. Conta como o `AvaliadorEquipe`
 * ao aprovar: vaga ativa, funcao `auxiliar` e vinculo vigente na data local
 * da sessao. O divisor registrado na escala e o da aprovacao, nao o atual do
 * EBI.
 */
class ConsultaEquipeEscalada implements ConsultarEquipeEscalada
{
    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly AvaliadorEquipe $avaliador,
    ) {}

    public function auxiliaresDaEscalaAprovada(string $sessaoId): ?int
    {
        $escala = Escala::query()->where('sessao_id', $sessaoId)->first();

        if ($escala === null || $escala->status !== Escala::STATUS_APROVADA) {
            return null;
        }

        $sessao = $this->sessoes->intervalos([$sessaoId])[$sessaoId] ?? null;

        if ($sessao === null) {
            return null;
        }

        return $this->avaliador->contagem($escala, $sessao)['auxiliares'];
    }
}
