<?php

namespace App\Domain\Identidade\Services;

/**
 * Contexto de atuacao resolvido para a requisicao: o papel exercido e o no do
 * escopo. E por requisicao (nao pode viver em singleton mutavel — Octane).
 */
class ContextoAtuacao
{
    public function __construct(
        public readonly string $papelCodigo,
        public readonly string $tipoEscopo,
        public readonly string $escopoId,
        public readonly ?string $usuarioId = null,
    ) {}
}
