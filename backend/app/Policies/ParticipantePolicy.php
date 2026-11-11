<?php

namespace App\Policies;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Autorizacao dos participantes (RF-02, RF-03): delega ao nucleo
 * `AutorizarOperacao` (RBAC + escopo territorial). O `ContextoAtuacao` vem da
 * request (middleware `X-Acting-Scope`).
 *
 * O caminho do responsavel (dependentes) e por **propriedade**, nao por
 * territorio: nao passa por aqui — vive em `VerificadorPropriedadeParticipante`
 * e nas rotas `/me/participants` (CA-04).
 */
class ParticipantePolicy
{
    public function __construct(
        private readonly AutorizarOperacao $autorizar,
        private readonly Request $request,
    ) {}

    public function ler(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::PARTICIPANTE_LER, $tipoRecurso, $recursoId);
    }

    public function cadastrar(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::PARTICIPANTE_CADASTRAR, $tipoRecurso, $recursoId);
    }

    public function corrigir(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::PARTICIPANTE_CORRIGIR, $tipoRecurso, $recursoId);
    }

    public function mesclar(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::PARTICIPANTE_MESCLAR, $tipoRecurso, $recursoId);
    }

    public function gerenciarVinculo(?Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool
    {
        return $this->decidir(Operacoes::VINCULO_GERENCIAR, $tipoRecurso, $recursoId);
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
