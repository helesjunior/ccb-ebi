<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EbiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'nome' => $this->resource->nome,
            'igreja_id' => $this->resource->igreja_id,
            'participantes_por_auxiliar' => $this->resource->participantes_por_auxiliar,
            'status' => $this->resource->status,
        ];
    }
}
