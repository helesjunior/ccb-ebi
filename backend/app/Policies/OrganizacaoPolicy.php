<?php

namespace App\Policies;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Superficie de autorizacao para a estrutura organizacional (Abordagem C):
 * delega ao nucleo `AutorizarOperacao`. A operacao e o recurso-alvo chegam
 * pelos parametros do `can:` (ex.: `can:organizacao.gerenciar,igreja,{id}`);
 * o `ContextoAtuacao` vem da request (middleware `X-Acting-Scope`).
 */
class OrganizacaoPolicy
{
    public function __construct(
        private readonly AutorizarOperacao $autorizar,
        private readonly Request $request,
    ) {}

    public function ler(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir('organizacao.ler', $tipoRecurso, $recursoId);
    }

    public function gerenciar(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir('organizacao.gerenciar', $tipoRecurso, $recursoId);
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
