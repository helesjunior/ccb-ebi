<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'codigo' => $this->resource->codigo,
            'nome' => $this->resource->nome,
            'central_nacional_id' => $this->resource->central_nacional_id,
            'status' => $this->resource->status,
        ];
    }
}
