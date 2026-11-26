<?php

namespace App\Domain\Escalas\Contracts;

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Identidade\Contracts\ContextoDecisao;

interface GerirEquipe
{
    /**
     * Cria a equipe de uma coordenadora do EBI. Quem cria e decidido pela
     * `PoliticaEquipe` (a propria coordenadora, ou chefe/adm_igreja). Devolve a
     * equipe com `detalhe` montado, como a leitura a devolve.
     */
    public function criar(string $ebiId, string $coordenadoraId, ContextoDecisao $contexto, ?string $autorId): Equipe;

    /** Devolve a equipe com `detalhe` montado, como a leitura a devolve. */
    public function incluirMembro(string $equipeId, string $usuarioId, ContextoDecisao $contexto, ?string $autorId): Equipe;

    /** Devolve a equipe com `detalhe` montado, como a leitura a devolve. */
    public function retirarMembro(string $equipeId, string $usuarioId, ContextoDecisao $contexto, ?string $autorId): Equipe;
}
