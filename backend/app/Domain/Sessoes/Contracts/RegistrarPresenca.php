<?php

namespace App\Domain\Sessoes\Contracts;

interface RegistrarPresenca
{
    public function registrar(string $sessaoId, string $participanteId, string $usuarioId, string $origem = 'web'): ResultadoPresenca;

    public function registrarContingencia(string $sessaoId, string $participanteId, string $motivo, string $usuarioId): ResultadoPresenca;
}
