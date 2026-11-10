<?php

namespace App\Policies;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Superficie de autorizacao para leitura de consentimentos. Operation-level: a
 * subarvore do titular e verificada no controller (que tem acesso a
 * `ConsultarUsuarios`), mantendo a policy igual as demais do repositorio.
 */
class ConsentimentoPolicy
{
    public function __construct(
        private readonly AutorizarOperacao $autorizar,
        private readonly Request $request,
    ) {}

    public function ler(?Authenticatable $usuario): bool
    {
        $contexto = $this->request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao) {
            return false;
        }

        return $this->autorizar->permite($contexto, Operacoes::CONSENTIMENTO_LER);
    }
}
