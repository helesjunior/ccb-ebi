<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata uma manifestacao de consentimento para a API. Nao importa o modelo do
 * modulo (fronteira modular); le os atributos do recurso subjacente.
 */
class ManifestacaoPrivacidadeResource extends JsonResource
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
            'termo_versao_id' => $this->resource->termo_versao_id,
            'decisao' => $this->resource->decisao,
            'origem' => $this->resource->origem,
            'evidencia' => $this->resource->evidencia,
            'ocorrido_em' => $this->resource->ocorrido_em?->toIso8601String(),
            'termo_versao' => $this->termoVersao(),
        ];
    }

    /**
     * Versao e hash do texto aceito (CA-16), quando carregada.
     *
     * @return array<string, mixed>|null
     */
    private function termoVersao(): ?array
    {
        if (! $this->resource->relationLoaded('termoVersao')) {
            return null;
        }

        $termo = $this->resource->getRelation('termoVersao');

        if ($termo === null) {
            return null;
        }

        return [
            'id' => $termo->id,
            'finalidade' => $termo->finalidade,
            'base_legal' => $termo->base_legal,
            'versao' => $termo->versao,
            'texto_hash' => $termo->texto_hash,
            'texto' => $termo->texto,
        ];
    }
}
