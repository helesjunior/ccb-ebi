<?php

namespace App\Domain\Agenda\Contracts;

interface GerirAgenda
{
    /** @return array<int, FaixaAgenda> */
    public function faixas(string $ebiId): array;

    public function faixa(string $faixaId): ?FaixaAgenda;

    /** @param array<string, mixed> $dados */
    public function criarFaixa(string $ebiId, array $dados): FaixaAgenda;

    /** @param array<string, mixed> $dados */
    public function atualizarFaixa(string $faixaId, array $dados): FaixaAgenda;

    public function encerrarFaixa(string $faixaId): void;
}
