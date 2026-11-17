<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Sessoes\Contracts\ConsultarPresenca;
use App\Domain\Sessoes\Contracts\PresencaRegistrada;
use App\Domain\Sessoes\Models\Presenca;

class ConsultaPresenca implements ConsultarPresenca
{
    public function daSessao(string $sessaoId): array
    {
        return Presenca::query()->where('sessao_id', $sessaoId)
            ->with('participante')->orderBy('registrado_em')->orderBy('id')->get()
            ->map(fn (Presenca $p) => new PresencaRegistrada(
                (string) $p->id, $p->sessao_id, $p->participante_id,
                $p->participante?->nome, $p->participante?->matricula,
                $p->registrado_por, $p->registrado_em->toIso8601String(),
                $p->origem, $p->status, $p->estornado_em?->toIso8601String(), $p->motivo_estorno,
            ))->all();
    }

    public function ebiDaPresenca(string $presencaId): ?string
    {
        return Presenca::query()->join('sessao', 'sessao.id', '=', 'presenca.sessao_id')
            ->where('presenca.id', $presencaId)->value('sessao.ebi_id');
    }
}
