<?php

namespace App\Http\Support;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Support\ProblemException;
use Illuminate\Http\Request;

/**
 * Autorizacao dos endpoints de escala. Diferente do `Gate` (que so responde
 * permitido/negado), distingue o motivo: sem papel (403) de recurso fora do
 * alcance ou inexistente (404), sem revelar a existencia do EBI.
 */
class AutorizacaoEscala
{
    public function __construct(private readonly AutorizarOperacao $autorizar) {}

    public function garantir(Request $request, string $operacao, ?string $ebiId): ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao || ! $this->autorizar->permite($contexto, $operacao)) {
            throw new ProblemException('sem_permissao', 'Acesso negado', 403, 'Você não tem permissão para esta operação.');
        }

        if ($ebiId === null || ! $this->autorizar->permite($contexto, $operacao, 'ebi', $ebiId)) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'EBI não encontrada.');
        }

        return $contexto;
    }
}
