<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\ConsultarEscala;
use App\Domain\Escalas\Contracts\GerirTrocaEscala;
use App\Domain\Identidade\Support\Operacoes;
use App\Http\Requests\Escalas\DecidirTrocaRequest;
use App\Http\Requests\Escalas\SolicitarTrocaRequest;
use App\Http\Support\AutorizacaoEscala;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trocas de vaga (RF-07). O pedido e o cancelamento sao do proprio membro; a
 * lista e a decisao passam pela matriz (`troca.decidir`) e pelo territorio, e
 * o dominio decide quem, de fato, decide a troca.
 */
class TrocaEscalaController
{
    public function __construct(
        private readonly AutorizacaoEscala $autorizacao,
        private readonly GerirTrocaEscala $trocas,
        private readonly ConsultarEscala $consulta,
    ) {}

    /** Trocas do EBI, filtravel por status. */
    public function index(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ebi_id' => ['required', 'string', 'max:26'],
            'status' => ['nullable', 'in:pendente,aprovada,recusada,cancelada'],
        ]);
        $this->autorizacao->garantir($request, Operacoes::TROCA_DECIDIR, (string) $dados['ebi_id']);

        return new JsonResponse(['data' => $this->trocas->listar((string) $dados['ebi_id'], $dados['status'] ?? null)]);
    }

    public function store(SolicitarTrocaRequest $request): JsonResponse
    {
        $troca = $this->trocas->solicitar(
            (string) $request->validated('escala_membro_id'),
            $request->validated('motivo') === null ? null : (string) $request->validated('motivo'),
            $this->autorId($request),
        );

        return new JsonResponse(['troca' => $this->trocas->representar($troca)], 201);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $troca = $this->trocas->cancelar($id, $this->autorId($request));

        return new JsonResponse(['troca' => $this->trocas->representar($troca)]);
    }

    /**
     * Decisao: matriz e territorio por `troca.decidir`; quem decide de fato
     * (chefe vigente, ou `adm_igreja` quando o pedido e da chefe) e decidido
     * no dominio, que tambem recusa o proprio solicitante.
     */
    public function update(DecidirTrocaRequest $request, string $id): JsonResponse
    {
        // Troca de vaga fora do alcance e 404, sem revelar a existencia.
        $referencia = $this->consulta->referenciaDaTroca($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Troca não encontrada.');
        $this->autorizacao->garantir($request, Operacoes::TROCA_DECIDIR, $referencia->ebiId);

        $troca = $this->trocas->decidir(
            $id,
            $request->decisao(),
            $request->substituto(),
            $request->motivo(),
            $this->autorizacao->contexto($request),
            $this->autorId($request),
        );

        return new JsonResponse(['troca' => $this->trocas->representar($troca)]);
    }

    private function autorId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
