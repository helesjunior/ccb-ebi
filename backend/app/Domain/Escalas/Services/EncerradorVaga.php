<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\OfertaVagaEscala;
use App\Domain\Escalas\Models\VagaEscala;

/**
 * Encerra vagas abertas: marca `cancelada`, descarta as ofertas pendentes e
 * audita `escala.vaga_cancelada` (ids e motivo). O chamador ja esta dentro da
 * `TravaEscala`; o encerrador nao abre trava.
 */
class EncerradorVaga
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function cancelar(VagaEscala $vaga, string $motivo, ?string $autorId): void
    {
        if (! $vaga->estaAberta()) {
            return;
        }

        $vaga->forceFill([
            'status' => VagaEscala::STATUS_CANCELADA,
            'motivo_encerramento' => $motivo,
            'encerrada_por' => $autorId,
            'encerrada_em' => now(),
        ])->save();

        OfertaVagaEscala::query()
            ->where('vaga_id', $vaga->id)
            ->where('status', OfertaVagaEscala::STATUS_PENDENTE)
            ->update(['status' => OfertaVagaEscala::STATUS_DESCARTADA, 'updated_at' => now()]);

        $escalaId = (string) $vaga->escala_id;

        $this->auditoria->registrar([
            'acao' => 'escala.vaga_cancelada',
            'recurso' => 'vaga_escala',
            'ator_id' => $autorId,
            'origem' => 'api',
            'escopo' => 'ebi:'.(string) Escala::query()->whereKey($escalaId)->value('ebi_id'),
            'motivo' => $motivo,
            'metadados_minimizados' => [
                'vaga_id' => (string) $vaga->id,
                'escala_id' => $escalaId,
                'motivo_encerramento' => $motivo,
                'status' => VagaEscala::STATUS_CANCELADA,
            ],
        ]);
    }

    /** Cancela toda vaga aberta que substitui este membro. */
    public function cancelarAbertasDoMembro(string $membroId, string $motivo, ?string $autorId): int
    {
        $vagas = VagaEscala::query()
            ->where('substitui_membro_id', $membroId)
            ->where('status', VagaEscala::STATUS_ABERTA)
            ->get();

        foreach ($vagas as $vaga) {
            $this->cancelar($vaga, $motivo, $autorId);
        }

        return $vagas->count();
    }
}
