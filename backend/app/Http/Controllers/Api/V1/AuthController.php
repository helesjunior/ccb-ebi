<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Http\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController
{
    public function __construct(private readonly AutenticarAtor $autenticador) {}

    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = $this->autenticador->autenticar($dados['email'], $dados['password']);

        $token = $usuario->createToken('web')->plainTextToken;

        return new JsonResponse([
            'token' => $token,
            'usuario' => UsuarioResource::make($usuario)->resolve(),
        ], 201);
    }

    public function logout(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
