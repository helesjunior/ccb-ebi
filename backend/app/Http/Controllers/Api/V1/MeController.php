<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\ConsultarVinculos;
use App\Domain\Identidade\Contracts\ExigirMfa;
use App\Domain\Identidade\Contracts\GerirPerfil;
use App\Http\Requests\Identidade\PerfilAtualizarRequest;
use App\Http\Requests\Identidade\PerfilSenhaRequest;
use App\Http\Resources\ContextoAtuacaoResource;
use App\Http\Resources\UsuarioResource;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function alterarSenha(PerfilSenhaRequest $request): Response
    {
        $this->gerirPerfil->alterarSenha(
            $this->usuario($request),
            $request->string('senha_atual')->toString(),
            $request->string('nova_senha')->toString(),
        );

        return response()->noContent();
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
