<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contexto de atuacao exposto ao portal: papel + no do escopo de um vinculo
 * vigente. Alimenta o seletor de contexto (`/me/scopes`). Nao expoe tokens nem
 * segredos — le apenas o array devolvido pelo contrato do modulo.
 */
class ContextoAtuacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'papel' => $this->resource['papel'],
            'tipo_escopo' => $this->resource['tipo_escopo'],
            'escopo_id' => $this->resource['escopo_id'],
        ];
    }
}
