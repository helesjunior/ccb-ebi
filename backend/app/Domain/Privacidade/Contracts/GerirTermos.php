<?php

namespace App\Domain\Privacidade\Contracts;

use App\Domain\Privacidade\Models\TermoVersao;

/**
 * Termos versionados (RF-04): publicar uma nova versao e consultar a vigente
 * de cada finalidade.
 */
interface GerirTermos
{
    public function vigente(string $finalidade): ?TermoVersao;

    public function encontrar(string $id): ?TermoVersao;

    public function publicar(
        string $finalidade,
        string $baseLegal,
        string $versao,
        string $texto,
        \DateTimeInterface $vigenteEm,
    ): TermoVersao;
}
