<?php

namespace App\Domain\Participantes\Contracts;

interface VerificarPropriedadeParticipante
{
    public function podeAcessar(string $usuarioId, string $participanteId): bool;
}
