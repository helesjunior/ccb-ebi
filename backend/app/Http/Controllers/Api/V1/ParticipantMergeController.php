<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Participantes\Contracts\GerirMesclagem;
use App\Http\Requests\Participantes\MesclarParticipanteRequest;
use App\Http\Resources\ParticipanteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mesclagem de duplicados (CA-27): funde e desfaz, sempre auditado e reversivel.
 * Rotas sob `can:participante.mesclar`.
 */
class ParticipantMergeController
{
    public function __construct(private readonly GerirMesclagem $mesclagem) {}

    public function store(MesclarParticipanteRequest $request, string $id): JsonResponse
    {
        $principal = $this->mesclagem->mesclar(
            (string) $request->user()?->getAuthIdentifier(),
            $id,
            (string) $request->validated('duplicado_id'),
        );

        return (new ParticipanteResource($principal))->response();
    }

    public function undo(Request $request, string $id): JsonResponse
    {
        $principal = $this->mesclagem->desfazer(
            (string) $request->user()?->getAuthIdentifier(),
            $id,
        );

        return (new ParticipanteResource($principal))->response();
    }
}
