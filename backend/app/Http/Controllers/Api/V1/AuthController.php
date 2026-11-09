<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Http\Resources\AtorResource;
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

        $ator = $this->autenticador->autenticar($dados['email'], $dados['password']);

        $token = $ator->createToken('web')->plainTextToken;

        return new JsonResponse([
            'token' => $token,
            'ator' => AtorResource::make($ator)->resolve(),
        ], 201);
    }

    public function logout(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
