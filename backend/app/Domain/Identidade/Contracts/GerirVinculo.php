<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Identidade\Services\ContextoAtuacao;

/**
 * Concessoes diretas de acesso (RF-05): nomear (papel+escopo) e revogar. Toda
 * concessao/revogacao e auditada (RN-10) e preserva o historico por vigencia.
 */
interface GerirVinculo
{
    /**
     * Concede um vinculo vigente. Se ja existir vinculo vigente do mesmo par
     * (usuario + papel + escopo), encerra-o antes (fim = hoje).
     */
    public function conceder(
        ContextoAtuacao $contexto,
        string $usuarioId,
        string $papelCodigo,
        string $tipoEscopo,
        string $escopoId,
    ): VinculoAcesso;

    /**
     * Revoga o vinculo vigente (fim = hoje). Idempotente.
     */
    public function revogar(ContextoAtuacao $contexto, string $vinculoId): void;
}
