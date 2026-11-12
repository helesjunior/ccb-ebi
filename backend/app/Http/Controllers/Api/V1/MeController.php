<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\ConsultarVinculos;
use App\Domain\Identidade\Contracts\ExigirMfa;
use App\Domain\Identidade\Contracts\GerirPerfil;
use App\Domain\Identidade\Models\Usuario;
use App\Http\Requests\Identidade\PerfilAtualizarRequest;
use App\Http\Resources\ContextoAtuacaoResource;
use App\Http\Resources\UsuarioResource;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController
{
    public function __construct(
        private readonly ConsultarVinculos $vinculos,
        private readonly ExigirMfa $mfa,
        private readonly GerirPerfil $gerirPerfil,
    ) {}

    public function me(Request $request): UsuarioResource
    {
        $usuario = $this->usuario($request);
        $usuario->loadMissing('vinculosVigentes.papel');
        $usuario->setAttribute('mfa_ativo', $this->mfa->temFatorValidado($usuario));

        return UsuarioResource::make($usuario);
    }

    public function atualizarPerfil(PerfilAtualizarRequest $request): UsuarioResource
    {
        return UsuarioResource::make(
            $this->gerirPerfil->atualizar($this->usuario($request), $request->validated()),
        );
    }

    public function scopes(Request $request): JsonResponse
    {
        $escopos = $this->vinculos->vigentesDoUsuario($this->usuario($request));

        return new JsonResponse([
            'data' => ContextoAtuacaoResource::collection($escopos)->resolve($request),
        ]);
    }

    private function usuario(Request $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw new ProblemException('unauthorized', 'Não autenticado', 401);
        }

        return $usuario;
    }
}
