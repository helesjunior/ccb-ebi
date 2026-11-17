<?php

namespace App\Domain\Sessoes\Contracts;

final readonly class ResultadoPresenca
{
    public function __construct(
        public PresencaRegistrada $presenca,
        public bool $nova,
        public ?string $aviso,
    ) {}
}
