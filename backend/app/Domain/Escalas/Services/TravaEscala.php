<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Models\Escala;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

/**
 * Serializa as alteracoes de uma escala: toda regra que depende do estado da
 * escala e dos membros (vagas, funcao, volta a prevista) roda dentro da mesma
 * transacao com a linha da escala travada. Em SQLite o `lockForUpdate` nao tem
 * efeito; a serializacao real e do PostgreSQL em producao.
 */
class TravaEscala
{
    /**
     * @template T
     *
     * @param  callable(Escala): T  $fn
     * @return T
     */
    public function executar(string $escalaId, callable $fn): mixed
    {
        return DB::transaction(function () use ($escalaId, $fn): mixed {
            $escala = Escala::query()->lockForUpdate()->find($escalaId)
                ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Escala não encontrada.');

            return $fn($escala);
        });
    }
}
