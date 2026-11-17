<?php

namespace App\Domain\Sessoes\Contracts;

interface ConsultarSessao
{
    /** @return array<int, SessaoEbi> */
    public function listar(string $ebiId, string $de, string $ate, ?string $status = null): array;

    public function detalhe(string $sessaoId): ?SessaoEbi;
}
