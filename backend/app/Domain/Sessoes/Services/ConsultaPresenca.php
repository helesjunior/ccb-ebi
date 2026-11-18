<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Participantes\Contracts\ConsultarParticipante;
use App\Domain\Sessoes\Contracts\ConsultarPresenca;
use App\Domain\Sessoes\Contracts\HistoricoPresenca;
use App\Domain\Sessoes\Contracts\PresencaRegistrada;
use App\Domain\Sessoes\Models\Presenca;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;

class ConsultaPresenca implements ConsultarPresenca
{
    public function __construct(
        private readonly ConsultarEbi $ebis,
        private readonly ConsultarParticipante $participantes,
    ) {}

    public function doParticipante(string $participanteId, ?string $de = null, ?string $ate = null): array
    {
        $presencas = Presenca::query()->where('participante_id', $participanteId)
            ->when($de !== null, fn ($q) => $q->where('registrado_em', '>=', CarbonImmutable::parse($de)->utc()))
            ->when($ate !== null, fn ($q) => $q->where('registrado_em', '<=', CarbonImmutable::parse($ate)->utc()))
            ->with('sessao')
            ->orderByDesc('registrado_em')->orderByDesc('id')->get();
        $ebis = [];

        return $presencas->map(function (Presenca $p) use (&$ebis): HistoricoPresenca {
            $sessao = $p->sessao ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
            $ebis[$sessao->ebi_id] ??= $this->ebis->dados($sessao->ebi_id);

            return new HistoricoPresenca(
                (string) $p->id, $p->sessao_id, $sessao->ebi_id, $ebis[$sessao->ebi_id]->nome ?? '',
                $ebis[$sessao->ebi_id]->timezone ?? 'UTC',
                CarbonImmutable::parse($sessao->inicio_previsto)->toIso8601String(), $p->status,
                $p->registrado_em->toIso8601String(),
            );
        })->all();
    }

    public function daSessao(string $sessaoId): array
    {
        $participantes = [];
        $presencas = Presenca::query()->where('sessao_id', $sessaoId)
            ->orderBy('registrado_em')->orderBy('id')->get();

        return $presencas->map(function (Presenca $p) use (&$participantes): PresencaRegistrada {
            $participantes[$p->participante_id] ??= $this->participantes->encontrar($p->participante_id);

            return new PresencaRegistrada(
                (string) $p->id, $p->sessao_id, $p->participante_id,
                $participantes[$p->participante_id]?->nome, $participantes[$p->participante_id]?->matricula,
                $p->registrado_por, $p->registrado_em->toIso8601String(),
                $p->origem, $p->status, $p->estornado_em?->toIso8601String(), $p->motivo_estorno,
            );
        })->all();
    }

    public function ebiDaPresenca(string $presencaId): ?string
    {
        return Presenca::query()->join('sessao', 'sessao.id', '=', 'presenca.sessao_id')
            ->where('presenca.id', $presencaId)->value('sessao.ebi_id');
    }
}
