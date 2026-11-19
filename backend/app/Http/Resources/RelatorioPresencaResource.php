<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relatorio de presenca agregado (`GET /reports/attendance`). Repassa o payload
 * do dominio `Relatorios` sem nomes de pessoas; cada indicador chega como
 * `{valor, suprimido, motivo}`.
 */
class RelatorioPresencaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'no' => $this->resource['no'],
            'periodo' => $this->resource['periodo'],
            'fuso' => $this->resource['fuso'],
            'gerado_em' => $this->resource['gerado_em'],
            'criterios' => $this->resource['criterios'],
            'totais' => $this->resource['totais'],
            'filhos' => $this->resource['filhos'],
        ];
    }
}
