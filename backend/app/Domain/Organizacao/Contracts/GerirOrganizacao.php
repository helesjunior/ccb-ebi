<?php

namespace App\Domain\Organizacao\Contracts;

use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Superficie publica de leitura/escrita da Organizacao usada pela API.
 *
 * Devolve modelos do modulo (a camada HTTP le atributos sem importar as classes,
 * via resources), mantendo as tabelas de Organizacao encapsuladas (ADR-0002).
 */
interface GerirOrganizacao
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return CursorPaginator<int, Igreja>
     */
    public function listarIgrejas(array $filtros, int $limit): CursorPaginator;

    public function encontrarIgreja(string $id): ?Igreja;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function criarIgreja(array $dados): Igreja;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizarIgreja(string $id, array $dados): ?Igreja;

    /**
     * @return CursorPaginator<int, Regional>
     */
    public function listarRegionais(int $limit): CursorPaginator;

    public function encontrarRegional(string $id): ?Regional;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function criarRegional(array $dados): Regional;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizarRegional(string $id, array $dados): ?Regional;

    /**
     * @return CursorPaginator<int, Setor>
     */
    public function listarSetoresDaRegional(string $regionalId, int $limit): CursorPaginator;

    /**
     * @return CursorPaginator<int, Setor>
     */
    public function listarSetores(int $limit): CursorPaginator;

    public function encontrarSetor(string $id): ?Setor;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function criarSetor(array $dados): Setor;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizarSetor(string $id, array $dados): ?Setor;

    /**
     * @return CursorPaginator<int, Ebi>
     */
    public function listarEbis(int $limit): CursorPaginator;

    public function encontrarEbi(string $id): ?Ebi;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function criarEbi(array $dados): Ebi;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizarEbi(string $id, array $dados): ?Ebi;

    public function central(): ?CentralNacional;
}
