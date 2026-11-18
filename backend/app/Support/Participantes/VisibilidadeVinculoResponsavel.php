<?php

namespace App\Support\Participantes;

use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Participantes\Models\ResponsavelParticipante;

/**
 * Listagem e recorte de visibilidade dos vinculos responsavel-participante (RF-03).
 *
 * Um vinculo e visivel ao ator por duas vias, unidas e deduplicadas:
 * 1. **Propriedade** — o vinculo e dele (`usuario_id`).
 * 2. **Territorio** — o participante do vinculo esta sob o escopo do ator com
 *    `vinculo.gerenciar` (via `AutorizacaoParticipante`, que resolve EBI/igreja).
 *
 * Vive em `app/Support` (fora dos modulos) pelo mesmo motivo que
 * `AutorizacaoParticipante`: precisa do `ContextoAtuacao`, que o modulo
 * `Participantes` nao pode importar (ADR-0002).
 */
class VisibilidadeVinculoResponsavel
{
    public function __construct(private readonly AutorizacaoParticipante $autorizacao) {}

    /**
     * @param  string  $validacao  pendente | aprovada | aguardando_documento | recusada | todos
     * @return array<int, ResponsavelParticipante>
     */
    public function listar(string $atorId, ?ContextoAtuacao $contexto, string $validacao): array
    {
        $query = ResponsavelParticipante::query()->with('participante');

        if ($validacao !== 'todos') {
            $query->where('validacao', $validacao);

            // Revogar/contestar grava `validacao = recusada` **e** `fim`: sem o
            // recorte por `fim`, um vinculo encerrado reapareceria como
            // pendente e voltaria a aceitar decisao.
            if (in_array($validacao, ['pendente', 'aprovada', 'aguardando_documento'], true)) {
                $query->whereNull('fim');
            }
        }

        $visiveis = $query->orderByDesc('created_at')->orderByDesc('id')->get()
            ->filter(function (ResponsavelParticipante $vinculo) use ($atorId, $contexto): bool {
                if ($atorId !== '' && $vinculo->usuario_id === $atorId) {
                    return true;
                }

                return $vinculo->participante !== null
                    && $this->autorizacao->pode($contexto, $atorId, $vinculo->participante, Operacoes::VINCULO_GERENCIAR);
            });

        return $visiveis->unique('id')->values()->all();
    }
}
