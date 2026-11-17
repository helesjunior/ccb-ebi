<?php

namespace App\Domain\Participantes\Contracts;

interface ConsultarParticipante
{
    /** @return array<int, ParticipanteResumo> */
    public function buscar(string $termo, int $limite = 20): array;

    public function encontrar(string $participanteId): ?ParticipanteResumo;
}
