<?php

namespace App\Domain\Sessoes\Contracts;

interface ConsultarPresenca
{
    /** @return array<int, PresencaRegistrada> */
    public function daSessao(string $sessaoId): array;

    public function ebiDaPresenca(string $presencaId): ?string;

    /** EBIs (ids distintos) onde o participante tem presenca `valida` (CA-27). @return array<int, string> */
    public function ebisComPresencaValida(string $participanteId): array;

    /** @return array<int, HistoricoPresenca> */
    public function doParticipante(string $participanteId, ?string $de = null, ?string $ate = null): array;
}
