<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata uma versao de termo de privacidade para a API. Nao importa o modelo
 * do modulo (fronteira modular); le os atributos do recurso subjacente.
 */
class TermoVersaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'finalidade' => $this->resource->finalidade,
            'base_legal' => $this->resource->base_legal,
            'versao' => $this->resource->versao,
            // O texto integral e exposto para o consentimento ser informado: a
            // pessoa precisa ler o termo antes de aceita-lo (RF-04, CA-16).
            'texto' => $this->resource->texto,
            'vigente_em' => $this->resource->vigente_em?->toIso8601String(),
        ];
    }
}
