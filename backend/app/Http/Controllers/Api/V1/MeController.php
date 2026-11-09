<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\ConsultarVinculos;
use App\Http\Resources\ContextoAtuacaoResource;
use App\Http\Resources\UsuarioResource;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController
{
    public function __construct(private readonly ConsultarVinculos $vinculos) {}

    public function me(Request $request): UsuarioResource
    {
        $usuario = $this->usuario($request);
        $usuario->loadMissing('vinculosVigentes.papel');

        return UsuarioResource::make($usuario);
    }

    public function scopes(Request $request): JsonResponse
    {
        $escopos = $this->vinculos->vigentesDoUsuario($this->usuario($request));

        return new JsonResponse([
            'data' => ContextoAtuacaoResource::collection($escopos)->resolve($request),
        ]);
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
