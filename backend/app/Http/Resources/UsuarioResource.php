<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata o usuario autenticado para a API. Não importa o modelo do módulo
 * (fronteira modular); lê os atributos do recurso subjacente.
 */
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'nome' => $this->resource->nome,
            'email' => $this->resource->email_normalizado,
            'email_verificado' => $this->resource->email_verificado_em !== null,
            'status' => $this->resource->status,
        ];
    }
}
