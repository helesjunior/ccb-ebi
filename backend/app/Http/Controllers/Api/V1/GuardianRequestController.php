<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Participantes\Contracts\GerirVinculoResponsavel;
use App\Http\Requests\Participantes\DecidirVinculoRequest;
use App\Http\Resources\ResponsavelParticipanteResource;
use Illuminate\Http\JsonResponse;

/**
 * Decisao da coordenadora sobre um pedido de vinculo (RF-03): aprovar, recusar,
 * pedir documento, revogar ou contestar. Rota sob `can:vinculo.gerenciar`.
 */
class GuardianRequestController
{
    public function __construct(private readonly GerirVinculoResponsavel $vinculos) {}

    public function update(DecidirVinculoRequest $request, string $id): JsonResponse
    {
        $vinculo = $this->vinculos->decidir(
            (string) $request->user()?->getAuthIdentifier(),
            $id,
            (string) $request->validated('decisao'),
            $request->validated('motivo'),
        );

        return (new ResponsavelParticipanteResource($vinculo))->response();
    }
}
