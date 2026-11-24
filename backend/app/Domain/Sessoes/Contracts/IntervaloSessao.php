<?php

namespace App\Domain\Sessoes\Contracts;

use Carbon\CarbonImmutable;

/**
 * Intervalo previsto de uma sessao, no fuso da igreja dona do EBI. E a unidade
 * que o modulo de escalas usa para detectar conflito de horario (CA-12) e o
 * modulo de relatorios para o denominador do aviso de equipe minima (M-06).
 */
final readonly class IntervaloSessao
{
    public function __construct(
        public string $id,
        public string $ebiId,
        public string $igrejaId,
        public CarbonImmutable $inicioPrevisto,
        public CarbonImmutable $fimPrevisto,
        public string $status,
        public string $timezone,
        public string $dataLocal,
    ) {}
}
