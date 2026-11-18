<?php

namespace App\Domain\Agenda\Contracts;

readonly class FaixaAgenda
{
    public function __construct(
        public string $id,
        public string $ebiId,
        public int $diaSemana,
        public string $horaInicio,
        public string $horaFim,
        public string $inicioVigencia,
        public ?string $fimVigencia,
        public bool $vigente,
    ) {}
}
