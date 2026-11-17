<?php

namespace App\Domain\Agenda\Contracts;

readonly class OcorrenciaAgenda
{
    public function __construct(
        public ?string $agendaEbiId,
        public string $ebiId,
        public string $data,
        public string $horaInicio,
        public string $horaFim,
    ) {}
}
