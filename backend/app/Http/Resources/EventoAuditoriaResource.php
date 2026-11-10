<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata um evento da trilha de auditoria. Nao importa o modelo do modulo
 * (fronteira modular); le os atributos do recurso subjacente.
 */
class EventoAuditoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'sequencia' => $this->resource->sequencia,
            'hash_anterior' => $this->resource->hash_anterior,
            'hash_encadeado' => $this->resource->hash_encadeado,
            'ator_id' => $this->resource->ator_id,
            'acao' => $this->resource->acao,
            'recurso' => $this->resource->recurso,
            'escopo' => $this->resource->escopo,
            'origem' => $this->resource->origem,
            'motivo' => $this->resource->motivo,
            'metadados_minimizados' => $this->resource->metadados_minimizados,
            'ocorrido_em' => $this->resource->ocorrido_em?->toIso8601String(),
        ];
    }
}
