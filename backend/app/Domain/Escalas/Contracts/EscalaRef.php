<?php

namespace App\Domain\Escalas\Contracts;

/**
 * Referencia minima de uma escala para a camada HTTP: o EBI dono (para
 * autorizar), a igreja dona (para decidir quem aprova/decide troca) e a
 * sessao. Nunca carrega modelo de modulo.
 */
final readonly class EscalaRef
{
    public function __construct(
        public string $id,
        public string $ebiId,
        public string $sessaoId,
        public string $igrejaId,
    ) {}
}
