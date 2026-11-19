<?php

namespace App\Domain\Organizacao\Contracts;

use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;

/**
 * Superficie publica de consulta da Organizacao.
 *
 * Servicos de outros modulos resolvem territorio (setor/regional de uma igreja)
 * por aqui, nunca lendo as tabelas ou models de Organizacao diretamente (ADR-0002).
 */
interface ConsultarOrganizacao
{
    /**
     * Setor vigente de uma igreja (vinculo com `fim IS NULL`).
     * Devolve null quando a igreja ainda nao possui vinculo.
     */
    public function setorVigente(string $igrejaId): ?Setor;

    /**
     * Regional a que pertence um setor.
     */
    public function regionalDoSetor(string $setorId): ?Regional;

    /**
     * Mapa `id => nome` das igrejas indicadas (dados de exibicao).
     *
     * @param  array<int, string>  $igrejaIds
     * @return array<string, string>
     */
    public function nomesDeIgrejas(array $igrejaIds): array;

    /**
     * Mapa `id => nome` de nos de um tipo (`central|regional|setor|igreja`).
     * Ids inexistentes ficam de fora.
     *
     * @param  array<int, string>  $ids
     * @return array<string, string>
     */
    public function nomes(string $tipo, array $ids): array;

    /**
     * Mapa `ebi_id => igreja_id` das EBIs indicadas.
     *
     * @param  array<int, string>  $ebiIds
     * @return array<string, string>
     */
    public function igrejaDosEbis(array $ebiIds): array;

    /**
     * Mapa `igreja_id => timezone`, usado para recortar o periodo no fuso da
     * igreja (RN-16). Igrejas de fuso unico no piloto devolvem um unico valor.
     *
     * @param  array<int, string>  $igrejaIds
     * @return array<string, string>
     */
    public function fusosDasIgrejas(array $igrejaIds): array;
}
