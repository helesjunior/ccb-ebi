<?php

namespace App\Domain\Auditoria\Contracts;

use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Leitura consultavel da trilha de auditoria (RF-16). O chamador informa as
 * primitivas do contexto de atuacao (papel e no do vinculo); o filtro
 * territorial e revalidado aqui, evento a evento, contra o escopo textual.
 */
interface ConsultarAuditoria
{
    /**
     * @param  array<string, mixed>  $filtros  Filtros aceitos: recurso, ator_id, de, ate.
     * @return CursorPaginator<int, mixed>
     */
    public function listar(
        string $papelCodigo,
        string $tipoEscopoVinculo,
        string $escopoIdVinculo,
        array $filtros,
        int $limit,
    ): CursorPaginator;
}
