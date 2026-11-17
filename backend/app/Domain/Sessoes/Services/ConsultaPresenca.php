<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Sessoes\Contracts\ConsultarPresenca;
use App\Domain\Sessoes\Contracts\HistoricoPresenca;
use App\Domain\Sessoes\Contracts\PresencaRegistrada;
use App\Domain\Sessoes\Models\Presenca;
use Carbon\CarbonImmutable;

class ConsultaPresenca implements ConsultarPresenca
{
    public function __construct(private readonly ConsultarEbi $ebis) {}

    public function doParticipante(string $participanteId, ?string $de = null, ?string $ate = null): array
    {
        $presencas = Presenca::query()->join('sessao', 'sessao.id', '=', 'presenca.sessao_id')
            ->where('presenca.participante_id', $participanteId)
            ->when($de !== null, fn ($q) => $q->where('presenca.registrado_em', '>=', CarbonImmutable::parse($de)->utc()))
            ->when($ate !== null, fn ($q) => $q->where('presenca.registrado_em', '<=', CarbonImmutable::parse($ate)->utc()))
            ->orderByDesc('presenca.registrado_em')->orderByDesc('presenca.id')
            ->get(['presenca.*', 'sessao.ebi_id', 'sessao.inicio_previsto']);
        $nomes = [];

        return $presencas->map(function (Presenca $p) use (&$nomes): HistoricoPresenca {
            $nomes[$p->ebi_id] ??= $this->ebis->dados($p->ebi_id)?->nome ?? '';

            return new HistoricoPresenca(
                (string) $p->id, $p->sessao_id, $p->ebi_id, $nomes[$p->ebi_id],
                CarbonImmutable::parse($p->inicio_previsto)->toIso8601String(), $p->status,
                $p->registrado_em->toIso8601String(),
            );
        })->all();
    }

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
