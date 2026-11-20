<?php

namespace App\Policies;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Autorizacao do relatorio agregado (E06, `relatorio.ler`).
 *
 * `ler` responde so pelo papel: a separacao entre 403 e 404 do no vem da
 * checagem de escopo do `ReportController` (decisao 3 da spec), porque o
 * nucleo precisa do recurso ja carregado para distinguir "inexistente" de
 * "fora do territorio".
 */
class RelatorioPolicy
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

        return $this->autorizar->permite($contexto, Operacoes::RELATORIO_LER);
    }
}
