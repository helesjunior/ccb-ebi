<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

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
            'papeis' => $this->papeis(),
            'mfa_ativo' => (bool) ($this->resource->mfa_ativo ?? false),
        ];
    }

    /**
     * Codigos dos papeis dos vinculos vigentes do usuario (quando carregados).
     *
     * @return array<int, string>
     */
    private function papeis(): array
    {
        $colecao = $this->resource->relationLoaded('vinculosVigentes')
            ? $this->resource->getRelation('vinculosVigentes')
            : null;

        if (! $colecao instanceof Collection) {
            return [];
        }

        return $colecao
            ->map(fn ($vinculo) => $vinculo->papel?->codigo)
            ->filter()
            ->values()
            ->all();
    }
}
