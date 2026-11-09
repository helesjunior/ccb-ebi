<?php

namespace App\Domain\Privacidade\Services;

use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Domain\Privacidade\Models\TermoVersao;

class GerenciadorTermos implements GerirTermos
{
    public function vigente(string $finalidade): ?TermoVersao
    {
        return TermoVersao::query()->vigente($finalidade)->first();
    }

    public function publicar(
        string $finalidade,
        string $baseLegal,
        string $versao,
        string $texto,
        \DateTimeInterface $vigenteEm,
    ): TermoVersao {
        return TermoVersao::query()->create([
            'finalidade' => $finalidade,
            'base_legal' => $baseLegal,
            'versao' => $versao,
            'texto' => $texto,
            'texto_hash' => hash('sha256', $texto),
            'vigente_em' => $vigenteEm,
            'encerrado_em' => null,
        ]);
    }
}
