<?php

namespace App\Domain\Sessoes\Contracts;

interface CancelarSessoesPrevistas
{
    public function naData(string $ebiId, string $dataIso, string $motivo, string $autorId): int;
}
