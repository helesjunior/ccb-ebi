<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\GerirConvite;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Http\Requests\Identidade\ConviteRequest;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvitationController
{
    public function __construct(private readonly GerirConvite $convites) {}

    public function store(ConviteRequest $request): JsonResponse
    {
        $dados = $request->validated();

        $convite = $this->convites->criar(
            $this->contexto($request),
            $dados['email'],
            $dados['papel'],
            $dados['tipo_escopo'],
            $dados['escopo_id'],
            (string) $this->usuario($request)->getAuthIdentifier(),
        );

        return new JsonResponse([
            'id' => (string) $convite->id,
            'email' => (string) $convite->email_normalizado,
            'tipo_escopo' => (string) $convite->tipo_escopo,
            'escopo_id' => (string) $convite->escopo_id,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->convites->listar($this->contexto($request))], 200);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->convites->cancelar($this->contexto($request), $id);

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

    private function usuario(Request $request): Authenticatable
    {
        $usuario = $request->user();

        if ($usuario === null) {
            throw new ProblemException('unauthorized', 'Não autenticado', 401);
        }

        return $usuario;
    }
}
