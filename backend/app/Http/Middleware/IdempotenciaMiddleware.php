<?php

namespace App\Http\Middleware;

use App\Support\Idempotencia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica idempotencia de escrita via cabecalho `Idempotency-Key`.
 *
 * Roda ANTES da resolucao/validacao do controller (middleware de rota), de modo
 * que o replay de uma requisicao ja processada devolva a resposta gravada sem
 * reexecutar a validacao nem a acao (RF: escritas idempotentes).
 */
class IdempotenciaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return Idempotencia::executar($request, fn (): Response => $next($request));
    }
}
