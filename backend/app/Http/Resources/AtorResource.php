<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata o ator autenticado para a API. Não importa o modelo do módulo
 * (fronteira modular); lê os atributos do recurso subjacente.
 */
class AtorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'nome' => $this->resource->nome,
            'email' => $this->resource->email,
            'papel' => $this->resource->papel,
        ];
    }
}
