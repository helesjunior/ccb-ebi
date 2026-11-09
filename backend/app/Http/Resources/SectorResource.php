<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectorResource extends JsonResource
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
            'regional_id' => $this->resource->regional_id,
            'status' => $this->resource->status,
        ];
    }
}
