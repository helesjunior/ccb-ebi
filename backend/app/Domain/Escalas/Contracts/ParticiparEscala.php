<?php

namespace App\Domain\Escalas\Contracts;

use App\Domain\Escalas\Models\EscalaMembro;

/**
 * Participacao da propria pessoa na escala (RF-07): quem foi escalado confirma
 * a vaga. Todas as guardas rodam na trava da escala.
 */
interface ParticiparEscala
{
    /**
     * `escalado` -> `confirmado` e audita `escala.membro_confirmado`; quem ja
     * confirmou recebe a mesma resposta sem novo evento.
     *
     * Membro de outra pessoa ou inexistente: 404. Sem vinculo na data: 422
     * `membro_sem_vinculo`. Escala fora de `aprovada`: 409
     * `escala_em_estado_invalido`; sessao fora de `prevista`/`aberta`: 409
     * `sessao_em_estado_invalido`; membro em `troca_solicitada` ou
     * `substituido`: 409 `membro_em_estado_invalido`.
     */
    public function confirmar(string $membroId, string $usuarioId): EscalaMembro;
}
