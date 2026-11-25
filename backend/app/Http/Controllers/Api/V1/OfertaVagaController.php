<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\GerirVagaEscala;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Retirada da propria oferta (RF-07, evolucao). So o dono da oferta a retira;
 * o servico responde 404 para oferta alheia e 409 para oferta ja decidida.
 */
class OfertaVagaController
{
    public function __construct(private readonly GerirVagaEscala $gerir) {}

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->gerir->retirarOferta($id, (string) $request->user()?->getAuthIdentifier());

        return new JsonResponse(null, 204);
    }
}
