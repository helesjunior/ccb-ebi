<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\ConsultarUsuarios;
use App\Domain\Identidade\Contracts\GerirUsuario;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Http\Requests\Identidade\UsuarioAtualizarRequest;
use App\Http\Resources\UsuarioResource;
use App\Support\PaginacaoCursor;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController
{
    public function __construct(
        private readonly ConsultarUsuarios $consulta,
        private readonly GerirUsuario $gerir,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $contexto = $this->contexto($request);

        $pagina = $this->consulta->listar(
            $contexto,
            $request->only(['status']),
            PaginacaoCursor::limit($request),
        );

        return response()->json([
            'data' => UsuarioResource::collection($pagina->items())->resolve($request),
            'meta' => ['proximo_cursor' => $pagina->nextCursor()?->encode()],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $usuario = $this->consulta->encontrar($this->contexto($request), $id);

        if ($usuario === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Usuário não encontrado');
        }

        return (new UsuarioResource($usuario))->response();
    }

    public function update(UsuarioAtualizarRequest $request, string $id): Response
    {
        $usuario = $this->gerir->atualizar(
            $this->contexto($request),
            $id,
            $request->validated(),
        );

        if ($usuario === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Usuário não encontrado');
        }

        return (new UsuarioResource($usuario))->response();
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
