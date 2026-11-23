<?php

namespace App\Domain\Identidade\Contracts;

/**
 * Contexto de atuacao em forma de primitivos, para modulos que precisam saber
 * quem esta agindo (papel + escopo) sem importar os tipos internos de
 * Identidade. A camada HTTP monta a partir do `ContextoAtuacao` resolvido.
 */
final readonly class ContextoDecisao
{
    public function __construct(
        public string $usuarioId,
        public string $papelCodigo,
        public string $tipoEscopo,
        public string $escopoId,
    ) {}
}
