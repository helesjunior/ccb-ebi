<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Privacidade\Contracts\SolicitarDireitoTitular;
use App\Http\Requests\Privacidade\SolicitacaoRequest;
use App\Http\Resources\SolicitacaoTitularResource;
use Illuminate\Http\JsonResponse;

class PrivacyRequestController
{
    public function __construct(
        private readonly SolicitarDireitoTitular $solicitar,
    ) {}

    public function store(SolicitacaoRequest $request): JsonResponse
    {
        $dados = $request->validated();

        // O titular so abre solicitacao em nome proprio (RF-16).
        $solicitacao = $this->solicitar->abrir(
            (string) $request->user()->id,
            $dados['tipo'],
            $dados['descricao'] ?? null,
        );

        return response()->json(
            (new SolicitacaoTitularResource($solicitacao))->resolve($request),
            201,
        );
    }
}
