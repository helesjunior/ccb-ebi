<?php

namespace App\Domain\Sessoes\Contracts;

interface GerarSessoesPrevistas
{
    public function gerar(string $ebiId, string $de, string $ate): ResultadoGeracao;
}
