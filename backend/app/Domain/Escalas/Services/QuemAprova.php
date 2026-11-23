<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Contracts\ContextoDecisao;

/**
 * Quem aprova a escala (RF-07) e quem decide a troca. Enquanto houver
 * coordenadora chefe vigente no EBI, so ela, atuando no escopo do proprio EBI,
 * aprova. Sem chefe, a vaga de decisao passa ao `adm_igreja` da igreja dona.
 *
 * Na troca a regra e a mesma, mas quem pediu nao decide: se o pedido veio da
 * chefe, a decisao vai para o `adm_igreja`.
 */
class QuemAprova
{
    public function __construct(private readonly ConsultarEquipeEbi $equipe) {}

    public function podeAprovarEscala(ContextoDecisao $contexto, string $ebiId, string $igrejaId): bool
    {
        $chefe = $this->equipe->chefeVigente($ebiId);

        if ($chefe !== null) {
            return $this->ehChefeNoEbi($contexto, $ebiId) && $contexto->usuarioId === $chefe;
        }

        return $this->ehAdmIgreja($contexto, $igrejaId);
    }

    public function podeDecidirTroca(ContextoDecisao $contexto, string $ebiId, string $igrejaId, string $solicitanteId): bool
    {
        $chefe = $this->equipe->chefeVigente($ebiId);

        if ($chefe !== null && $solicitanteId === $chefe) {
            return $this->ehAdmIgreja($contexto, $igrejaId);
        }

        return $this->podeAprovarEscala($contexto, $ebiId, $igrejaId);
    }

    private function ehChefeNoEbi(ContextoDecisao $contexto, string $ebiId): bool
    {
        return $contexto->papelCodigo === 'coordenadora_chefe'
            && $contexto->tipoEscopo === 'ebi'
            && $contexto->escopoId === $ebiId;
    }

    private function ehAdmIgreja(ContextoDecisao $contexto, string $igrejaId): bool
    {
        return $contexto->papelCodigo === 'adm_igreja'
            && $contexto->tipoEscopo === 'igreja'
            && $contexto->escopoId === $igrejaId;
    }
}
