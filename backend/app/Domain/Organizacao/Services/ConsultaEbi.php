<?php

namespace App\Domain\Organizacao\Services;

use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Organizacao\Contracts\EbiDados;
use App\Domain\Organizacao\Models\Ebi;
use RuntimeException;

/**
 * Percorre a hierarquia via os models do proprio modulo (Ebi -> Igreja ->
 * vinculacao vigente -> Setor/Regional) e devolve dados de exibicao das EBIs
 * alcancadas por cada tipo de escopo, ordenadas por nome.
 */
class ConsultaEbi implements ConsultarEbi
{
    public function dados(string $ebiId): ?EbiDados
    {
        $ebi = Ebi::query()->with('igreja')->find($ebiId);

        return $ebi === null ? null : $this->paraDados($ebi);
    }

    public function listarPorEscopo(string $tipoEscopo, string $escopoId): array
    {
        $query = match ($tipoEscopo) {
            'central' => Ebi::query(),
            'regional' => Ebi::query()->whereHas('igreja.vinculacaoVigente.setor', fn ($q) => $q->where('regional_id', $escopoId)),
            'setor' => Ebi::query()->whereHas('igreja.vinculacaoVigente', fn ($q) => $q->where('setor_id', $escopoId)->whereNull('fim')),
            'igreja' => Ebi::query()->where('igreja_id', $escopoId),
            'ebi' => Ebi::query()->whereKey($escopoId),
            default => throw new RuntimeException("Tipo de escopo desconhecido: {$tipoEscopo}"),
        };

        return $query->with('igreja')
            ->orderBy('nome')
            ->get()
            ->map(fn (Ebi $ebi) => $this->paraDados($ebi))
            ->all();
    }

    public function todasAtivas(): array
    {
        return Ebi::query()->with('igreja')
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get()
            ->map(fn (Ebi $ebi) => $this->paraDados($ebi))
            ->all();
    }

    private function paraDados(Ebi $ebi): EbiDados
    {
        return new EbiDados(
            id: (string) $ebi->id,
            nome: (string) $ebi->nome,
            igrejaId: (string) $ebi->igreja_id,
            igrejaNome: (string) $ebi->igreja?->nome,
            timezone: (string) $ebi->igreja?->timezone,
            participantesPorAuxiliar: (int) $ebi->participantes_por_auxiliar,
        );
    }
}
