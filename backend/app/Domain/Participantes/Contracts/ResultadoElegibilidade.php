<?php

namespace App\Domain\Participantes\Contracts;

/**
 * Resultado da checagem de elegibilidade etaria: permitido + motivo tecnico
 * (sem dado pessoal; o motivo e um codigo, nao uma frase com PII).
 */
final class ResultadoElegibilidade
{
    public function __construct(
        public readonly bool $permitido,
        public readonly string $motivo,
    ) {}

    public static function permitido(string $motivo = 'elegivel'): self
    {
        return new self(true, $motivo);
    }

    public static function bloqueado(string $motivo): self
    {
        return new self(false, $motivo);
    }
}
