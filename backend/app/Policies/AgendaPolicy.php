<?php

namespace App\Policies;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Autorizacao da agenda (RF-06): delega ao nucleo `AutorizarOperacao`
 * (RBAC + escopo territorial). O `ContextoAtuacao` vem da request (middleware
 * `X-Acting-Scope`).
 */
class AgendaPolicy
{
    public function __construct(
        private readonly AutorizarOperacao $autorizar,
        private readonly Request $request,
    ) {}

    public function gerenciar(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::AGENDA_GERENCIAR, $tipoRecurso, $recursoId);
    }

    private function decidir(string $operacao, ?string $tipoRecurso, ?string $recursoId): bool
    {
        $contexto = $this->request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao) {
            return false;
        }

        return $this->autorizar->permite($contexto, $operacao, $tipoRecurso, $recursoId);
    }
}
