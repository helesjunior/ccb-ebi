<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata um vinculo responsavel↔participante. Nao importa o modelo do modulo
 * (fronteira modular, ADR-0002).
 */
class ResponsavelParticipanteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'usuario_id' => $this->resource->usuario_id,
            'participante_id' => $this->resource->participante_id,
            'nome' => $this->resource->nome,
            'tipo_relacao' => $this->resource->tipo_relacao,
            'status' => $this->resource->status,
            'validacao' => $this->resource->validacao,
            'conferido_em' => $this->resource->conferido_em?->toIso8601String(),
            'inicio' => $this->resource->inicio?->toDateString(),
            'fim' => $this->resource->fim?->toDateString(),
        ];
    }
}
