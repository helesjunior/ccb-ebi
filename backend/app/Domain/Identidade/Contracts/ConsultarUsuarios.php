<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Services\ContextoAtuacao;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Leitura da equipe (RF-05): lista usuarios do escopo de atuacao (horizontal e
 * vertical respeitados — so quem tem vinculo vigente na subarvore do no).
 */
interface ConsultarUsuarios
{
    /**
     * @param  array<string, mixed>  $filtros  Filtros aceitos: status.
     * @return CursorPaginator<int, mixed>
     */
    public function listar(ContextoAtuacao $contexto, array $filtros, int $limit): CursorPaginator;

    /**
     * Usuario dentro do escopo de atuacao, ou null.
     */
    public function encontrar(ContextoAtuacao $contexto, string $id): ?object;
}
