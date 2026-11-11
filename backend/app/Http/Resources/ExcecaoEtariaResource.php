<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata uma excecao inclusiva. Nao importa o modelo do modulo (fronteira).
 */
class ExcecaoEtariaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'participante_id' => $this->resource->participante_id,
            'tipo_necessidade' => $this->resource->tipo_necessidade,
            'status' => $this->resource->status,
            'decidido_por' => $this->resource->decidido_por,
            'decidido_em' => $this->resource->decidido_em?->toIso8601String(),
            'vigente_ate' => $this->resource->vigente_ate?->toDateString(),
            'revogado_em' => $this->resource->revogado_em?->toIso8601String(),
            'motivo' => $this->resource->motivo,
        ];
    }
}
