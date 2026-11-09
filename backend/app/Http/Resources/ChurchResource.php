<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata uma igreja para a API. Nao importa o modelo do modulo (fronteira
 * modular); o setor e lido da relacao vigente anexada pelo controller.
 */
class ChurchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $setor = $this->resource->setorVigente ?? null;

        return [
            'id' => $this->resource->id,
            'codigo' => $this->resource->codigo,
            'nome' => $this->resource->nome,
            'endereco' => $this->resource->endereco,
            'latitude' => $this->resource->latitude,
            'longitude' => $this->resource->longitude,
            'timezone' => $this->resource->timezone,
            'status' => $this->resource->status,
            'setor' => $setor === null ? null : [
                'id' => $setor->id,
                'codigo' => $setor->codigo,
                'nome' => $setor->nome,
                'regional_id' => $setor->regional_id,
            ],
        ];
    }
}
