<?php

namespace App\Domain\Auditoria\Contracts;

use App\Domain\Auditoria\Models\EventoAuditoria;

interface RegistrarEvento
{
    /**
     * Grava um evento na trilha append-only, calculando o hash encadeado.
     *
     * @param  array{acao: string, recurso: string, ator_id?: string|null, origem: string, escopo?: string|null, motivo?: string|null, metadados_minimizados?: array<string, mixed>|null, ocorrido_em?: \DateTimeInterface|null}  $evento
     */
    public function registrar(array $evento): EventoAuditoria;

    /**
     * Recalcula a cadeia inteira e lança se algum elo não fechar.
     */
    public function verificarIntegridade(): void;
}
