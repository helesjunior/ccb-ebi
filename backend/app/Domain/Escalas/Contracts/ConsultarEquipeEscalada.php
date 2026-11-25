<?php

namespace App\Domain\Escalas\Contracts;

/**
 * Quantos auxiliares a escala aprovada da sessao define (RF-07). Null quando
 * nao ha escala ou ela nao esta `aprovada`: a sessao volta ao campo antigo.
 */
interface ConsultarEquipeEscalada
{
    public function auxiliaresDaEscalaAprovada(string $sessaoId): ?int;

    /** Id da escala da sessao, de qualquer status; null se a sessao nao tem escala. */
    public function escalaDaSessao(string $sessaoId): ?string;
}
