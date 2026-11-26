<?php

namespace App\Domain\Escalas\Contracts;

/**
 * Referencia minima de uma equipe para a camada HTTP: o EBI dono (para
 * autorizar), a igreja e a coordenadora dona (para decidir quem edita).
 * Nunca carrega modelo de modulo.
 */
final readonly class EquipeRef
{
    public function __construct(
        public string $id,
        public string $ebiId,
        public string $igrejaId,
        public string $coordenadoraId,
    ) {}
}
