<?php

namespace App\Http\Middleware;

use App\Domain\Identidade\Contracts\ResolverContextoAtuacao;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o contexto de atuacao a partir do cabecalho `X-Acting-Scope` e o
 * injeta na request (`contexto_atuacao`). Revalida contra os vinculos vigentes
 * do usuario (o cabecalho nunca e aceito sem revalidacao — RN-11, CA-03).
 *
 * Roda no grupo `api`. Resolve o usuario pelo guard `sanctum` diretamente, de
 * modo que funciona mesmo antes do middleware `auth:sanctum` da rota. Rotas
 * publicas (sem token) passam direto.
 */
class ResolveActingScope
{
    public function __construct(private readonly ResolverContextoAtuacao $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user('sanctum');

        if ($usuario !== null) {
            $contexto = $this->resolver->resolver(
                $usuario,
                $request->header('X-Acting-Scope'),
            );

            if ($contexto !== null) {
                $request->attributes->set('contexto_atuacao', $contexto);
            }
        }

        return $next($request);
    }
}
