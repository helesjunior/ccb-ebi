<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata um vinculo responsavel↔participante. Nao importa o modelo do modulo
 * (fronteira modular, ADR-0002).
 */
class ResponsavelParticipanteResource extends JsonResource
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
            'participante' => $this->participanteResumo(),
            'nome' => $this->resource->nome,
            'tipo_relacao' => $this->resource->tipo_relacao,
            'status' => $this->resource->status,
            'validacao' => $this->resource->validacao,
            'conferido_em' => $this->resource->conferido_em?->toIso8601String(),
            'inicio' => $this->resource->inicio?->toDateString(),
            'fim' => $this->resource->fim?->toDateString(),
        ];
    }

    /**
     * Resumo do participante do vinculo, quando a relacao foi carregada.
     *
     * @return array{id: string, nome: string, matricula: string}|null
     */
    private function participanteResumo(): ?array
    {
        if (! $this->resource->relationLoaded('participante') || $this->resource->participante === null) {
            return null;
        }

        return [
            'id' => (string) $this->resource->participante->id,
            'nome' => (string) $this->resource->participante->nome,
            'matricula' => (string) $this->resource->participante->matricula,
        ];
    }
}
