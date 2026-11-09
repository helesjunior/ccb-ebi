<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Services\ContextoAtuacao;

/**
 * Nucleo da autorizacao por operacao (CA-03): combina RBAC (papel x operacao,
 * derivado da matriz) com ABAC territorial (o recurso esta na subarvore do no
 * do vinculo). `super_admin` ignora o escopo territorial (bypass explicito).
 */
interface AutorizarOperacao
{
    /**
     * @param  string  $operacao  Codigo de `Operacoes`.
     * @param  string|null  $tipoRecurso  Tipo do recurso-alvo (ex.: igreja) ou null quando a operacao nao e sobre recurso.
     * @param  string|null  $recursoId  ID do recurso-alvo.
     */
    public function permite(
        ContextoAtuacao $contexto,
        string $operacao,
        ?string $tipoRecurso = null,
        ?string $recursoId = null,
    ): bool;
}
