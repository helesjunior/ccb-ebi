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
     * ID da EBI sediada na igreja, ou null quando a igreja nao tem EBI. Usado
     * para resolver o escopo de um participante (igreja de referencia -> EBI)
     * quando o ator atua no nivel da EBI (E03).
     */
    public function ebiDaigreja(string $igrejaId): ?string;

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

    /**
     * Filhos diretos de um no, na ordem de exibicao. Sempre no formato
     * `['tipo' => string, 'id' => string, 'nome' => string]`; vazio para `ebi`.
     *
     * @return array<int, array{tipo: string, id: string, nome: string}>
     *
     * @throws RuntimeException se o tipo de no nao existir.
     */
    public function filhos(string $tipo, string $id): array;

    /**
     * IDs das igrejas sob o no (o proprio, quando `tipo = igreja`), pelo
     * vinculo vigente da igreja (RN-09). Vazio para `ebi`.
     *
     * @return array<int, string>
     */
    public function igrejasDaSubarvore(string $tipo, string $id): array;

    /**
     * IDs das EBIs sob o no; para `tipo = ebi`, a propria EBI.
     *
     * @return array<int, string>
     */
    public function ebisDaSubarvore(string $tipo, string $id): array;
}
