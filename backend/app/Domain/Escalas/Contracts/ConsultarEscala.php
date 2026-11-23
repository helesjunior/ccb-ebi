<?php

namespace App\Domain\Escalas\Contracts;

use App\Domain\Escalas\Models\Escala;
use App\Domain\Sessoes\Contracts\IntervaloSessao;

interface ConsultarEscala
{
    /**
     * Detalhe para a tela: sessao, equipe minima, membros com nome, vigencia e
     * conflitos. `null` quando a escala nao existe. A `EscalaVisao` diz o que
     * o leitor pode ver dos conflitos (CA-12).
     *
     * @return array<string, mixed>|null
     */
    public function detalhe(string $escalaId, EscalaVisao $visao): ?array;

    /** Uma das situacoes: cancelada, abaixo_do_minimo, aguardando_aprovacao, aprovada. */
    public function situacao(Escala $escala, IntervaloSessao $sessao): string;

    /**
     * Sessoes do periodo com a escala, quando houver.
     *
     * @return array<int, array{sessao_id: string, inicio_previsto: string, fim_previsto: string, sessao_status: string, escala_id: ?string, situacao: string, confirmados: int, membros: int}>
     */
    public function listarDoEbi(string $ebiId, string $inicio, string $fim): array;

    /** Referencia da escala (EBI dono e sessao), ou `null` quando nao existe. */
    public function referencia(string $escalaId): ?EscalaRef;

    /** Referencia da escala dona do membro, ou `null` quando o membro nao existe. */
    public function referenciaDoMembro(string $membroId): ?EscalaRef;

    /**
     * EBIs das sessoes que conflitam com a escala (CA-12), para a HTTP decidir
     * a visao dos nomes.
     *
     * @return array<int, string>
     */
    public function ebisEmConflito(string $escalaId): array;

    /**
     * Converte a escala gravada no corpo de resposta (`escala` do envelope).
     *
     * @return array<string, mixed>
     */
    public function detalheDaEscala(Escala $escala, EscalaVisao $visao): array;
}
