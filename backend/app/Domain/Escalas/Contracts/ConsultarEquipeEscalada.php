<?php

namespace App\Domain\Escalas\Contracts;

/**
 * Quantos auxiliares a escala aprovada da sessao define (RF-07). Null quando
 * nao ha escala ou ela nao esta `aprovada`: a sessao volta ao campo antigo.
 */
interface ConsultarEquipeEscalada
{
    public function auxiliaresDaEscalaAprovada(string $sessaoId): ?int;
}
