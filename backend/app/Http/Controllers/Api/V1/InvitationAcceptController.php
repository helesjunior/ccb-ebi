<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\AceitarConvite;
use App\Http\Requests\Identidade\AceitarConviteRequest;
use Illuminate\Http\JsonResponse;

/**
 * Aceite de convite (rota pública): o token secreto do convite é a credencial.
 */
class InvitationAcceptController
{
    public function __construct(private readonly AceitarConvite $aceitarConvite) {}

    public function accept(AceitarConviteRequest $request, string $id): JsonResponse
    {
        $conta = $this->aceitarConvite->aceitar(
            $id,
            $request->string('token')->toString(),
            $request->string('nome')->toString(),
            $request->string('password')->toString(),
        );

        return new JsonResponse([
            'id' => $conta->id,
            'email_normalizado' => $conta->emailNormalizado,
            'nome' => $conta->nome,
        ], 201);
    }
}
