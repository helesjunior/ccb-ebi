<?php

namespace App\Domain\Escalas\Contracts;

use App\Domain\Escalas\Models\Escala;

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
}
