<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;

/**
 * Dado de cancelamento de uma troca pendente: o membro volta ao status anterior
 * e o evento `escala.troca_cancelada` sai com ids apenas.
 */
class RegistradorTrocaCancelada
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function registrar(SolicitacaoTrocaEscala $troca, string $escalaId, string $ebiId, string $motivoDecisao, ?string $autorId): void
    {
        $troca->forceFill([
            'status' => SolicitacaoTrocaEscala::STATUS_CANCELADA,
            'motivo_decisao' => $motivoDecisao,
            'decidido_em' => now(),
        ])->save();

        $membro = $troca->membro;
        if ($membro !== null && $membro->status === EscalaMembro::STATUS_TROCA_SOLICITADA) {
            $membro->forceFill(['status' => $membro->statusAnterior()])->save();
        }

        $this->auditoria->registrar([
            'acao' => 'escala.troca_cancelada',
            'recurso' => 'solicitacao_troca_escala',
            'ator_id' => $autorId,
            'origem' => 'api',
            'escopo' => 'ebi:'.$ebiId,
            'motivo' => $motivoDecisao,
            'metadados_minimizados' => [
                'troca_id' => (string) $troca->id,
                'escala_id' => $escalaId,
                'membro_id' => (string) $troca->escala_membro_id,
                'status' => SolicitacaoTrocaEscala::STATUS_CANCELADA,
            ],
        ]);
    }
}
