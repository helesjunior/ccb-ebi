<?php

namespace App\Domain\Sessoes\Contracts;

interface EstornarPresenca
{
    public function estornar(string $presencaId, string $motivo, string $usuarioId): PresencaRegistrada;
}
