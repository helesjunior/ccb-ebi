<?php

namespace App\Domain\Agenda\Contracts;

interface ConsultarAgenda
{
    /** @return array<int, OcorrenciaAgenda> */
    public function ocorrenciasDoDia(string $ebiId, string $dataIso): array;
}
