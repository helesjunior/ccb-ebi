<?php

namespace App\Domain\Identidade\Contracts;

/**
 * Leitura da equipe de um EBI para os modulos que precisam dela (Escalas,
 * Sessoes): quem pode ser escalado, por quanto tempo cada papel vale e quem e a
 * coordenadora chefe vigente. Devolve apenas dados de exibicao, nunca modelos.
 */
interface ConsultarEquipeEbi
{
    /** Papeis que compoem a equipe do EBI. */
    public const PAPEIS_EQUIPE = ['coordenadora', 'coordenadora_chefe', 'auxiliar'];

    /**
     * Membros vigentes: papeis de `PAPEIS_EQUIPE` com escopo `ebi:$ebiId` ou
     * `igreja:$igrejaId`, agrupados por usuario e ordenados por nome.
     *
     * @return array<int, array{usuario_id: string, nome: string, papeis: array<int, string>}>
     */
    public function equipe(string $ebiId, string $igrejaId): array;

    /**
     * Historico de papeis dos usuarios dados, incluindo vinculos encerrados.
     *
     * @param  array<int, string>  $usuarioIds
     * @return array<string, array<int, array{papel: string, inicio: string, fim: ?string}>>
     */
    public function vigencias(array $usuarioIds, string $ebiId, string $igrejaId): array;

    /** Usuario da coordenadora chefe vigente do EBI, ou null. */
    public function chefeVigente(string $ebiId): ?string;

    /**
     * @param  array<int, string>  $usuarioIds
     * @return array<string, string>
     */
    public function nomes(array $usuarioIds): array;
}
