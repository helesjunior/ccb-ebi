<?php

namespace App\Domain\Organizacao\Contracts;

use App\Domain\Organizacao\Exceptions\SobreposicaoVigenciaException;
use App\Domain\Organizacao\Models\IgrejaVinculacao;

/**
 * Superficie publica de transferencia de igreja entre setores.
 *
 * Regra territorial: nao ha data de corte. O setor vigente e sempre o atual;
 * a igreja e a ancora. O historico de vinculos preserva as transferencias (RF-01).
 */
interface TransferirIgreja
{
    /**
     * Transfere a igreja para o setor destino na data informada.
     *
     * Fecha o vinculo ativo com `fim = data` (exclusivo, [inicio, fim)) e abre
     * um novo com `inicio = data`. Rejeita sobreposicao de vigencia.
     *
     * @throws SobreposicaoVigenciaException
     */
    public function executar(
        string $igrejaId,
        string $setorDestinoId,
        \DateTimeInterface $data,
        ?string $motivo = null,
    ): IgrejaVinculacao;
}
