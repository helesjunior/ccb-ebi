<?php

namespace App\Domain\Escalas\Contracts;

use App\Domain\Escalas\Models\Escala;
use App\Domain\Identidade\Contracts\ContextoDecisao;

interface GerirEscala
{
    /**
     * Cria a escala de uma sessao. `$membros` e `array<int, array{usuario_id: string, funcao: string}>`.
     *
     * @param  array<int, array{usuario_id: string, funcao: string}>  $membros
     */
    public function criar(string $sessaoId, int $estimativa, array $membros, ?string $autorId): Escala;

    public function alterarEstimativa(string $escalaId, int $estimativa, ?string $autorId): Escala;

    public function incluirMembro(string $escalaId, string $usuarioId, string $funcao, ?string $autorId): Escala;

    public function retirarMembro(string $membroId, ?string $autorId): Escala;

    public function mudarFuncao(string $membroId, string $funcao, ?string $autorId): Escala;

    /**
     * Aprova a escala. Quem pode aprovar e decidido pelo `QuemAprova`; as
     * demais guardas (estado da escala e da sessao, vinculos e equipe minima)
     * rodam na ordem do plano.
     */
    public function aprovar(string $escalaId, ContextoDecisao $contexto, ?string $autorId): Escala;

    /**
     * Presenca da equipe (planejado x cumprido). `$membros` e
     * `array<int, array{id: string, presente: bool}>`; grava so o que muda e
     * audita uma vez quando algo mudou.
     *
     * @param  array<int, array{id: string, presente: bool}>  $membros
     */
    public function registrarPresencaEquipe(string $escalaId, array $membros, ?string $autorId): Escala;
}
