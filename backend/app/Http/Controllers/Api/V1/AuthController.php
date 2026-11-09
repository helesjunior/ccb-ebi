<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\Autenticar;
use App\Domain\Identidade\Contracts\GerenciarSessao;
use App\Http\Resources\UsuarioResource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class AuthController
{
    public function __construct(
        private readonly Autenticar $autenticador,
        private readonly GerenciarSessao $sessoes,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $resultado = $this->autenticador->executar($dados['email'], $dados['password']);

        if ($resultado->mfaPendente) {
            return new JsonResponse([
                'mfa_required' => true,
                'token_desafio' => $resultado->tokenDesafio,
            ], 200);
        }

        $token = $this->abrirSessao($request, $resultado->usuario);

        return new JsonResponse([
            'token' => $token,
            'usuario' => UsuarioResource::make($resultado->usuario)->resolve(),
        ], 201);
    }

    public function logout(Request $request): Response
    {
        $usuario = $request->user();
        $token = $usuario?->currentAccessToken();

        if ($usuario !== null && $token !== null) {
            // Marca a sessao como revogada (some da listagem) e invalida o token.
            $this->sessoes->revogarPorToken($usuario, $token->getKey());
        }

        return response()->noContent();
    }

    /** Abre a sessao de dispositivo e devolve o token Sanctum de sessao plena. */
    private function abrirSessao(Request $request, Authenticatable $usuario): string
    {
        $dispositivo = Str::limit((string) $request->userAgent(), 255, '') ?: 'Desconhecido';

        return $this->sessoes->abrir($usuario, $dispositivo);
    }
}
