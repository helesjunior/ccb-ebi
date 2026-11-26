<?php

namespace App\Http\Support;

use App\Domain\Escalas\Contracts\EscalaVisao;
use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
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

    /**
     * O leitor alcanca este EBI com a operacao, sem lancar 403. A visao de
     * leitura de um cartao de vaga nao pode recusar quem so ve a propria oferta.
     */
    public function alcanca(Request $request, string $operacao, string $ebiId): bool
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        return $contexto instanceof ContextoAtuacao
            && $this->autorizar->permite($contexto, $operacao)
            && $this->autorizar->permite($contexto, $operacao, 'ebi', $ebiId);
    }

    /**
     * Visao de leitura do conflito (CA-12): alem do EBI dono da escala, quais
     * outros EBIs o leitor alcanca com `escala.ler`. A propria pessoa ve o
     * proprio conflito mesmo fora deles.
     *
     * @param  array<int, string>  $outrosEbis  EBIs de sessoes em conflito
     */
    public function visao(Request $request, array $outrosEbis, string $ebiId): EscalaVisao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao) {
            return new EscalaVisao;
        }

        $visiveis = [];
        foreach (array_unique($outrosEbis) as $ebiId) {
            if ($this->autorizar->permite($contexto, Operacoes::ESCALA_LER, 'ebi', $ebiId)) {
                $visiveis[] = $ebiId;
            }
        }

        return new EscalaVisao(
            $contexto->usuarioId,
            $visiveis,
            contexto: $this->contexto($request),
            // Membros da escala entram por excecao de propriedade, sem
            // `escala.ler`: o que eles veem da vaga muda com isso.
            temEscalaLer: $this->autorizar->permite($contexto, Operacoes::ESCALA_LER, 'ebi', $ebiId),
        );
    }

    /**
     * Contexto de atuacao da requisicao em primitivos, para o dominio. Sem
     * contexto resolvido devolve um vazio, que nunca satisfaz nenhuma regra de
     * papel (a autorizacao ja recusou antes).
     */
    public function contexto(Request $request): ContextoDecisao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao) {
            return new ContextoDecisao('', '', '', '');
        }

        return new ContextoDecisao(
            (string) $contexto->usuarioId,
            $contexto->papelCodigo,
            $contexto->tipoEscopo,
            $contexto->escopoId,
        );
    }
}
