<?php

namespace App\Domain\Privacidade\Services;

use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Domain\Privacidade\Models\TermoVersao;
use Illuminate\Support\Facades\DB;

class GerenciadorTermos implements GerirTermos
{
    public function vigente(string $finalidade): ?TermoVersao
    {
        return TermoVersao::query()
            ->vigente($finalidade)
            ->latest('vigente_em')
            ->orderByDesc('id')
            ->first();
    }

    public function encontrar(string $id): ?TermoVersao
    {
        return TermoVersao::query()->find($id);
    }

    public function publicar(
        string $finalidade,
        string $baseLegal,
        string $versao,
        string $texto,
        \DateTimeInterface $vigenteEm,
    ): TermoVersao {
        return DB::transaction(function () use ($finalidade, $baseLegal, $versao, $texto, $vigenteEm): TermoVersao {
            // Uma versao aberta por finalidade: a anterior encerra quando a nova passa a valer.
            TermoVersao::query()
                ->where('finalidade', $finalidade)
                ->whereNull('encerrado_em')
                ->update(['encerrado_em' => $vigenteEm]);

            return TermoVersao::query()->create([
                'finalidade' => $finalidade,
                'base_legal' => $baseLegal,
                'versao' => $versao,
                'texto' => $texto,
                'texto_hash' => hash('sha256', $texto),
                'vigente_em' => $vigenteEm,
                'encerrado_em' => null,
            ]);
        });
    }
}
