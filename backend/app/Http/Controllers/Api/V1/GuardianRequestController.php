<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Domain\Participantes\Contracts\GerirVinculoResponsavel;
use App\Http\Requests\Participantes\DecidirVinculoRequest;
use App\Http\Resources\ResponsavelParticipanteResource;
use App\Support\Participantes\AutorizacaoParticipante;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Decisao da coordenadora sobre um pedido de vinculo (RF-03): aprovar, recusar,
 * pedir documento, revogar ou contestar. Rota sob `can:vinculo.gerenciar`.
 */
class GuardianRequestController
{
    public function __construct(
        private readonly GerirVinculoResponsavel $vinculos,
        private readonly GerirParticipante $participantes,
        private readonly AutorizacaoParticipante $autorizacao,
    ) {}

    public function update(DecidirVinculoRequest $request, string $id): JsonResponse
    {
        $atorId = (string) $request->user()?->getAuthIdentifier();
        $vinculo = $this->vinculos->encontrar($id);
        $participante = $vinculo !== null
            ? $this->participantes->encontrar((string) $vinculo->participante_id)
            : null;

        // Decidir exige o territorio do participante; fora dele o vinculo se
        // comporta como inexistente (404), para nao revelar que existe (RF-03).
        if ($vinculo === null || $participante === null
            || ! $this->autorizacao->pode($this->contexto($request), $atorId, $participante, Operacoes::VINCULO_GERENCIAR)) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vínculo não encontrado.');
        }

        $vinculo = $this->vinculos->decidir(
            $atorId,
            $id,
            (string) $request->validated('decisao'),
            $request->validated('motivo'),
        );

        return (new ResponsavelParticipanteResource($vinculo))->response();
    }

    private function contexto(Request $request): ?ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        return $contexto instanceof ContextoAtuacao ? $contexto : null;
    }
}
