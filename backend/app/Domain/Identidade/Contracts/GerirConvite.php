<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Models\Convite;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Support\ProblemException;

/**
 * Convites para a equipe (RF-05): criar, listar e cancelar. O convidante so
 * convida para papel/escopo dentro da sua propria autoridade.
 */
interface GerirConvite
{
    /**
     * @throws ProblemException 403 quando o escopo/papel excede a autoridade do convidante.
     */
    public function criar(
        ContextoAtuacao $contexto,
        string $email,
        string $papelCodigo,
        string $tipoEscopo,
        string $escopoId,
        string $convidanteId,
    ): Convite;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listar(ContextoAtuacao $contexto): array;

    /**
     * Cancela (soft) o convite. Idempotente.
     */
    public function cancelar(ContextoAtuacao $contexto, string $id): void;
}
