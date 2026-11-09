<?php

namespace App\Domain\Organizacao\Services;

use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Contracts\Pagination\CursorPaginator;

class GerenciadorOrganizacao implements GerirOrganizacao
{
    public function listarIgrejas(array $filtros, int $limit): CursorPaginator
    {
        return Igreja::query()
            ->when(isset($filtros['status']), fn ($q) => $q->where('status', $filtros['status']))
            ->when(isset($filtros['codigo']), fn ($q) => $q->where('codigo', $filtros['codigo']))
            ->orderBy('id')
            ->cursorPaginate($limit);
    }

    public function encontrarIgreja(string $id): ?Igreja
    {
        return Igreja::query()->find($id);
    }

    public function criarIgreja(array $dados): Igreja
    {
        return Igreja::query()->create($dados);
    }

    public function atualizarIgreja(string $id, array $dados): ?Igreja
    {
        $igreja = Igreja::query()->find($id);

        if ($igreja === null) {
            return null;
        }

        $igreja->fill($dados)->save();

        return $igreja;
    }

    public function listarRegionais(int $limit): CursorPaginator
    {
        return Regional::query()->orderBy('id')->cursorPaginate($limit);
    }

    public function criarRegional(array $dados): Regional
    {
        return Regional::query()->create($dados);
    }

    public function atualizarRegional(string $id, array $dados): ?Regional
    {
        $regional = Regional::query()->find($id);

        if ($regional === null) {
            return null;
        }

        $regional->fill($dados)->save();

        return $regional;
    }

    public function listarSetoresDaRegional(string $regionalId, int $limit): CursorPaginator
    {
        return Setor::query()
            ->where('regional_id', $regionalId)
            ->orderBy('id')
            ->cursorPaginate($limit);
    }

    public function listarSetores(int $limit): CursorPaginator
    {
        return Setor::query()->orderBy('id')->cursorPaginate($limit);
    }

    public function criarSetor(array $dados): Setor
    {
        return Setor::query()->create($dados);
    }

    public function atualizarSetor(string $id, array $dados): ?Setor
    {
        $setor = Setor::query()->find($id);

        if ($setor === null) {
            return null;
        }

        $setor->fill($dados)->save();

        return $setor;
    }

    public function listarEbis(int $limit): CursorPaginator
    {
        return Ebi::query()->orderBy('id')->cursorPaginate($limit);
    }

    public function encontrarEbi(string $id): ?Ebi
    {
        return Ebi::query()->find($id);
    }

    public function criarEbi(array $dados): Ebi
    {
        return Ebi::query()->create($dados);
    }

    public function atualizarEbi(string $id, array $dados): ?Ebi
    {
        $ebi = Ebi::query()->find($id);

        if ($ebi === null) {
            return null;
        }

        $ebi->fill($dados)->save();

        return $ebi;
    }

    public function central(): ?CentralNacional
    {
        return CentralNacional::query()->first();
    }
}
