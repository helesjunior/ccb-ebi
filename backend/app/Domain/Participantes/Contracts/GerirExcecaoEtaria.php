<?php

namespace App\Domain\Participantes\Contracts;

use App\Domain\Participantes\Models\ExcecaoEtaria;

/**
 * Superficie publica da excecao inclusiva (FL-06, ADR-0012). O responsavel
 * solicita; a coordenadora decide (aprovar/recusar/suspender/revogar). A
 * excecao nunca libera o bloqueio de <3 anos (CA-19).
 */
interface GerirExcecaoEtaria
{
    /**
     * @param  array<string, mixed>  $dados  tipo_necessidade, apoios_necessarios?, justificativa_funcional?
     */
    public function solicitar(string $solicitanteId, string $participanteId, array $dados, ?string $escopo = null): ExcecaoEtaria;

    public function encontrar(string $excecaoId): ?ExcecaoEtaria;

    /**
     * @param  string  $decisao  aprovar | recusar | suspender | revogar
     * @param  array<string, mixed>  $dados  vigente_ate?, revisao_prevista_em?, responsavel_concordante_id?, motivo?
     */
    public function decidir(string $decidorId, string $excecaoId, string $decisao, array $dados = [], ?string $escopo = null): ExcecaoEtaria;
}
