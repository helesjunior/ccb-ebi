<?php

namespace App\Domain\Participantes\Contracts;

use App\Domain\Participantes\Models\Participante;
use DateTimeInterface;

interface AvaliarElegibilidade
{
    public function avaliarPorId(string $participanteId, DateTimeInterface $dataSessao, string $fusoIgreja): ResultadoElegibilidade;

    /**
     * Data local (Y-m-d) do 12o aniversario quando ela cai em [hoje, hoje + $dias]
     * no fuso dado, o participante esta ativo e nao ha excecao aprovada vigente
     * nessa data. Senao, null.
     */
    public function aviso12Anos(Participante $participante, DateTimeInterface $agora, string $fuso, int $dias = 30): ?string;

    /**
     * Participantes ativos com igreja_referencia_id = $igrejaId e aviso nao nulo,
     * ordenados por data e nome.
     *
     * @return array<int, array{participante: Participante, faz_12_em: string}>
     */
    public function aviso12AnosDaIgreja(string $igrejaId, DateTimeInterface $agora, string $fuso, int $dias = 30): array;
}
