<?php

namespace App\Domain\Sessoes\Contracts;

use Carbon\CarbonImmutable;

/**
 * Indicadores agregados de presenca e sessao para o modulo de relatorios
 * (E06, RF-14). Devolve apenas numeros e ids; nunca nomes (ADR-0029).
 */
interface ConsultarIndicadoresPresenca
{
    /**
     * Totais do periodo por igreja e/ou por EBI.
     *
     * A chave de cada entrada e `"igreja:{id}"` ou `"ebi:{id}"`; igrejas e EBIs
     * ausentes do periodo voltam com zeros.
     *
     * - `presencas` (M-03): presencas com status `valida`.
     * - `sessoes_realizadas` (M-05): sessoes `encerrada` com `realizada = true`,
     *   inclusive sem nenhuma presenca.
     * - `participantes_distintos` (M-02): `COUNT(DISTINCT)` sobre o id canonico
     *   (mapa duplicado -> principal, vindo do modulo Participantes). ids traz
     *   os ids canonicos, para quem precisa deduplicar entre varios escopos.
     *
     * O periodo e inclusivo nas duas pontas e recortado pela data local de
     * `inicio_previsto` no fuso da igreja (RN-16).
     *
     * @param  array<int, string>  $igrejaIds
     * @param  array<int, string>  $ebiIds
     * @param  array<string, string>  $canonico
     * @return array<string, array{participantes_distintos: int, presencas: int, sessoes_realizadas: int, ids: array<string, true>}>
     */
    public function totais(array $igrejaIds, array $ebiIds, string $inicio, string $fim, array $canonico = []): array;

    /**
     * Sessoes que ja deveriam ter acontecido: status `prevista`, `aberta` ou
     * `encerrada` e inicio previsto ate a data de geracao (inclusive), com data
     * local dentro do periodo. Base do denominador do M-06: sessao futura nao
     * entra.
     *
     * @param  array<int, string>  $igrejaIds
     * @param  array<int, string>  $ebiIds
     * @return array<int, array{id: string, chave: string, data_local: string}>
     */
    public function sessoesPrevistas(array $igrejaIds, array $ebiIds, string $inicio, string $fim, CarbonImmutable $geracao): array;

    /**
     * Fuso de cada igreja (`id => timezone`), para o recorte de periodo e para
     * declarar o fuso no relatorio.
     *
     * @param  array<int, string>  $igrejaIds
     * @return array<string, string>
     */
    public function fusos(array $igrejaIds): array;

    /**
     * Ids canonicos com pelo menos uma presenca valida na janela [desde, ate]
     * (base de "participante ativo", ADR-0020). `ate` e a data de geracao: uma
     * sessao no futuro nao torna ninguem ativo.
     *
     * @param  array<string, string>  $canonico
     * @return array<int, string>
     */
    public function participantesComPresencaDesde(array $canonico, CarbonImmutable $desde, ?CarbonImmutable $ate = null): array;

    /**
     * Ids das EBIs com pelo menos uma sessao realizada na janela [desde, ate]
     * (base de M-09, ADR-0020).
     *
     * @return array<int, string>
     */
    public function ebisComSessaoDesde(CarbonImmutable $desde, ?CarbonImmutable $ate = null): array;

    /**
     * Presenca valida por igreja: `igreja_id => [id canonico => true]`, na
     * janela [desde, ate] (base de "participante ativo", que cruza igrejas).
     *
     * @param  array<string, string>  $canonico
     * @return array<string, array<string, true>>
     */
    public function igrejasComPresencaDesde(array $canonico, CarbonImmutable $desde, ?CarbonImmutable $ate = null): array;

    /**
     * Presenca valida por EBI na janela [desde, ate]: ebi_id =>
     * [id canonico => true]. Base de M-02 no nivel EBI (o participante nao
     * se atribui a EBI por igreja de referencia).
     *
     * @param  array<string, string>  $canonico
     * @return array<string, array<string, true>>
     */
    public function ebisComPresencaDesde(array $canonico, CarbonImmutable $desde, ?CarbonImmutable $ate = null): array;
}
