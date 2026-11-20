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
    /** Papeis que veem a igreja no relatorio. */
    public const PAPEIS = ['coordenadora', 'adm_igreja', 'ministerio_local'];

    /**
     * Papeis que veem a lista de participantes na propria igreja. O auxiliar
     * tem `participante.ler` e atua na igreja, mas nao tem `relatorio.ler`.
     */
    public const PAPEIS_DA_LISTA = [...self::PAPEIS, 'auxiliar'];

    public static function veAIgreja(?ContextoAtuacao $contexto): bool
    {
        return self::ativo($contexto, self::PAPEIS);
    }

    public static function veAIgrejaNaLista(?ContextoAtuacao $contexto): bool
    {
        return self::ativo($contexto, self::PAPEIS_DA_LISTA);
    }

    public static function suprime(?ContextoAtuacao $contexto): bool
    {
        return ! self::veAIgreja($contexto);
    }

    public static function suprimeLista(?ContextoAtuacao $contexto): bool
    {
        return ! self::veAIgrejaNaLista($contexto);
    }

    /** @param  array<int, string>  $papeis */
    private static function ativo(?ContextoAtuacao $contexto, array $papeis): bool
    {
        return $contexto !== null
            && in_array($contexto->papelCodigo, $papeis, true)
            && in_array($contexto->tipoEscopo, ['igreja', 'ebi'], true);
    }
}
