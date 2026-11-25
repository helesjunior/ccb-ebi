<?php

namespace App\Domain\Escalas\Contracts;

interface ConsultarEquipe
{
    /** Referencia minima da equipe (dona, EBI e igreja), ou null se nao existe. */
    public function referencia(string $equipeId): ?EquipeRef;

    /**
     * Equipes do EBI com a dona, se ela ainda tem vinculo de coordenadora
     * vigente, e quantos membros cada uma tem, ordenadas pelo nome da dona.
     *
     * @return array<int, array{id: string, ebi_id: string, coordenadora_id: string, coordenadora_nome: string, coordenadora_sem_vinculo: bool, quantidade_membros: int}>
     */
    public function resumosDoEbi(string $ebiId, string $igrejaId): array;

    /**
     * Equipe com coordenadora e membros (membro sem vinculo vigente marcado
     * com `sem_vinculo`), ou null se nao existe.
     *
     * @return array<string, mixed>|null
     */
    public function detalhe(string $equipeId): ?array;
}
