<?php

namespace App\Domain\Agenda\Services;

use App\Domain\Agenda\Contracts\ConsultarAgenda;
use App\Domain\Agenda\Contracts\OcorrenciaAgenda;
use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Agenda\Models\AgendaExcecao;
use Carbon\CarbonImmutable;

class ConsultaAgenda implements ConsultarAgenda
{
    public function ocorrenciasDoDia(string $ebiId, string $dataIso): array
    {
        $dia = CarbonImmutable::parse($dataIso)->dayOfWeekIso;
        $excecoes = AgendaExcecao::query()->where('ebi_id', $ebiId)->whereDate('data', $dataIso)->get();
        if ($excecoes->whereIn('tipo', [AgendaExcecao::TIPO_FERIADO, AgendaExcecao::TIPO_CANCELAMENTO])->isNotEmpty()) {
            return [];
        }

        $especiais = $excecoes->where('tipo', AgendaExcecao::TIPO_HORARIO_ESPECIAL)->keyBy('agenda_ebi_id');

        return AgendaEbi::query()->where('ebi_id', $ebiId)->where('dia_semana', $dia)
            ->whereDate('inicio_vigencia', '<=', $dataIso)
            ->where(fn ($q) => $q->whereNull('fim_vigencia')->orWhereDate('fim_vigencia', '>=', $dataIso))
            ->orderBy('hora_inicio')->get()
            ->map(function (AgendaEbi $faixa) use ($especiais, $ebiId, $dataIso): OcorrenciaAgenda {
                $especial = $especiais->get($faixa->id);

                return new OcorrenciaAgenda(
                    (string) $faixa->id, $ebiId, $dataIso,
                    substr($especial?->hora_inicio ?? $faixa->hora_inicio, 0, 5),
                    substr($especial?->hora_fim ?? $faixa->hora_fim, 0, 5),
                );
            })->values()->all();
    }
}
