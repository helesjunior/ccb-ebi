<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Participantes\Contracts\GerirExcecaoEtaria;
use App\Http\Requests\Participantes\DecidirExcecaoRequest;
use App\Http\Requests\Participantes\SolicitarExcecaoRequest;
use App\Http\Resources\ExcecaoEtariaResource;
use Illuminate\Http\JsonResponse;

/**
 * Excecao inclusiva (FL-06, ADR-0012): solicitacao pelo responsavel; decisao
 * pela coordenadora (`can:excecao.decidir`).
 */
class AgeExceptionController
{
    public function __construct(private readonly GerirExcecaoEtaria $excecoes) {}

    public function store(SolicitarExcecaoRequest $request, string $participantId): JsonResponse
    {
        $excecao = $this->excecoes->solicitar(
            (string) $request->user()?->getAuthIdentifier(),
            $participantId,
            $request->validated(),
        );

        return (new ExcecaoEtariaResource($excecao))->response()->setStatusCode(201);
    }

    public function update(DecidirExcecaoRequest $request, string $id): JsonResponse
    {
        $validado = $request->validated();

        $excecao = $this->excecoes->decidir(
            (string) $request->user()?->getAuthIdentifier(),
            $id,
            (string) $validado['decisao'],
            $validado,
        );

        return (new ExcecaoEtariaResource($excecao))->response();
    }
}
