<?php

namespace App\Domain\Organizacao\Services;

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use RuntimeException;

class ConsultaOrganizacao implements ConsultarOrganizacao
{
    public function setorVigente(string $igrejaId): ?Setor
    {
        $vinculo = IgrejaVinculacao::query()
            ->where('igreja_id', $igrejaId)
            ->whereNull('fim')
            ->first();

        return $vinculo?->setor;
    }

    public function regionalDoSetor(string $setorId): ?Regional
    {
        return Setor::query()->find($setorId)?->regional;
    }

    public function nomesDeIgrejas(array $igrejaIds): array
    {
        return Igreja::query()->whereIn('id', $igrejaIds)->pluck('nome', 'id')->all();
    }

    public function nomes(string $tipo, array $ids): array
    {
        $modelo = match ($tipo) {
            'central' => CentralNacional::query(),
            'regional' => Regional::query(),
            'setor' => Setor::query(),
            'igreja' => Igreja::query(),
            'ebi' => Ebi::query(),
            default => throw new RuntimeException("Tipo de no sem nome: {$tipo}"),
        };

        return $modelo->whereIn('id', $ids)->pluck('nome', 'id')->all();
    }

    public function igrejaDosEbis(array $ebiIds): array
    {
        return Ebi::query()->whereIn('id', $ebiIds)->pluck('igreja_id', 'id')->all();
    }

    public function fusosDasIgrejas(array $igrejaIds): array
    {
        return Igreja::query()->whereIn('id', $igrejaIds)->pluck('timezone', 'id')->all();
    }
}
