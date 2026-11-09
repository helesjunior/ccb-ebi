<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Services\ContextoAtuacao;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolve o contexto de atuacao (papel + escopo) de uma requisicao a partir do
 * cabecalho `X-Acting-Scope`, revalidando contra os vinculos vigentes do
 * usuario (RN-11).
 *
 * Recebe `Authenticatable` (nao o modelo) para que `app/Http` nao precise
 * importar o modelo do modulo (ADR-0002); a implementacao valida que o usuario
 * e da identidade e lanca se nao for.
 */
interface ResolverContextoAtuacao
{
    /**
     * Devolve `null` quando o usuario nao possui nenhum vinculo vigente (cabe a
     * rota exigir contexto). Lanca 422 quando ha varios vinculos e o cabecalho
     * esta ausente; 403 quando o contexto pedido nao lhe pertence.
     */
    public function resolver(Authenticatable $usuario, ?string $header): ?ContextoAtuacao;
}
