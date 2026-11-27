<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Domain\Participantes\Contracts\GerirVinculoResponsavel;
use App\Http\Requests\Participantes\ListarVinculosRequest;
use App\Http\Requests\Participantes\SolicitarVinculoRequest;
use App\Http\Resources\ParticipanteResource;
use App\Http\Resources\ResponsavelParticipanteResource;
use App\Support\Participantes\AutorizacaoParticipante;
use App\Support\Participantes\EscopoAuditoria;
use App\Support\Participantes\VisibilidadeVinculoResponsavel;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vinculos do responsavel (RF-03). A criacao do vinculo parte do responsavel
 * autenticado; a decisao e da coordenadora (rota sob `vinculo.gerenciar`).
 */
class GuardianController
{
    public function __construct(
        private readonly GerirVinculoResponsavel $vinculos,
        private readonly GerirParticipante $participantes,
        private readonly AutorizacaoParticipante $autorizacao,
        private readonly EscopoAuditoria $escopos,
        private readonly VisibilidadeVinculoResponsavel $visibilidade,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function store(SolicitarVinculoRequest $request, string $participantId): JsonResponse
    {
        $usuarioId = (string) $request->user()?->getAuthIdentifier();

        $participante = $this->participantes->encontrar($participantId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');

        // H2: so quem tem `vinculo.gerenciar` sobre a igreja do participante, o
        // proprio responsavel (solicitacao nasce pendente) ou quem ja tem vinculo
        // pode abrir o pedido. Conhecer a matricula nao basta (RF-03.4).
        if (! $this->autorizacao->podeSolicitarVinculo($this->contexto($request), $usuarioId, $participante)) {
            throw new ProblemException('acesso_negado', 'Acesso negado', 403, 'Você não pode solicitar vínculo para este participante.');
        }

        $vinculo = $this->vinculos->solicitar(
            $usuarioId,
            $participantId,
            $request->validated(),
            $this->escopos->daIgreja($participante->igreja_referencia_id),
        );

        return (new ResponsavelParticipanteResource($vinculo))->response()->setStatusCode(201);
    }

    /** Dependentes do ator autenticado (autorizacao por propriedade, CA-04). */
    public function meusDependentes(Request $request): JsonResponse
    {
        $dependentes = $this->vinculos->dependentesDe((string) $request->user()?->getAuthIdentifier());

        return response()->json([
            'data' => ParticipanteResource::collection($dependentes)->resolve($request),
        ]);
    }

    /**
     * Pedidos de vinculo visiveis ao ator: territorio para quem gerencia,
     * propriedade para os demais (RF-03).
     */
    public function pedidos(ListarVinculosRequest $request): JsonResponse
    {
        $finalidade = (string) $request->validated('finalidade');
        $vinculos = $this->visibilidade->listar(
            (string) $request->user()?->getAuthIdentifier(),
            $this->contexto($request),
            (string) ($request->validated('validacao') ?? 'pendente'),
        );

        // Leitura identificavel (nome/matricula do participante): a finalidade
        // declarada vai para a trilha, como em `GET /participants` (ADR-0032).
        $this->auditoria->registrar([
            'acao' => 'vinculo.listado',
            'recurso' => 'vinculo',
            'ator_id' => (string) $request->user()?->getAuthIdentifier(),
            'origem' => 'api',
            'escopo' => EscopoAuditoria::doContexto($this->contexto($request)),
            'motivo' => $finalidade,
            'metadados_minimizados' => [
                'vinculo_ids' => array_map(static fn ($v): string => (string) $v->id, $vinculos),
            ],
            'ocorrido_em' => now(),
        ]);

        return response()->json([
            'data' => ResponsavelParticipanteResource::collection($vinculos)->resolve($request),
        ]);
    }

    private function contexto(Request $request): ?ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        return $contexto instanceof ContextoAtuacao ? $contexto : null;
    }
}
