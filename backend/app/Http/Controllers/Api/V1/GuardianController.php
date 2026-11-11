<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Participantes\Contracts\GerirVinculoResponsavel;
use App\Http\Requests\Participantes\SolicitarVinculoRequest;
use App\Http\Resources\ParticipanteResource;
use App\Http\Resources\ResponsavelParticipanteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vinculos do responsavel (RF-03). A criacao do vinculo parte do responsavel
 * autenticado; a decisao e da coordenadora (rota sob `vinculo.gerenciar`).
 */
class GuardianController
{
    public function __construct(private readonly GerirVinculoResponsavel $vinculos) {}

    public function store(SolicitarVinculoRequest $request, string $participantId): JsonResponse
    {
        $vinculo = $this->vinculos->solicitar(
            (string) $request->user()?->getAuthIdentifier(),
            $participantId,
            $request->validated(),
        );

        return (new ResponsavelParticipanteResource($vinculo))->response()->setStatusCode(201);
    }

    /** Dependentes do ator autenticado (autorizacao por propriedade, CA-04). */
    public function meusDependentes(Request $request): JsonResponse
    {
        $dependentes = $this->vinculos->dependentesDe((string) $request->user()?->getAuthIdentifier());

        return response()->json([
            'data' => ParticipanteResource::collection($dependentes)->resolve($request),
        ]);
    }
}
