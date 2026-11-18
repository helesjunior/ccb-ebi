<?php

namespace App\Domain\Organizacao\Contracts;

/**
 * Dados de exibicao de uma EBI (resumo): identificadores opacos + nome,
 * igreja de origem e timezone. Nunca carrega modelos de Organizacao.
 */
final class EbiDados
{
    public function __construct(
        public readonly string $id,
        public readonly string $nome,
        public readonly string $igrejaId,
        public readonly string $igrejaNome,
        public readonly string $timezone,
        public readonly int $participantesPorAuxiliar,
    ) {}
}
