<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\VagaEscala;
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
        return DB::transaction(fn (): mixed => $fn($this->escalaTravada($escalaId)));
    }

    /**
     * Ancora a trava pela vaga. A ordem de travas e sempre escala -> vaga: a
     * vaga apenas aponta para a escala, e o id e lido sem trava-fora, entao
     * todos os caminhos (retirar membro, mudar funcao, escolher) pegam as
     * mesmas linhas na mesma ordem. Travar a vaga antes da escala fechava um
     * ciclo ABBA com `cancelarAbertasDoMembro` e o PostgreSQL abortava com
     * `40P01` (deadlock).
     *
     * @template T
     *
     * @param  callable(Escala): T  $fn
     * @return T
     */
    public function executarPelaVaga(string $vagaId, callable $fn): mixed
    {
        $escalaId = (string) VagaEscala::query()->whereKey($vagaId)->value('escala_id');

        if ($escalaId === '') {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vaga não encontrada.');
        }

        return $this->executar($escalaId, $fn);
    }

    private function escalaTravada(string $escalaId): Escala
    {
        return Escala::query()->lockForUpdate()->find($escalaId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Escala não encontrada.');
    }
}
