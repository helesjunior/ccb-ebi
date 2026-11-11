<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata um participante para a API. Nao importa o modelo do modulo
 * (fronteira modular, ADR-0002); le os atributos do recurso subjacente.
 */
class ParticipanteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'matricula' => $this->resource->matricula,
            'nome' => $this->resource->nome,
            'nome_social' => $this->resource->nome_social,
            'data_nascimento' => $this->resource->data_nascimento?->toDateString(),
            'status' => $this->resource->status,
            'origem_cadastro' => $this->resource->origem_cadastro,
            'igreja_referencia_id' => $this->resource->igreja_referencia_id,
        ];
    }
}
