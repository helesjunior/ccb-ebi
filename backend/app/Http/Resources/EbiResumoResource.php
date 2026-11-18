<?php

namespace App\Http\Resources;

use App\Domain\Organizacao\Contracts\EbiDados;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resumo de EBI para o seletor de contexto (`/me/ebis`): id, nome, igreja e
 * timezone. Le apenas o DTO opaco devolvido pelo contrato do modulo.
 *
 * @property EbiDados $resource
 */
class EbiResumoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'nome' => $this->resource->nome,
            'igreja_id' => $this->resource->igrejaId,
            'igreja_nome' => $this->resource->igrejaNome,
            'timezone' => $this->resource->timezone,
        ];
    }
}
