<?php

namespace App\Domain\Sessoes\Contracts;

interface ConsultarSessao
{
    /** @return array<int, SessaoEbi> */
    public function listar(string $ebiId, string $de, string $ate, ?string $status = null): array;

    public function detalhe(string $sessaoId): ?SessaoEbi;

    /**
     * Intervalos previstos por id de sessao; ids inexistentes ficam de fora.
     *
     * @param  array<int, string>  $sessaoIds
     * @return array<string, IntervaloSessao>
     */
    public function intervalos(array $sessaoIds): array;
}
