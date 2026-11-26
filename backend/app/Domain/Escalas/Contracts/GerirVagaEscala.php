<?php

namespace App\Domain\Escalas\Contracts;

use App\Domain\Identidade\Contracts\ContextoDecisao;

/**
 * Ciclo da vaga (RF-07, evolucao): abrir, cancelar, oferecer, retirar oferta e
 * a dona escolher. Toda regra roda na `TravaEscala` da escala.
 */
interface GerirVagaEscala
{
    /** Abre uma vaga de acrescimo (sem `$substituiMembroId`) ou de substituicao. Devolve o id. */
    public function abrir(string $escalaId, ?string $substituiMembroId, ?string $observacao, ContextoDecisao $contexto, ?string $autorId): string;

    public function cancelar(string $vagaId, ContextoDecisao $contexto, ?string $autorId): void;

    /** Oferta da propria pessoa; devolve o id da oferta. */
    public function oferecer(string $vagaId, string $usuarioId): string;

    public function retirarOferta(string $ofertaId, string $usuarioId): void;

    /** So a dona escolhe; devolve o id do membro criado (`confirmado`). */
    public function escolher(string $ofertaId, ContextoDecisao $contexto, ?string $autorId): string;
}
