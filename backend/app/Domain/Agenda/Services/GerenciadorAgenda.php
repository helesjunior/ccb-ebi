<?php

namespace App\Domain\Agenda\Services;

use App\Domain\Agenda\Contracts\ExcecaoAgenda;
use App\Domain\Agenda\Contracts\FaixaAgenda;
use App\Domain\Agenda\Contracts\GerirAgenda;
use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Agenda\Models\AgendaExcecao;
use App\Support\ProblemException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GerenciadorAgenda implements GerirAgenda
{
    public function excecoes(string $ebiId, ?string $de = null, ?string $ate = null): array
    {
        return AgendaExcecao::query()->where('ebi_id', $ebiId)
            ->when($de !== null, fn ($q) => $q->whereDate('data', '>=', $de))
            ->when($ate !== null, fn ($q) => $q->whereDate('data', '<=', $ate))
            ->orderBy('data')->orderBy('id')->get()
            ->map(fn (AgendaExcecao $excecao): ExcecaoAgenda => $this->excecaoDto($excecao))->all();
    }

    public function excecao(string $id): ?ExcecaoAgenda
    {
        $excecao = AgendaExcecao::query()->find($id);

        return $excecao ? $this->excecaoDto($excecao) : null;
    }

    public function criarExcecao(string $ebiId, array $dados): ExcecaoAgenda
    {
        $especial = ($dados['tipo'] ?? null) === AgendaExcecao::TIPO_HORARIO_ESPECIAL;
        $dados = Validator::make($dados, [
            'tipo' => ['required', Rule::in([AgendaExcecao::TIPO_FERIADO, AgendaExcecao::TIPO_CANCELAMENTO, AgendaExcecao::TIPO_HORARIO_ESPECIAL])],
            'data' => ['required', 'date_format:Y-m-d'],
            'motivo' => ['required', 'string'],
            'agenda_ebi_id' => $especial
                ? ['required', 'ulid', Rule::exists('agenda_ebi', 'id')->where('ebi_id', $ebiId)]
                : ['nullable', Rule::in([null])],
            'hora_inicio' => $especial ? ['required', 'date_format:H:i'] : ['nullable', Rule::in([null])],
            'hora_fim' => $especial ? ['required', 'date_format:H:i', 'after:hora_inicio'] : ['nullable', Rule::in([null])],
        ])->validate();
        $dados = array_merge(['agenda_ebi_id' => null, 'hora_inicio' => null, 'hora_fim' => null], $dados);

        $duplicada = AgendaExcecao::query()->whereDate('data', $dados['data']);
        if ($especial) {
            $duplicada->where('agenda_ebi_id', $dados['agenda_ebi_id']);
        } else {
            $duplicada->where('ebi_id', $ebiId)->where('tipo', $dados['tipo'])->whereNull('agenda_ebi_id');
        }
        if ($duplicada->exists()) {
            $this->excecaoDuplicada();
        }

        try {
            return DB::transaction(fn (): ExcecaoAgenda => $this->excecaoDto(AgendaExcecao::query()->create([...$dados, 'ebi_id' => $ebiId])));
        } catch (QueryException $e) {
            if ($e instanceof UniqueConstraintViolationException) {
                $this->excecaoDuplicada();
            }

            throw $e;
        }
    }

    public function removerExcecao(string $excecaoId): void
    {
        $excecao = AgendaExcecao::query()->find($excecaoId) ?? $this->naoEncontrada();
        $excecao->delete();
    }

    private function excecaoDuplicada(): never
    {
        throw new ProblemException('agenda_excecao_duplicada', 'Exceção de agenda duplicada', 422);
    }

    private function excecaoDto(AgendaExcecao $excecao): ExcecaoAgenda
    {
        return new ExcecaoAgenda(
            $excecao->id, $excecao->ebi_id, $excecao->agenda_ebi_id,
            $excecao->data->toDateString(), $excecao->tipo,
            $excecao->hora_inicio === null ? null : substr($excecao->hora_inicio, 0, 5),
            $excecao->hora_fim === null ? null : substr($excecao->hora_fim, 0, 5),
            $excecao->motivo,
        );
    }

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
        $inicio = $faixa->inicio_vigencia->toDateString();
        if ($inicio > today()->toDateString()
            && ! DB::table('sessao')->where('agenda_ebi_id', $faixaId)->exists()) {
            $faixa->delete();

            return;
        }

        // Faixa futura que ja gerou sessoes fica inerte em vez de vencer antes
        // de comecar (o que violaria a janela de vigencia).
        $fim = today()->subDay()->toDateString();
        $faixa->update(['fim_vigencia' => $fim < $inicio ? $inicio : $fim]);
    }

    /** @param array<string, mixed> $dados */
    private function validar(array $dados): void
    {
        if ($dados['hora_fim'] <= $dados['hora_inicio']
            || (($dados['fim_vigencia'] ?? null) !== null && $dados['fim_vigencia'] < $dados['inicio_vigencia'])) {
            throw new ProblemException('agenda_invalida', 'Agenda inválida', 422);
        }
    }

    /** @param array<string, mixed> $dados */
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
