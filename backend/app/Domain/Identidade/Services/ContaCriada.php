<?php

namespace App\Domain\Identidade\Services;

/**
 * Resultado do aceite de convite (DTO): dados publicos da conta criada —
 * mantem `app/Http` livre do modelo (ADR-0002).
 */
class ContaCriada
{
    public function __construct(
        public readonly string $id,
        public readonly string $emailNormalizado,
        public readonly string $nome,
    ) {}
}
