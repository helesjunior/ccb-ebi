<?php

namespace App\Domain\Relatorios\Contracts;

/**
 * Relatorio de presenca agregado (E06, RF-14). Nao devolve nomes de pessoas e
 * aplica a supressao do ADR-0020, inclusive a cascata (decisao 6a da spec).
 */
interface ConsultarRelatorio
{
    /**
     * Payload de `data` de `GET /reports/attendance`, sem wrapper HTTP.
     *
     * `$suprimir` e decidido pelo chamador (papel + tipo de escopo do
     * contexto). `$hoje` e a data de geracao no fuso do no.
     *
     * @return array{
     *     no: array{nivel: string, id: string, nome: string},
     *     periodo: array{inicio: string, fim: string},
     *     fuso: string,
     *     gerado_em: string,
     *     criterios: string,
     *     totais: array<string, array{valor: int|float|null, suprimido: bool, motivo: ?string}>,
     *     filhos: array<int, array{nivel: string, id: string, nome: string, totais: array<string, array{valor: int|float|null, suprimido: bool, motivo: ?string}>}>
     * }
     */
    public function presenca(string $tipo, string $id, string $inicio, string $fim, bool $suprimir): array;
}
