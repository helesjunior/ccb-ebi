<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\IntervaloSessao;

/**
 * CA-12: a mesma pessoa escalada em sessoes que se sobrepoem. O conflito e
 * so aviso (nunca bloqueia): o horario aparece sempre e os nomes apenas para
 * quem alcanca o outro EBI, ou para a propria pessoa. A decisao de alcance
 * vem pronta da camada HTTP (`$leitorVeOutroEbi`), que tem o contexto de
 * atuacao.
 */
class DetectorConflito
{
    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly ConsultarOrganizacao $organizacao,
        private readonly ConsultarEbi $ebis,
    ) {}

    /**
     * Sessoes de outras escalas da pessoa que se sobrepoem a `$sessao`,
     * ignorando `$escalaIdIgnorada`; `substituido` e `cancelada` nao contam.
     *
     * @param  array<int, string>  $usuarioIds
     * @return array<string, array<int, IntervaloSessao>>
     */
    public function conflitos(array $usuarioIds, IntervaloSessao $sessao, string $escalaIdIgnorada): array
    {
        $porSessao = [];

        // Escalas (de outras sessoes) em que a pessoa segue escalada.
        /** @var iterable<int, object{usuario_id: string, sessao_id: string}> $linhas */
        $linhas = EscalaMembro::query()
            ->whereIn('escala_membro.usuario_id', $usuarioIds)
            ->where('escala_membro.status', '!=', EscalaMembro::STATUS_SUBSTITUIDO)
            ->join('escala', 'escala.id', '=', 'escala_membro.escala_id')
            ->where('escala.id', '!=', $escalaIdIgnorada)
            ->get(['escala_membro.usuario_id', 'escala.sessao_id']);

        foreach ($linhas as $linha) {
            $porSessao[(string) $linha->sessao_id][] = (string) $linha->usuario_id;
        }

        $intervalos = $this->sessoes->intervalos(array_keys($porSessao));
        $resultado = [];

        foreach ($porSessao as $sessaoId => $ids) {
            $outra = $intervalos[$sessaoId] ?? null;

            if ($outra === null || $outra->status === 'cancelada' || ! $this->sobrepoe($sessao, $outra)) {
                continue;
            }

            foreach ($ids as $usuarioId) {
                $resultado[$usuarioId][] = $outra;
            }
        }

        return $resultado;
    }

    /**
     * EBIs dos conflitos: a camada HTTP decide, por EBI, se o leitor os ve.
     *
     * @param  array<string, array<int, IntervaloSessao>>  $conflitos
     * @return array<int, string>
     */
    public function ebisDosConflitos(array $conflitos): array
    {
        $ids = [];
        foreach ($conflitos as $lista) {
            foreach ($lista as $outra) {
                $ids[$outra->ebiId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Projecao para a API. `$nomesLivres` ja vem decidido pela camada HTTP
     * (leitor alcanca o outro EBI ou o conflito e da propria pessoa); sem ele
     * so o horario aparece.
     *
     * @param  array<int, IntervaloSessao>  $intervalos
     * @return array<int, array{sessao_id: ?string, inicio: string, fim: string, igreja_nome: ?string, ebi_nome: ?string}>
     */
    public function apresentar(array $intervalos, bool $nomesLivres): array
    {
        $igrejas = $this->nomesDeIgrejas($intervalos);
        $ebis = $this->nomesDeEbis($intervalos);

        return array_map(fn (IntervaloSessao $outra): array => [
            'sessao_id' => $nomesLivres ? $outra->id : null,
            'inicio' => $outra->inicioPrevisto->toIso8601String(),
            'fim' => $outra->fimPrevisto->toIso8601String(),
            'igreja_nome' => $nomesLivres ? ($igrejas[$outra->igrejaId] ?? null) : null,
            'ebi_nome' => $nomesLivres ? ($ebis[$outra->ebiId] ?? null) : null,
        ], $intervalos);
    }

    public function sobrepoe(IntervaloSessao $a, IntervaloSessao $b): bool
    {
        return $a->inicioPrevisto->lessThan($b->fimPrevisto)
            && $b->inicioPrevisto->lessThan($a->fimPrevisto);
    }

    /**
     * @param  array<int, IntervaloSessao>  $intervalos
     * @return array<string, string>
     */
    private function nomesDeIgrejas(array $intervalos): array
    {
        $ids = array_unique(array_map(fn (IntervaloSessao $outra): string => $outra->igrejaId, $intervalos));

        return $ids === [] ? [] : $this->organizacao->nomesDeIgrejas(array_values($ids));
    }

    /**
     * @param  array<int, IntervaloSessao>  $intervalos
     * @return array<string, string>
     */
    private function nomesDeEbis(array $intervalos): array
    {
        $nomes = [];
        foreach (array_unique(array_map(fn (IntervaloSessao $outra): string => $outra->ebiId, $intervalos)) as $ebiId) {
            $dados = $this->ebis->dados($ebiId);
            if ($dados !== null) {
                $nomes[$ebiId] = $dados->nome;
            }
        }

        return $nomes;
    }
}
