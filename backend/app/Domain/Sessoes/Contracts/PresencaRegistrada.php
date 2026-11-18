<?php

namespace App\Domain\Sessoes\Contracts;

final readonly class PresencaRegistrada
{
    public function __construct(
        public string $id,
        public string $sessaoId,
        public string $participanteId,
        public ?string $participanteNome,
        public ?string $matricula,
        public string $registradoPor,
        public string $registradoEm,
        public string $origem,
        public string $status,
        public ?string $estornadoEm,
        public ?string $motivoEstorno,
    ) {}
}
