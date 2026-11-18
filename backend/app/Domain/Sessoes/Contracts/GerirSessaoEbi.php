<?php

namespace App\Domain\Sessoes\Contracts;

interface GerirSessaoEbi
{
    public function abrir(string $sessaoId, string $autorId): SessaoEbi;

    public function encerrar(string $sessaoId, bool $realizada, ?string $fimRealIso, string $autorId): SessaoEbi;

    public function cancelar(string $sessaoId, string $motivo, string $autorId): SessaoEbi;

    /** @param array<string, mixed> $dados */
    public function corrigir(string $sessaoId, array $dados, string $autorId): SessaoEbi;
}
