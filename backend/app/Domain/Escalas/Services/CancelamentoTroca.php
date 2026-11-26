<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;

/**
 * Cancela o pedido de troca que deixou de fazer sentido porque a escala mudou
 * (estimativa, retirada, funcao) ou porque a vaga do membro foi preenchida.
 * O chamador ja esta dentro da `TravaEscala`; o encerrador nao abre trava.
 */
class CancelamentoTroca
{
    public function __construct(private readonly RegistradorTrocaCancelada $registrador) {}

    /**
     * Cancela as trocas pendentes da escala (todas, ou so as do membro) e
     * devolve a quantidade cancelada.
     */
    public function cancelarPendentes(string $escalaId, ?string $membroId, string $motivoDecisao, ?string $autorId): int
    {
        $trocas = SolicitacaoTrocaEscala::query()
            ->whereIn('escala_membro_id', EscalaMembro::query()->where('escala_id', $escalaId)->select('id'))
            ->where('status', SolicitacaoTrocaEscala::STATUS_PENDENTE)
            ->when($membroId !== null, fn ($q) => $q->where('escala_membro_id', $membroId))
            ->with('membro')
            ->get();

        if ($trocas->isEmpty()) {
            return 0;
        }

        $ebiId = (string) Escala::query()->whereKey($escalaId)->value('ebi_id');

        foreach ($trocas as $troca) {
            $this->registrador->registrar($troca, $escalaId, $ebiId, $motivoDecisao, $autorId);
        }

        return $trocas->count();
    }
}
