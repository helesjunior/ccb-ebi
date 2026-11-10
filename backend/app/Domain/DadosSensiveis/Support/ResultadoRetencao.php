<?php

namespace App\Domain\DadosSensiveis\Support;

/**
 * Relatório de uma passagem do motor de retenção.
 *
 * @property-read int $candidatos  Registros vencidos encontrados (expiraveis).
 * @property-read int $expurgados  Registros efetivamente descartados/anonimizados.
 * @property-read bool $executado  Se a rotina realmente escreveu (false = dry-run).
 */
readonly class ResultadoRetencao
{
    public function __construct(
        public int $candidatos,
        public int $expurgados,
        public bool $executado,
    ) {}
}
