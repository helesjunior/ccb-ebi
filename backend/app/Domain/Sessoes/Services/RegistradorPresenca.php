<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Participantes\Contracts\AvaliarElegibilidade;
use App\Domain\Participantes\Contracts\ConsultarParticipante;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\PresencaRegistrada;
use App\Domain\Sessoes\Contracts\RegistrarPresenca;
use App\Domain\Sessoes\Contracts\ResultadoPresenca;
use App\Domain\Sessoes\Contracts\SessaoEbi;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RegistradorPresenca implements RegistrarPresenca
{
    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly ConsultarEbi $ebis,
        private readonly AvaliarElegibilidade $elegibilidade,
        private readonly ConsultarParticipante $participantes,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function registrar(string $sessaoId, string $participanteId, string $usuarioId, string $origem = 'web'): ResultadoPresenca
    {
        $sessao = $this->sessoes->detalhe($sessaoId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        if ($sessao->status !== Sessao::STATUS_ABERTA) {
            throw new ProblemException('sessao_em_estado_invalido', 'Conflito de estado', 409);
        }
        $timezone = $this->avaliarOuFalhar($sessao, $participanteId);
        if (($replay = $this->replayExistente($sessaoId, $participanteId)) !== null) {
            return $replay;
        }

        try {
            $presenca = DB::transaction(function () use ($sessaoId, $participanteId, $usuarioId, $origem, $sessao): Presenca {
                $presenca = Presenca::query()->create([
                    'sessao_id' => $sessaoId, 'participante_id' => $participanteId,
                    'registrado_por' => $usuarioId, 'registrado_em' => now(),
                    'origem' => $origem, 'status' => Presenca::STATUS_VALIDA,
                ]);
                $this->auditoria->registrar([
                    'acao' => 'presenca.registrada', 'recurso' => 'presenca', 'ator_id' => $usuarioId,
                    'origem' => 'api', 'escopo' => "ebi:{$sessao->ebiId}",
                    'metadados_minimizados' => ['sessao_id' => $sessaoId, 'participante_id' => $participanteId, 'origem' => $origem],
                ]);

                return $presenca;
            });
        } catch (QueryException $e) {
            // PostgreSQL precisa sair da transação abortada antes de consultar o vencedor.
            $mensagem = $e->getMessage();
            $unicidade = ($e->errorInfo[0] ?? null) === '23505' && str_contains($mensagem, 'presenca_valida_unica');
            $sqlite = ($e->errorInfo[1] ?? null) === 19
                && str_contains($mensagem, 'UNIQUE constraint failed: presenca.sessao_id, presenca.participante_id');
            if ((! $unicidade && ! $sqlite) || ($existente = $this->existente($sessaoId, $participanteId)) === null) {
                throw $e;
            }

            return $this->resultado($existente, false);
        }

        $inicioDia = CarbonImmutable::parse($sessao->inicioPrevisto)->setTimezone($timezone)->startOfDay();
        $simultanea = Presenca::query()->join('sessao', 'sessao.id', '=', 'presenca.sessao_id')
            ->where('presenca.participante_id', $participanteId)->where('presenca.status', Presenca::STATUS_VALIDA)
            ->where('sessao.id', '!=', $sessaoId)->where('sessao.igreja_id', '!=', $sessao->igrejaId)
            ->where('sessao.inicio_previsto', '>=', $inicioDia->utc())
            ->where('sessao.inicio_previsto', '<', $inicioDia->addDay()->utc())->exists();

        return $this->resultado($presenca, true, $simultanea ? 'presenca_simultanea' : null);
    }

    public function registrarContingencia(string $sessaoId, string $participanteId, string $motivo, string $usuarioId): ResultadoPresenca
    {
        $sessao = $this->sessoes->detalhe($sessaoId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        if ($sessao->status !== Sessao::STATUS_ENCERRADA) {
            throw new ProblemException('sessao_em_estado_invalido', 'Conflito de estado', 409);
        }
        $this->avaliarOuFalhar($sessao, $participanteId);
        if (($replay = $this->replayExistente($sessaoId, $participanteId)) !== null) {
            return $replay;
        }

        try {
            $presenca = DB::transaction(function () use ($sessaoId, $participanteId, $motivo, $usuarioId, $sessao): Presenca {
                $presenca = Presenca::query()->create([
                    'sessao_id' => $sessaoId, 'participante_id' => $participanteId,
                    'registrado_por' => $usuarioId, 'registrado_em' => now(),
                    'origem' => 'contingencia', 'status' => Presenca::STATUS_VALIDA,
                ]);
                $this->auditoria->registrar([
                    'acao' => 'presenca.contingencia', 'recurso' => 'presenca', 'ator_id' => $usuarioId,
                    'origem' => 'api', 'escopo' => "ebi:{$sessao->ebiId}", 'motivo' => $motivo,
                    'metadados_minimizados' => ['sessao_id' => $sessaoId, 'participante_id' => $participanteId],
                ]);

                return $presenca;
            });
        } catch (QueryException $e) {
            $mensagem = $e->getMessage();
            $unicidade = ($e->errorInfo[0] ?? null) === '23505' && str_contains($mensagem, 'presenca_valida_unica');
            $sqlite = ($e->errorInfo[1] ?? null) === 19
                && str_contains($mensagem, 'UNIQUE constraint failed: presenca.sessao_id, presenca.participante_id');
            if ((! $unicidade && ! $sqlite) || ($existente = $this->existente($sessaoId, $participanteId)) === null) {
                throw $e;
            }

            return $this->resultado($existente, false);
        }

        return $this->resultado($presenca, true);
    }

    private function avaliarOuFalhar(SessaoEbi $sessao, string $participanteId): string
    {
        $ebi = $this->ebis->dados($sessao->ebiId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        $resultado = $this->elegibilidade->avaliarPorId($participanteId, CarbonImmutable::parse($sessao->inicioPrevisto), $ebi->timezone);
        if (! $resultado->permitido) {
            throw new ProblemException('inelegivel', 'Participante inelegível', 422, extra: ['motivo_bloqueio' => $resultado->motivo]);
        }

        return $ebi->timezone;
    }

    private function replayExistente(string $sessaoId, string $participanteId): ?ResultadoPresenca
    {
        $existente = $this->existente($sessaoId, $participanteId);

        return $existente === null ? null : $this->resultado($existente, false);
    }

    private function existente(string $sessaoId, string $participanteId): ?Presenca
    {
        return Presenca::query()->where('sessao_id', $sessaoId)->where('participante_id', $participanteId)
            ->where('status', Presenca::STATUS_VALIDA)->first();
    }

    private function resultado(Presenca $p, bool $nova, ?string $aviso = null): ResultadoPresenca
    {
        $participante = $this->participantes->encontrar($p->participante_id);

        return new ResultadoPresenca(new PresencaRegistrada(
            (string) $p->id, $p->sessao_id, $p->participante_id, $participante?->nome, $participante?->matricula,
            $p->registrado_por, $p->registrado_em->toIso8601String(), $p->origem, $p->status,
            $p->estornado_em?->toIso8601String(), $p->motivo_estorno,
        ), $nova, $aviso);
    }
}
