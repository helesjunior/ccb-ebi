<?php

namespace App\Domain\Escalas\Contracts;

use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Identidade\Contracts\ContextoDecisao;

/**
 * Troca de vaga na escala (RF-07): a pessoa escalada pede, o solicitante
 * cancela e quem decide e a chefe vigente (ou o `adm_igreja` quando o pedido
 * veio da propria chefe). Toda regra que depende do estado roda na trava.
 */
interface GerirTrocaEscala
{
    /**
     * Abre o pedido. Na trava: vaga de outra pessoa ou inexistente (404),
     * escala fora de `aprovada` (409 `escala_em_estado_invalido`), sessao fora
     * de `prevista` (409 `sessao_em_estado_invalido`), membro fora de
     * `escalado`/`confirmado` ou com troca pendente (409
     * `membro_em_estado_invalido`). O membro passa a `troca_solicitada` e
     * audita `escala.troca_solicitada`.
     */
    public function solicitar(string $membroId, ?string $motivo, string $usuarioId): SolicitacaoTrocaEscala;

    /**
     * O proprio solicitante desfaz o pedido. Outro usuario (404); troca fora
     * de `pendente` (409 `troca_ja_decidida`). O membro volta a
     * `statusAnterior()` e audita `escala.troca_cancelada`.
     */
    public function cancelar(string $trocaId, string $usuarioId): SolicitacaoTrocaEscala;

    /**
     * Decisao: `aprovar` exige substituto elegivel e vigente (422
     * `membro_inelegivel`), fora da escala (422 `membro_ja_escalado`); o
     * original vira `substituido` e nasce um membro novo `escalado` na mesma
     * funcao. `recusar` devolve o membro ao `statusAnterior()`. Audita
     * `escala.troca_decidida`.
     */
    public function decidir(string $trocaId, string $decisao, ?string $substitutoUsuarioId, ?string $motivo, ContextoDecisao $contexto, ?string $autorId): SolicitacaoTrocaEscala;

    /**
     * Trocas do EBI (opcionalmente por status), mais antigas primeiro. A
     * `situacao` e `expirada` quando a troca segue `pendente` mas a sessao nao
     * esta mais `prevista`.
     *
     * @return array<int, array{id: string, escala_id: string, sessao_id: string, membro_id: string, solicitante_id: string, solicitante_nome: string, motivo: ?string, status: string, situacao: string, criado_em: string}>
     */
    public function listar(string $ebiId, ?string $status): array;

    /**
     * Projecao da troca no corpo de resposta (`troca` do envelope).
     *
     * @return array{id: string, escala_membro_id: string, solicitante_id: string, motivo: ?string, status: string, situacao: string, substituto_membro_id: ?string, decidido_por: ?string, decidido_em: ?string, motivo_decisao: ?string, criado_em: ?string}
     */
    public function representar(SolicitacaoTrocaEscala $troca): array;
}
