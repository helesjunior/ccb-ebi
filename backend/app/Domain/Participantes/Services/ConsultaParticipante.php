<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Participantes\Contracts\ConsultarParticipante;
use App\Domain\Participantes\Contracts\ParticipanteResumo;
use App\Domain\Participantes\Models\Participante;
use Illuminate\Support\Str;

class ConsultaParticipante implements ConsultarParticipante
{
    public function buscar(string $termo, int $limite = 20): array
    {
        $termoNormalizado = mb_strtolower(Str::ascii($termo));

        if (mb_strlen($termoNormalizado) < 3 || $limite < 1) {
            return [];
        }

        return Participante::query()
            ->where('status', '!=', 'mesclado')
            ->where(function ($query) use ($termo, $termoNormalizado) {
                $query->where('matricula', 'like', $termo.'%')
                    ->orWhere('nome_busca', 'like', '%'.mb_substr($termoNormalizado, 0, 3).'%');
            })
            ->limit(100)
            ->get()
            ->filter(fn (Participante $participante) => str_contains(
                mb_strtolower(Str::ascii($participante->nome)),
                $termoNormalizado,
            ) || str_starts_with(mb_strtolower($participante->matricula), mb_strtolower($termo)))
            ->sortBy(fn (Participante $participante) => mb_strtolower(Str::ascii($participante->nome)))
            ->take($limite)
            ->map(fn (Participante $participante) => $this->resumo($participante))
            ->values()
            ->all();
    }

    public function encontrar(string $participanteId): ?ParticipanteResumo
    {
        $participante = Participante::query()
            ->where('status', '!=', 'mesclado')
            ->find($participanteId);

        return $participante ? $this->resumo($participante) : null;
    }

    private function resumo(Participante $participante): ParticipanteResumo
    {
        return new ParticipanteResumo(
            $participante->id,
            $participante->nome,
            $participante->matricula,
            $participante->igreja_referencia_id,
        );
    }
}
