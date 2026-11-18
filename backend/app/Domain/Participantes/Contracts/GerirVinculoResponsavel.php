<?php

namespace App\Domain\Participantes\Contracts;

use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;

/**
 * Superficie publica dos vinculos responsavel↔participante (RF-03).
 *
 * `solicitar` pede o vinculo (fica pendente ate a conferencia da coordenadora);
 * `decidir` aprova/recusa/pede documento; `revogar`/`contestar` encerram. Toda
 * escrita mantem `responsavel_participante` (situacao corrente) e
 * `solicitacao_vinculo` (log append-only) coerentes.
 */
interface GerirVinculoResponsavel
{
    /**
     * @param  array<string, mixed>  $dados  nome, tipo_relacao, telefone?
     */
    public function solicitar(string $usuarioId, string $participanteId, array $dados): ResponsavelParticipante;

    /**
     * O autor do pedido não pode decidir o próprio vínculo, em nenhuma decisão
     * (segregação de funções); nesse caso lança 403 `acesso_negado`.
     *
     * @param  string  $decisao  aprovar | recusar | pedir_documento | revogar | contestar
     */
    public function decidir(string $coordenadoraId, string $vinculoId, string $decisao, ?string $motivo = null): ResponsavelParticipante;

    /**
     * Participantes que o usuario pode acessar por propriedade (dependentes com
     * vinculo aprovado).
     *
     * @return array<int, Participante>
     */
    public function dependentesDe(string $usuarioId): array;

    public function encontrar(string $vinculoId): ?ResponsavelParticipante;
}
