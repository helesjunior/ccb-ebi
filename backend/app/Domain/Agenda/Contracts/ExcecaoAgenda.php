<?php

namespace App\Domain\Agenda\Contracts;

readonly class ExcecaoAgenda
{
    public function __construct(
        public string $id,
        public string $ebiId,
        public ?string $agendaEbiId,
        public string $data,
        public string $tipo,
        public ?string $horaInicio,
        public ?string $horaFim,
        public string $motivo,
    ) {}
}
