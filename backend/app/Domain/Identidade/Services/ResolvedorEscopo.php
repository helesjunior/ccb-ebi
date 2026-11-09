<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;

/**
 * Implementacao do escopo por descendencia: o recurso esta coberto quando o no
 * do vinculo aparece entre os ascendentes do recurso (incluindo o proprio no).
 */
class ResolvedorEscopo implements ResolverEscopo
{
    public function __construct(private readonly ConsultarHierarquia $hierarquia) {}

    public function cobre(
        string $tipoEscopoVinculo,
        string $escopoIdVinculo,
        string $tipoRecurso,
        string $recursoId,
    ): bool {
        $ancestrais = $this->hierarquia->ancestrais($tipoRecurso, $recursoId);

        return ($ancestrais[$tipoEscopoVinculo] ?? null) === $escopoIdVinculo;
    }
}
