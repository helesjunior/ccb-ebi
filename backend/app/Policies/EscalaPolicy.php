<?php

namespace App\Policies;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Autorizacao das escalas (RF-07): delega ao nucleo `AutorizarOperacao`
 * (RBAC + escopo territorial), como `SessaoPolicy`. Quem aprova e decide troca
 * alem do papel e decidido no servico (`QuemAprova`), porque depende da
 * existencia de coordenadora chefe vigente no EBI.
 */
class EscalaPolicy
{
    public function __construct(
        private readonly AutorizarOperacao $autorizar,
        private readonly Request $request,
    ) {}

    public function ler(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::ESCALA_LER, $tipoRecurso, $recursoId);
    }

    public function gerenciar(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::ESCALA_GERENCIAR, $tipoRecurso, $recursoId);
    }

    public function aprovar(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::ESCALA_APROVAR, $tipoRecurso, $recursoId);
    }

    public function decidirTroca(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::TROCA_DECIDIR, $tipoRecurso, $recursoId);
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
