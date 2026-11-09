<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Padroniza a paginacao por cursor das listagens da API.
 *
 * O consumidor envia `?cursor=&limit=`; `limit` e limitado a LIMITE_MAX.
 */
class PaginacaoCursor
{
    public const LIMITE_PADRAO = 20;

    public const LIMITE_MAX = 100;

    public static function limit(Request $request): int
    {
        $bruto = $request->query('limit');

        if (! is_numeric($bruto)) {
            return self::LIMITE_PADRAO;
        }

        $valor = (int) $bruto;

        if ($valor < 1) {
            return self::LIMITE_PADRAO;
        }

        return min($valor, self::LIMITE_MAX);
    }

    /**
     * @param  Builder<*>  $query
     * @return array{data: array<int, mixed>, meta: array{proximo_cursor: ?string}}
     */
    public static function resposta(Builder $query, Request $request): array
    {
        $pagina = $query
            ->orderBy('id')
            ->cursorPaginate(self::limit($request));

        return [
            'data' => $pagina->items(),
            'meta' => [
                'proximo_cursor' => $pagina->nextCursor()?->encode(),
            ],
        ];
    }

    /**
     * @param  Builder<*>  $query
     * @return CursorPaginator<int, mixed>
     */
    public static function paginar(Builder $query, Request $request): CursorPaginator
    {
        return $query
            ->orderBy('id')
            ->cursorPaginate(self::limit($request));
    }
}
