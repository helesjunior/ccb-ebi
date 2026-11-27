<?php

namespace App\Support\Participantes;

use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;

/**
 * Escopo gravado nos eventos de auditoria de participante (CA8): o contexto de
 * quem atua ou o territorio da crianca. Vive em `app/Support` pelo mesmo motivo
 * de `AutorizacaoParticipante` (usa `ContextoAtuacao`, servico de Identidade).
 */
final class EscopoAuditoria
{
    public function __construct(
        private readonly ConsultarHierarquia $hierarquia,
        private readonly ConsultarOrganizacao $organizacao,
    ) {}

    /** "tipo:id" do contexto de atuacao; null sem contexto. */
    public static function doContexto(?ContextoAtuacao $contexto): ?string
    {
        return $contexto === null ? null : "{$contexto->tipoEscopo}:{$contexto->escopoId}";
    }

    /**
     * Territorio do participante: ebi:<id> se a igreja tem EBI, igreja:<id> se
     * nao tem, null sem igreja ou com igreja inexistente (cadastro legado).
     */
    public function daIgreja(?string $igrejaId): ?string
    {
        if ($igrejaId === null || $igrejaId === '') {
            return null;
        }
        $ebiId = $this->hierarquia->ebiDaigreja($igrejaId);
        if ($ebiId !== null) {
            return "ebi:{$ebiId}";
        }

        return $this->organizacao->nomes('igreja', [$igrejaId]) === [] ? null : "igreja:{$igrejaId}";
    }
}
