<?php

namespace App\Domain\Organizacao\Services;

use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use RuntimeException;

/**
 * Percorre a hierarquia Central -> Regional -> Setor -> Igreja -> EBI usando
 * os modelos do proprio modulo e devolve o mapa `tipo => id` do no e de seus
 * ascendentes. A ligacao igreja->setor vem da vinculacao vigente (`fim IS NULL`).
 */
class ConsultaHierarquia implements ConsultarHierarquia
{
    public function ancestrais(string $tipo, string $id): array
    {
        return match ($tipo) {
            'central' => $this->central($id),
            'regional' => $this->regional($id),
            'setor' => $this->setor($id),
            'igreja' => $this->igreja($id),
            'ebi' => $this->ebi($id),
            default => throw new RuntimeException("Tipo de escopo desconhecido: {$tipo}"),
        };
    }

    /** @return array<string, string> */
    private function central(string $id): array
    {
        $central = CentralNacional::query()->find($id);

        if ($central === null) {
            throw new RuntimeException("Central nao encontrada: {$id}");
        }

        return ['central' => $central->id];
    }

    /** @return array<string, string> */
    private function regional(string $id): array
    {
        $regional = Regional::query()->find($id);

        if ($regional === null) {
            throw new RuntimeException("Regional nao encontrada: {$id}");
        }

        return ['regional' => $regional->id, 'central' => $regional->central_nacional_id];
    }

    /** @return array<string, string> */
    private function setor(string $id): array
    {
        $setor = Setor::query()->with('regional')->find($id);

        if ($setor === null || $setor->regional === null) {
            throw new RuntimeException("Setor nao encontrado: {$id}");
        }

        return [
            'setor' => $setor->id,
            'regional' => $setor->regional->id,
            'central' => $setor->regional->central_nacional_id,
        ];
    }

    /** @return array<string, string> */
    private function igreja(string $id): array
    {
        $igreja = Igreja::query()->with('vinculacaoVigente.setor.regional')->find($id);

        if ($igreja === null) {
            throw new RuntimeException("Igreja nao encontrada: {$id}");
        }

        $ancestrais = ['igreja' => $igreja->id];

        $setor = $igreja->vinculacaoVigente?->setor;
        if ($setor !== null) {
            $ancestrais['setor'] = $setor->id;

            if ($setor->regional !== null) {
                $ancestrais['regional'] = $setor->regional->id;
                $ancestrais['central'] = $setor->regional->central_nacional_id;
            }
        }

        return $ancestrais;
    }

    /** @return array<string, string> */
    private function ebi(string $id): array
    {
        $ebi = Ebi::query()->find($id);

        if ($ebi === null) {
            throw new RuntimeException("EBI nao encontrada: {$id}");
        }

        return ['ebi' => $ebi->id] + $this->igreja($ebi->igreja_id);
    }
}
