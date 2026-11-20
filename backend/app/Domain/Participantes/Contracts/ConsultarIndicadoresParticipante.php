<?php

namespace App\Domain\Participantes\Contracts;

use Carbon\CarbonImmutable;

/**
 * Contagens e mapa de mesclagem usados pelo modulo de relatorios (E06).
 * Nunca devolve nomes (ADR-0029).
 */
interface ConsultarIndicadoresParticipante
{
    /**
     * Mapa `id => id final` das mesclagens, resolvido transitivamente.
     * Ciclos sao desempatados pelo menor id do componente. Quem nao foi
     * mesclado fica de fora (vale por si mesmo).
     *
     * @return array<string, string>
     */
    public function mapaCanonico(): array;

    /**
     * Cadastrados (`status = ativo`) por igreja de referencia. Participantes
     * sem igreja de referencia ficam de fora.
     *
     * @return array<string, int>
     */
    public function cadastradosPorIgreja(): array;

    /**
     * Ids canonicos de participantes `ativo` que atendem ao menos um criterio
     * de participante ativo (ADR-0020): presenca valida recente ou cadastro
     * recente. `$presencaIds` ja vem canonicizado pela consulta de presenca.
     *
     * @param  array<string, string>  $canonico
     * @param  array<int, string>  $presencaIds
     * @return array<int, string>
     */
    public function idsAtivos(array $canonico, CarbonImmutable $desdeCadastro, array $presencaIds): array;

    /**
     * Mapa `id => igreja_referencia_id` dos participantes cujo id canonico esta
     * em `$ids`; os demais ficam de fora.
     *
     * @param  array<string, string>  $canonico
     * @param  array<int, string>  $ids
     * @return array<string, string>
     */
    public function igrejaPorParticipante(array $canonico, array $ids): array;
}
