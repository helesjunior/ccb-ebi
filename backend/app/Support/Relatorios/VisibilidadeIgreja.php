<?php

namespace App\Support\Relatorios;

use App\Domain\Identidade\Services\ContextoAtuacao;

/**
 * Quem ve a igreja nos relatorios e na busca de participantes (decisao 6).
 *
 * O tipo de escopo sozinho nao basta: vinculos incompatíveis sao aceitos
 * (por exemplo, um administrador regional com contexto de igreja), e sem o
 * papel um administrador escaparia do corte so por trocar o contexto.
 * A isencao exige papel local E escopo de igreja/EBI.
 */
final class VisibilidadeIgreja
{
    public const PAPEIS = ['coordenadora', 'adm_igreja', 'ministerio_local'];

    public static function veAIgreja(?ContextoAtuacao $contexto): bool
    {
        return $contexto !== null
            && in_array($contexto->papelCodigo, self::PAPEIS, true)
            && in_array($contexto->tipoEscopo, ['igreja', 'ebi'], true);
    }

    public static function suprime(?ContextoAtuacao $contexto): bool
    {
        return ! self::veAIgreja($contexto);
    }
}
