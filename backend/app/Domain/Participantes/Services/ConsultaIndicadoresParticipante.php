<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Participantes\Contracts\ConsultarIndicadoresParticipante;
use App\Domain\Participantes\Models\Participante;
use Carbon\CarbonImmutable;

class ConsultaIndicadoresParticipante implements ConsultarIndicadoresParticipante
{
    public function mapaCanonico(): array
    {
        $bruto = [];

        Participante::query()
            // O snapshot fica no principal; sem filtro de status porque o
            // where gerado para JSON falha no SQLite dos testes.
            ->whereNotNull('snapshot_mesclagem')
            ->select(['id', 'snapshot_mesclagem'])
            ->get()
            ->each(function (Participante $mesclado) use (&$bruto): void {
                $snapshot = $mesclado->snapshot_mesclagem ?? [];
                $duplicado = (string) ($snapshot['duplicado']['id'] ?? $mesclado->id);
                // O snapshot guarda o id do duplicado; o principal e o proprio
                // registro que carrega o snapshot.
                $principal = (string) ($snapshot['principal']['id'] ?? $mesclado->id);

                if ($duplicado === '' || $duplicado === $principal) {
                    return;
                }

                $bruto[$duplicado] = $principal;
            });

        // Cadeia e ciclo sao resolvidos por caminhamento; um principal que tambem
        // foi mesclado depois aponta para o fim da cadeia.
        $mapa = [];
        foreach (array_keys($bruto) as $id) {
            $mapa[$id] = $this->resolver($id, $bruto);
        }

        return $mapa;
    }

    public function cadastradosPorIgreja(): array
    {
        return Participante::query()
            ->where('status', Participante::STATUS_ATIVO)
            ->whereNotNull('igreja_referencia_id')
            ->selectRaw('igreja_referencia_id, COUNT(*) as total')
            ->groupBy('igreja_referencia_id')
            ->pluck('total', 'igreja_referencia_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    public function idsAtivos(array $canonico, CarbonImmutable $desdeCadastro, array $presencaIds): array
    {
        $ids = [];

        Participante::query()
            ->where('status', Participante::STATUS_ATIVO)
            ->where(fn ($q) => $q
                ->where('created_at', '>=', $desdeCadastro->utc()->format('Y-m-d H:i:s'))
                ->orWhereIn('id', $presencaIds))
            ->select(['id'])
            ->chunkById(500, function ($ativos) use (&$ids, $canonico): void {
                foreach ($ativos as $participante) {
                    $ids[$canonico[$participante->id] ?? $participante->id] = true;
                }
            });

        return array_keys($ids);
    }

    public function igrejaPorParticipante(array $canonico, array $ids): array
    {
        $porIgreja = [];
        if ($ids === []) {
            return $porIgreja;
        }

        Participante::query()
            ->whereIn('id', $ids)
            ->whereNotNull('igreja_referencia_id')
            ->select(['id', 'igreja_referencia_id'])
            ->chunkById(500, function ($participantes) use (&$porIgreja, $canonico): void {
                foreach ($participantes as $participante) {
                    $principal = $canonico[$participante->id] ?? $participante->id;
                    $porIgreja[$principal] = $participante->igreja_referencia_id;
                }
            });

        return $porIgreja;
    }

    /**
     * Segue a cadeia de mesclagem ate o fim. Ciclo: devolve o menor id do
     * componente visitado.
     *
     * @param  array<string, string>  $bruto
     */
    private function resolver(string $id, array $bruto): string
    {
        // $id nao entra em $visitados: o proprio no que carrega o snapshot
        // nao aponta para si (o mapa so le a cadeia dos duplicados).
        $visitados = [];
        $atual = $id;

        while (isset($bruto[$atual])) {
            $atual = $bruto[$atual];
            if (isset($visitados[$atual])) {
                return min(array_keys($visitados));
            }
            $visitados[$atual] = true;
        }

        return $atual;
    }
}
