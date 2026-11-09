<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\GerenciarMfa;
use App\Http\Requests\Identidade\MfaConfirmarRequest;
use App\Http\Resources\UsuarioResource;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaController
{
    public function __construct(private readonly GerenciarMfa $mfa) {}

    /**
     * Segundo fator do login: exige o token de desafio (`mfa:pendente`),
     * valida o codigo e, no sucesso, revoga o desafio e emite a sessao plena.
     */
    public function verify(Request $request): JsonResponse
    {
        $dados = $request->validate(['codigo' => ['required', 'string', 'max:64']]);

        $usuario = $this->usuario($request);
        $token = $this->mfa->validarEConcluir($usuario, $dados['codigo']);

        return new JsonResponse([
            'token' => $token,
            'usuario' => UsuarioResource::make($usuario)->resolve(),
        ], 201);
    }

    /**
     * Inicia a inscricao de TOTP (autenticado).
     */
    public function iniciarTotp(Request $request): JsonResponse
    {
        return new JsonResponse($this->mfa->iniciarInscricao($this->usuario($request)), 200);
    }

    /**
     * Confirma a inscricao de TOTP (autenticado).
     */
    public function confirmarTotp(MfaConfirmarRequest $request): JsonResponse
    {
        $this->mfa->confirmar($this->usuario($request), $request->string('codigo')->toString());

        return new JsonResponse(['confirmado' => true], 200);
    }

    /**
     * Gera codigos de recuperacao (autenticado); devolvidos em claro uma vez.
     */
    public function gerarRecuperacao(Request $request): JsonResponse
    {
        return new JsonResponse([
            'codigos' => $this->mfa->gerarCodigosRecuperacao($this->usuario($request)),
        ], 200);
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
