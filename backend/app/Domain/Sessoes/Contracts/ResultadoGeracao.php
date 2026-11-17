<?php

namespace App\Domain\Sessoes\Contracts;

readonly class ResultadoGeracao
{
    public function __construct(
        public int $criadas,
        public int $ignoradas,
        public string $de,
        public string $ate,
    ) {}
}
