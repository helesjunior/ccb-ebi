<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Contracts\ConsultarCoberturaEscala;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Sessoes\Contracts\ConsultarSessao;

/**
 * M-06: uma sessao esta coberta quando a escala esta `aprovada` e a equipe
 * minima ja confirmada (confirmados e vigentes na data) e atingida. O divisor
 * e o registrado na escala na aprovacao; mudar o divisor do EBI depois nao
 * altera a cobertura. Os intervalos vem de uma consulta em lote e as
 * vigencias sao buscadas uma vez por EBI pelo `AvaliadorEquipe`.
 */
class ConsultaCoberturaEscala implements ConsultarCoberturaEscala
{
    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly AvaliadorEquipe $avaliador,
    ) {}

    public function cobertas(array $sessaoIds): array
    {
        $ids = array_values(array_unique(array_map('strval', $sessaoIds)));

        if ($ids === []) {
            return [];
        }

        $escalas = Escala::query()
            ->whereIn('sessao_id', $ids)
            ->where('status', Escala::STATUS_APROVADA)
            ->with('membros')
            ->get();

        if ($escalas->isEmpty()) {
            return [];
        }

        $intervalos = $this->sessoes->intervalos(
            $escalas->pluck('sessao_id')->map(fn ($id): string => (string) $id)->unique()->values()->all(),
        );

        $cobertas = [];

        foreach ($escalas as $escala) {
            $sessao = $intervalos[(string) $escala->sessao_id] ?? null;

            if ($sessao === null) {
                continue;
            }

            if ($this->avaliador->equipeMinima($escala, $sessao, soConfirmados: true)['atingida']) {
                $cobertas[(string) $escala->sessao_id] = true;
            }
        }

        return $cobertas;
    }
}
