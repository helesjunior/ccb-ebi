<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Contracts\EquipeRef;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Support\ProblemException;

/**
 * Quem pode mexer na equipe de uma coordenadora. A equipe e dela: so a dona
 * cria a propria equipe e muda o proprio quadro. A chefe e o adm_igreja
 * (*supervisao* de qualquer escala) criam a equipe de qualquer coordenadora,
 * mas nao mexem nos membros de uma equipe existente.
 */
class PoliticaEquipe
{
    private const SUPERVISAO = ['coordenadora_chefe', 'adm_igreja'];

    public function podeCriarPara(ContextoDecisao $contexto, string $coordenadoraAlvo): bool
    {
        return $contexto->usuarioId === $coordenadoraAlvo
            || in_array($contexto->papelCodigo, self::SUPERVISAO, true);
    }

    public function ehDona(ContextoDecisao $contexto, EquipeRef $equipe): bool
    {
        return $contexto->usuarioId === $equipe->coordenadoraId;
    }

    public function garantirPodeCriar(ContextoDecisao $contexto, string $coordenadoraAlvo): void
    {
        if (! $this->podeCriarPara($contexto, $coordenadoraAlvo)) {
            throw new ProblemException(
                'nao_e_dona_da_equipe',
                'Equipe de outra coordenadora',
                403,
                'Somente a propria coordenadora cria a sua equipe.',
            );
        }
    }

    public function garantirDona(ContextoDecisao $contexto, EquipeRef $equipe): void
    {
        if (! $this->ehDona($contexto, $equipe)) {
            throw new ProblemException(
                'nao_e_dona_da_equipe',
                'Equipe de outra coordenadora',
                403,
                'Somente a dona da equipe inclui e retira membros.',
            );
        }
    }
}
