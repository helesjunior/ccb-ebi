<?php

namespace App\Domain\Participantes\Contracts;

use App\Domain\Participantes\Models\Participante;

/**
 * Deduplicacao e mesclagem auditada e reversivel (CA-27, ADR-0007).
 *
 * Mesclar funde dois cadastros do mesmo participante (o duplicado vira
 * `mesclado`); desfazer restaura o estado anterior gravado no snapshot. Toda
 * acao e auditada.
 */
interface GerirMesclagem
{
    /**
     * Candidatos a duplicidade por nome normalizado + data de nascimento, no
     * escopo da igreja de referencia. Devolve apenas o sinal (nunca dados de
     * terceiros a quem nao pode ler).
     *
     * @param  array<string, mixed>  $dados  nome, data_nascimento, igreja_referencia_id?
     * @return array<int, Participante>
     */
    public function detectar(array $dados): array;

    /** Mescla `duplicado` no `principal`, guardando o snapshot para desfazer. */
    public function mesclar(string $atorId, string $principalId, string $duplicadoId): Participante;

    /** Desfaz a ultima mesclagem do participante, restaurando o snapshot. */
    public function desfazer(string $atorId, string $principalId): Participante;
}
