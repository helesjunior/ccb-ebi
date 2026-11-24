<?php

namespace App\Domain\Escalas\Contracts;

/**
 * Cobertura da escala para o M-06 (RF-07 + relatorios): quais sessoes tem
 * escala aprovada com a equipe minima confirmada e vigente na data.
 */
interface ConsultarCoberturaEscala
{
    /**
     * @param  array<int, string>  $sessaoIds
     * @return array<string, true>
     */
    public function cobertas(array $sessaoIds): array;
}
