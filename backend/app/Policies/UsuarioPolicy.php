<?php

namespace App\Policies;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Superficie de autorizacao para usuarios/vinculos (equipe do EBI). Delega ao
 * nucleo `AutorizarOperacao` com o `ContextoAtuacao` da request.
 */
class UsuarioPolicy
{
    public function __construct(
        private readonly AutorizarOperacao $autorizar,
        private readonly Request $request,
    ) {}

    public function ler(?Authenticatable $usuario): bool
    {
        return $this->decidir(Operacoes::USUARIO_LER);
    }

    public function gerenciar(?Authenticatable $usuario): bool
    {
        return $this->decidir(Operacoes::USUARIO_GERENCIAR);
    }

    private function decidir(string $operacao): bool
    {
        $contexto = $this->request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao) {
            return false;
        }

        return $this->autorizar->permite($contexto, $operacao);
    }
}
