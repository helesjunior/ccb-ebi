<?php

namespace App\Domain\Sessoes\Contracts;

interface ConsultarPresenca
{
    /** @return array<int, PresencaRegistrada> */
    public function daSessao(string $sessaoId): array;

    public function ebiDaPresenca(string $presencaId): ?string;

    /** @return array<int, HistoricoPresenca> */
    public function doParticipante(string $participanteId, ?string $de = null, ?string $ate = null): array;
}
