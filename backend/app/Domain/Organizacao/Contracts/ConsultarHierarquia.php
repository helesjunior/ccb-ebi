<?php

namespace App\Domain\Organizacao\Contracts;

use RuntimeException;

/**
 * Superficie publica de leitura da hierarquia organizacional para outros
 * modulos (ex.: Identidade, para decidir escopo por descendencia).
 *
 * Devolve apenas identificadores opacos e o tipo do nivel — nunca modelos.
 */
interface ConsultarHierarquia
{
    /**
     * Mapa `tipo => id` do no e de todos os seus ascendentes.
     *
     * Ex.: `ancestrais('igreja', $id)` devolve
     * `['igreja' => id, 'setor' => id, 'regional' => id, 'central' => id]`.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException se o no nao existir.
     */
    public function ancestrais(string $tipo, string $id): array;

    /**
     * ID da Central Nacional (unica). Devolve null quando ainda nao existe.
     * Superficie opaca (so o id) para outros modulos (ADR-0002).
     */
    public function centralId(): ?string;

    /**
     * ID da Central Nacional, criando-a se ainda nao existir (idempotente).
     * Usado no cadastro publico, que nao pode depender do seed organizacional.
     */
    public function garantirCentralId(): string;
}
