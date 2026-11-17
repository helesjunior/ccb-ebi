<?php

namespace App\Domain\Organizacao\Contracts;

use RuntimeException;

/**
 * Superficie publica de leitura das EBIs para outros modulos (ex.: Sessoes,
 * para resolver o seletor de contexto e o alcance territorial).
 *
 * Devolve apenas identificadores opacos e dados de exibicao — nunca modelos.
 */
interface ConsultarEbi
{
    /**
     * Dados de uma EBI pelo id, ou null quando nao existir.
     */
    public function dados(string $ebiId): ?EbiDados;

    /**
     * EBIs alcancadas por um no da hierarquia
     * (central|regional|setor|igreja|ebi). A ligacao igreja->setor/regional vem
     * da vinculacao vigente (`fim IS NULL`).
     *
     * @return array<int, EbiDados>
     *
     * @throws RuntimeException se o tipo de escopo for desconhecido.
     */
    public function listarPorEscopo(string $tipoEscopo, string $escopoId): array;

    /**
     * Todas as EBIs ativas (status `ativo`), para o escopo central.
     *
     * @return array<int, EbiDados>
     */
    public function todasAtivas(): array;
}
