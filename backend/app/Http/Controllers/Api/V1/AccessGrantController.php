<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\GerirVinculo;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Http\Requests\Identidade\VinculoRequest;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccessGrantController
{
    public function __construct(private readonly GerirVinculo $vinculos) {}

    public function store(VinculoRequest $request): JsonResponse
    {
        $dados = $request->validated();

        $vinculo = $this->vinculos->conceder(
            $this->contexto($request),
            $dados['usuario_id'],
            $dados['papel'],
            $dados['tipo_escopo'],
            $dados['escopo_id'],
        );

        return new JsonResponse([
            'id' => (string) $vinculo->id,
            'usuario_id' => (string) $vinculo->usuario_id,
            'tipo_escopo' => (string) $vinculo->tipo_escopo,
            'escopo_id' => (string) $vinculo->escopo_id,
        ], 201);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->vinculos->revogar($this->contexto($request), $id);

        return response()->noContent();
    }

    private function contexto(Request $request): ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao) {
            throw new ProblemException('sem_contexto', 'Acesso negado', 403, 'Sem contexto de atuação vigente.');
        }

        return $contexto;
    }
}
