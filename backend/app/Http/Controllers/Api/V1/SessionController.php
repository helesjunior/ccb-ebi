<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\GerenciarSessao;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController
{
    public function __construct(private readonly GerenciarSessao $sessoes) {}

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->sessoes->listar($this->usuario($request))], 200);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->sessoes->revogar($this->usuario($request), $id);

        return response()->noContent();
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
