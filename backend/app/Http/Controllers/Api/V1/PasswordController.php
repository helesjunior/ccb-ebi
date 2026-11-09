<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\RecuperarSenha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PasswordController
{
    public function __construct(private readonly RecuperarSenha $recuperarSenha) {}

    /**
     * Solicita a redefinicao. Resposta identica (202) exista ou nao o e-mail.
     */
    public function forgot(Request $request): Response
    {
        $dados = $request->validate(['email' => ['required', 'email']]);

        $this->recuperarSenha->solicitar($dados['email']);

        return response()->noContent(202);
    }

    public function reset(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        $this->recuperarSenha->redefinir($dados['email'], $dados['token'], $dados['password']);

        return new JsonResponse(['redefinida' => true], 200);
    }
}
