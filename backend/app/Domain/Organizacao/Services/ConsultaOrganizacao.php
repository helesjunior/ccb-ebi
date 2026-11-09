<?php

namespace App\Domain\Organizacao\Services;

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;

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
}
