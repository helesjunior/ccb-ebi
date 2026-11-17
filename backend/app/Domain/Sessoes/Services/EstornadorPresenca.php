<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Participantes\Contracts\ConsultarParticipante;
use App\Domain\Sessoes\Contracts\ConsultarPresenca;
use App\Domain\Sessoes\Contracts\EstornarPresenca;
use App\Domain\Sessoes\Contracts\PresencaRegistrada;
use App\Domain\Sessoes\Models\Presenca;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

class EstornadorPresenca implements EstornarPresenca
{
    public function __construct(
        private readonly RegistrarEvento $auditoria,
        private readonly ConsultarPresenca $consulta,
        private readonly ConsultarParticipante $participantes,
    ) {}

    public function estornar(string $presencaId, string $motivo, string $usuarioId): PresencaRegistrada
    {
        return DB::transaction(function () use ($presencaId, $motivo, $usuarioId): PresencaRegistrada {
            $p = Presenca::query()->lockForUpdate()->find($presencaId)
                ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
            if ($p->status === Presenca::STATUS_ESTORNADA) {
                throw new ProblemException('presenca_ja_estornada', 'Conflito de estado', 409);
            }
            $ebiId = $this->consulta->ebiDaPresenca($presencaId);
            $p->update([
                'status' => Presenca::STATUS_ESTORNADA, 'estornado_por' => $usuarioId,
                'estornado_em' => now(), 'motivo_estorno' => $motivo,
            ]);
            $this->auditoria->registrar([
                'acao' => 'presenca.estornada', 'recurso' => 'presenca', 'ator_id' => $usuarioId,
                'origem' => 'api', 'escopo' => "ebi:{$ebiId}", 'motivo' => $motivo,
                'metadados_minimizados' => ['presenca_id' => $presencaId, 'sessao_id' => $p->sessao_id],
            ]);
            $participante = $this->participantes->encontrar($p->participante_id);

            return new PresencaRegistrada(
                (string) $p->id, $p->sessao_id, $p->participante_id, $participante?->nome, $participante?->matricula,
                $p->registrado_por, $p->registrado_em->toIso8601String(), $p->origem, $p->status,
                $p->estornado_em->toIso8601String(), $p->motivo_estorno,
            );
        });
    }
}
