<?php

namespace App\Domain\Participantes\Contracts;

use DateTimeInterface;

interface AvaliarElegibilidade
{
    public function avaliarPorId(string $participanteId, DateTimeInterface $dataSessao, string $fusoIgreja): ResultadoElegibilidade;
}
