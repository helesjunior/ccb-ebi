<?php

namespace App\Support\Participantes;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Services\VerificadorPropriedadeParticipante;
use App\Support\ProblemException;

/**
 * Autorizacao das escritas/leituras sobre um participante (H2/H3, CA-04).
 *
 * Combina duas vias, nesta ordem:
 * 1. **Propriedade** — o responsavel com vinculo `aprovada` e vigente acessa o
 *    **proprio dependente** (`VerificadorPropriedadeParticipante`).
 * 2. **Territorio** — um operador cujo papel permite a operacao e cujo escopo
 *    cobre a igreja de referencia do participante (resolvida a EBI quando ha
 *    uma, para o escopo da coordenadora casar com o da igreja).
 *
 * Sem nenhuma das duas: 403. Isso fecha o buraco em que qualquer autenticado
 * criava vinculo/excecao contra qualquer participante, e a leitura territorial
 * era pulada por falta de recurso-alvo.
 *
 * Vive em `app/Support` (fora dos modulos) porque precisa do `ContextoAtuacao`
 * (Servico de `Identidade`) — o modulo `Participantes` so pode importar
 * `Contracts` de outro modulo (ADR-0002).
 */
class AutorizacaoParticipante
{
    public function __construct(
        private readonly VerificadorPropriedadeParticipante $propriedade,
        private readonly AutorizarOperacao $autorizar,
        private readonly ConsultarHierarquia $hierarquia,
    ) {}

    /**
     * Exige propriedade **ou** a operacao territorial sobre a igreja/EBI do
     * participante. Lanca 403 quando nenhuma via se aplica.
     */
    public function exigir(
        ?ContextoAtuacao $contexto,
        string $usuarioId,
        Participante $participante,
        string $operacaoTerritorial,
    ): void {
        if (! $this->pode($contexto, $usuarioId, $participante, $operacaoTerritorial)) {
            throw new ProblemException(
                'acesso_negado',
                'Acesso negado',
                403,
                'Você não tem vínculo nem escopo sobre este participante.',
            );
        }
    }

    public function pode(
        ?ContextoAtuacao $contexto,
        string $usuarioId,
        Participante $participante,
        string $operacaoTerritorial,
    ): bool {
        if ($usuarioId !== '' && $this->propriedade->podeAcessar($usuarioId, (string) $participante->id)) {
            return true;
        }

        return $this->cobreTerritorio($contexto, $participante, $operacaoTerritorial);
    }

    /**
     * O escopo do ator cobre a igreja de referencia do participante? Resolve a
     * EBI da igreja quando existe (o escopo da coordenadora e a EBI), senao cai
     * na igreja. Sem igreja de referencia, nao ha escopo territorial derivavel.
     */
    public function cobreTerritorio(
        ?ContextoAtuacao $contexto,
        Participante $participante,
        string $operacaoTerritorial,
    ): bool {
        if (! $contexto instanceof ContextoAtuacao) {
            return false;
        }

        $igrejaId = $participante->igreja_referencia_id;

        if ($igrejaId === null || $igrejaId === '') {
            return false;
        }

        $ebiId = $this->hierarquia->ebiDaigreja((string) $igrejaId);

        [$tipoRecurso, $recursoId] = $ebiId !== null
            ? ['ebi', $ebiId]
            : ['igreja', (string) $igrejaId];

        return $this->autorizar->permite($contexto, $operacaoTerritorial, $tipoRecurso, $recursoId);
    }

    /**
     * A operacao territorial e permitida para o ator (sem exigir recurso)? Usado
     * quando o participante ainda nao tem igreja de referencia e nao ha vinculo
     * de propriedade (ex.: a propria coordenadora cadastrando o vinculo).
     */
    public function papelPermite(?ContextoAtuacao $contexto, string $operacaoTerritorial): bool
    {
        return $contexto instanceof ContextoAtuacao
            && $this->autorizar->permite($contexto, $operacaoTerritorial);
    }

    /**
     * Quem pode **abrir a solicitacao** de vinculo (RF-03)? O responsavel
     * autenticado (a solicitacao nasce `pendente` e NAO da acesso — quem decide
     * e a coordenadora) ou um operador com `vinculo.gerenciar` sobre a igreja do
     * participante. Conhecer a matricula nao basta (RF-03.4): a solicitacao so
     * vira acesso apos a aprovacao da coordenadora.
     */
    public function podeSolicitarVinculo(
        ?ContextoAtuacao $contexto,
        string $usuarioId,
        Participante $participante,
    ): bool {
        if ($usuarioId !== '' && $this->propriedade->podeAcessar($usuarioId, (string) $participante->id)) {
            return true;
        }

        if ($contexto?->papelCodigo === 'responsavel') {
            return true;
        }

        return $this->cobreTerritorio($contexto, $participante, 'vinculo.gerenciar');
    }
}
