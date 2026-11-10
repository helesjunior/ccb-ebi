<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\RegistrarConta;
use App\Domain\Identidade\Contracts\VerificarEmail;
use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Http\Requests\Identidade\RegistrarRequest;
use App\Http\Resources\UsuarioResource;
use App\Support\EvidenciaAceite;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RegisterController
{
    public function __construct(
        private readonly RegistrarConta $registrar,
        private readonly VerificarEmail $verificarEmail,
        private readonly GerirTermos $termos,
    ) {}

    public function register(RegistrarRequest $request): JsonResponse
    {
        $termoVersaoId = $request->string('termo_versao_id')->toString();

        // A regra exists: do request ja garante a existencia; o fallback cobre corrida.
        $termo = $this->termos->encontrar($termoVersaoId)
            ?? throw new ProblemException(
                'termo_indisponivel',
                'Termo indisponível',
                422,
                'A versão de termo informada não está disponível.',
            );

        $usuario = $this->registrar->registrar(
            $request->string('nome')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $termoVersaoId,
            'proprio',
            EvidenciaAceite::deRequest($request, hash('sha256', $termo->texto)),
        );

        return new JsonResponse(UsuarioResource::make($usuario)->resolve(), 201);
    }

    public function verify(Request $request): Response
    {
        $request->validate([
            'usuario' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        if (! $request->hasValidSignature()) {
            throw new ProblemException('assinatura_invalida', 'Link inválido', 403, 'Assinatura inválida ou expirada.');
        }

        $this->verificarEmail->verificar($request->string('usuario')->toString());

        return response()->noContent();
    }

    public function resend(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Resposta constante: nunca revela se a conta existe.
        $this->verificarEmail->reenviar($request->string('email')->toString());

        return new JsonResponse(['mensagem' => 'Se o e-mail existir, um novo link foi enviado.'], 202);
    }
}
