<?php

namespace App\Domain\Sessoes\Contracts;

final readonly class HistoricoPresenca
{
    public function __construct(
        public string $id,
        public string $sessaoId,
        public string $ebiId,
        public string $ebiNome,
        public string $inicioPrevisto,
        public string $status,
        public string $registradoEm,
    ) {}
}
