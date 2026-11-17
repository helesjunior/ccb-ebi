<?php

namespace App\Domain\Agenda\Services;

use App\Domain\Agenda\Contracts\FaixaAgenda;
use App\Domain\Agenda\Contracts\GerirAgenda;
use App\Domain\Agenda\Models\AgendaEbi;
use App\Support\ProblemException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GerenciadorAgenda implements GerirAgenda
{
    public function faixas(string $ebiId): array
    {
        return AgendaEbi::query()->where('ebi_id', $ebiId)->orderBy('dia_semana')->orderBy('hora_inicio')->get()
            ->map(fn (AgendaEbi $faixa): FaixaAgenda => $this->dto($faixa))->all();
    }

    public function faixa(string $faixaId): ?FaixaAgenda
    {
        $faixa = AgendaEbi::query()->find($faixaId);

        return $faixa ? $this->dto($faixa) : null;
    }

    public function criarFaixa(string $ebiId, array $dados): FaixaAgenda
    {
        $dados['fim_vigencia'] ??= null;
        $this->validar($dados);
        $this->validarSobreposicao($ebiId, $dados);

        return $this->dto(AgendaEbi::query()->create([...$dados, 'ebi_id' => $ebiId]));
    }

    public function atualizarFaixa(string $faixaId, array $dados): FaixaAgenda
    {
        $faixa = AgendaEbi::query()->find($faixaId) ?? $this->naoEncontrada();
        if ($faixa->inicio_vigencia->toDateString() <= today()->toDateString()) {
            throw new ProblemException('agenda_vigente', 'Agenda vigente', 422);
        }

        $dados = array_merge($faixa->only(['dia_semana', 'hora_inicio', 'hora_fim', 'inicio_vigencia', 'fim_vigencia']), $dados);
        $dados['inicio_vigencia'] = is_string($dados['inicio_vigencia']) ? $dados['inicio_vigencia'] : $dados['inicio_vigencia']->toDateString();
        $dados['fim_vigencia'] = $dados['fim_vigencia'] instanceof Carbon ? $dados['fim_vigencia']->toDateString() : $dados['fim_vigencia'];
        $this->validar($dados);
        $this->validarSobreposicao($faixa->ebi_id, $dados, $faixaId);
        $faixa->update($dados);

        return $this->dto($faixa->refresh());
    }

    public function encerrarFaixa(string $faixaId): void
    {
        $faixa = AgendaEbi::query()->find($faixaId) ?? $this->naoEncontrada();
        if ($faixa->inicio_vigencia->toDateString() > today()->toDateString()
            && ! DB::table('sessao')->where('agenda_ebi_id', $faixaId)->exists()) {
            $faixa->delete();

            return;
        }

        $faixa->update(['fim_vigencia' => today()->subDay()->toDateString()]);
    }

    private function validar(array $dados): void
    {
        if ($dados['hora_fim'] <= $dados['hora_inicio']
            || (($dados['fim_vigencia'] ?? null) !== null && $dados['fim_vigencia'] < $dados['inicio_vigencia'])) {
            throw new ProblemException('agenda_invalida', 'Agenda inválida', 422);
        }
    }

    private function validarSobreposicao(string $ebiId, array $dados, ?string $ignorar = null): void
    {
        $query = AgendaEbi::query()->where('ebi_id', $ebiId)->where('dia_semana', $dados['dia_semana'])
            ->where('hora_inicio', '<', $dados['hora_fim'])->where('hora_fim', '>', $dados['hora_inicio'])
            ->where(function ($query) use ($dados): void {
                $query->whereNull('fim_vigencia')->orWhere('fim_vigencia', '>=', $dados['inicio_vigencia']);
            });
        if ($dados['fim_vigencia'] !== null) {
            $query->where('inicio_vigencia', '<=', $dados['fim_vigencia']);
        }
        if ($ignorar !== null) {
            $query->where('id', '!=', $ignorar);
        }
        if ($query->exists()) {
            throw new ProblemException('agenda_sobreposta', 'Faixa de agenda sobreposta', 422);
        }
    }

    private function dto(AgendaEbi $faixa): FaixaAgenda
    {
        $hoje = today()->toDateString();

        return new FaixaAgenda(
            $faixa->id,
            $faixa->ebi_id,
            $faixa->dia_semana,
            substr($faixa->hora_inicio, 0, 5),
            substr($faixa->hora_fim, 0, 5),
            $faixa->inicio_vigencia->toDateString(),
            $faixa->fim_vigencia?->toDateString(),
            $faixa->inicio_vigencia->toDateString() <= $hoje && ($faixa->fim_vigencia === null || $faixa->fim_vigencia->toDateString() >= $hoje),
        );
    }

    private function naoEncontrada(): never
    {
        throw new ProblemException('not_found', 'Recurso não encontrado', 404);
    }
}
