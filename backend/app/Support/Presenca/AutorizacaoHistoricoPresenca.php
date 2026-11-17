<?php

namespace App\Support\Presenca;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Participantes\Contracts\VerificarPropriedadeParticipante;
use App\Support\ProblemException;

class AutorizacaoHistoricoPresenca
{
    public function __construct(
        private readonly AutorizarOperacao $autorizar,
        private readonly ConsultarHierarquia $hierarquia,
        private readonly VerificarPropriedadeParticipante $propriedade,
    ) {}

    public function exigirOperador(?ContextoAtuacao $contexto, string $participanteId, ?string $igrejaReferenciaId): void
    {
        if ($contexto !== null) {
            if ($igrejaReferenciaId === null && $contexto->papelCodigo === 'super_admin') {
                return;
            }
            if ($igrejaReferenciaId !== null) {
                $ebiId = $this->hierarquia->ebiDaigreja($igrejaReferenciaId);
                if ($this->autorizar->permite($contexto, Operacoes::PRESENCA_LER, $ebiId === null ? 'igreja' : 'ebi', $ebiId ?? $igrejaReferenciaId)) {
                    return;
                }
            }
        }

        throw new ProblemException('acesso_negado', 'Acesso negado', 403);
    }

    public function exigirResponsavel(string $usuarioId, string $participanteId): void
    {
        if (! $this->propriedade->podeAcessar($usuarioId, $participanteId)) {
            throw new ProblemException('acesso_negado', 'Acesso negado', 403);
        }
    }
}
