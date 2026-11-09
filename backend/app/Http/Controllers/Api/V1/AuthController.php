<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\Autenticar;
use App\Http\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController
{
    public function __construct(private readonly Autenticar $autenticador) {}

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

        $token = $resultado->usuario->createToken('web')->plainTextToken;

        return new JsonResponse([
            'token' => $token,
            'usuario' => UsuarioResource::make($resultado->usuario)->resolve(),
        ], 201);
    }

    public function logout(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
