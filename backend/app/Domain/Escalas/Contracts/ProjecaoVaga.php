<?php

namespace App\Domain\Escalas\Contracts;

/**
 * O quanto da vaga o leitor enxerga (decisao 9 da spec). A camada HTTP
 * decide a projecao com o contexto de atuacao e a entrega pronta ao dominio.
 */
enum ProjecaoVaga
{
    /** Cartao publico (`/me`): so a propria oferta e os proprios conflitos. */
    case Publica;

    /** Leitor com `escala.ler` sem gerir: contagem de ofertas e quem foi escolhido. */
    case Leitor;

    /** Dona e supervisao (`pode_gerir`): nomes de quem se ofereceu. */
    case Gestao;
}
