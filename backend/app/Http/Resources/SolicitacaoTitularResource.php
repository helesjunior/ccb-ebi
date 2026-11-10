<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata uma solicitacao de direito do titular para a API. Nao importa o modelo
 * do modulo (fronteira modular); le os atributos do recurso subjacente.
 */
class SolicitacaoTitularResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'protocolo' => $this->resource->protocolo,
            'tipo' => $this->resource->tipo,
            'status' => $this->resource->status,
        ];
    }
}
