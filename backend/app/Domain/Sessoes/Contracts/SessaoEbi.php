<?php

namespace App\Domain\Sessoes\Contracts;

final readonly class SessaoEbi
{
    public function __construct(
        public string $id,
        public string $ebiId,
        public string $ebiNome,
        public string $timezone,
        public string $igrejaId,
        public ?string $agendaEbiId,
        public string $inicioPrevisto,
        public string $fimPrevisto,
        public ?string $inicioReal,
        public ?string $fimReal,
        public ?string $tema,
        public ?string $passagemBiblica,
        public string $status,
        public ?bool $realizada,
        public ?string $motivoCancelamento,
        public ?int $auxiliaresEscalados,
        public int $presentes,
    ) {}
}
