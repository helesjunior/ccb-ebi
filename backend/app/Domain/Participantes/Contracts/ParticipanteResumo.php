<?php

namespace App\Domain\Participantes\Contracts;

final readonly class ParticipanteResumo
{
    public function __construct(
        public string $id,
        public string $nome,
        public string $matricula,
        public ?string $igrejaReferenciaId,
    ) {}
}
