<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Http\Requests\Participantes\CadastrarParticipanteRequest;
use App\Http\Resources\ParticipanteResource;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController
{
    public function __construct(private readonly GerirParticipante $participantes) {}

    public function store(CadastrarParticipanteRequest $request): JsonResponse
    {
        $participante = $this->participantes->cadastrar(
            $this->usuarioId($request),
            $request->validated(),
        );

        return (new ParticipanteResource($participante))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        // Leitura por operador: a policy cobre `participante.ler` (territorial).
        // O caminho do responsavel (dependentes) e tratado em `/me/participants`.
        $participante = $this->participantes->encontrar($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');

        return (new ParticipanteResource($participante))->response();
    }

    private function usuarioId(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? '');
    }
}
