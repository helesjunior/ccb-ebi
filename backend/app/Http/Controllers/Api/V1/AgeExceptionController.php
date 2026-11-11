<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Participantes\Contracts\GerirExcecaoEtaria;
use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Http\Requests\Participantes\DecidirExcecaoRequest;
use App\Http\Requests\Participantes\SolicitarExcecaoRequest;
use App\Http\Resources\ExcecaoEtariaResource;
use App\Support\Participantes\AutorizacaoParticipante;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;

/**
 * Excecao inclusiva (FL-06, ADR-0012): solicitacao pelo responsavel; decisao
 * pela coordenadora (`can:excecao.decidir`).
 */
class AgeExceptionController
{
    public function __construct(
        private readonly GerirExcecaoEtaria $excecoes,
        private readonly GerirParticipante $participantes,
        private readonly AutorizacaoParticipante $autorizacao,
    ) {}

    public function store(SolicitarExcecaoRequest $request, string $participantId): JsonResponse
    {
        $usuarioId = (string) $request->user()?->getAuthIdentifier();

        $participante = $this->participantes->encontrar($participantId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');

        // H2: a excecao so pode ser pedida pelo responsavel com vinculo aprovado
        // (propriedade, CA-04) ou pela coordenadora com escopo (`excecao.decidir`).
        $this->autorizacao->exigir(
            $this->contexto($request),
            $usuarioId,
            $participante,
            'excecao.decidir',
        );

        $excecao = $this->excecoes->solicitar(
            $usuarioId,
            $participantId,
            $request->validated(),
        );

        return (new ExcecaoEtariaResource($excecao))->response()->setStatusCode(201);
    }

    public function update(DecidirExcecaoRequest $request, string $id): JsonResponse
    {
        $validado = $request->validated();

        $excecao = $this->excecoes->decidir(
            (string) $request->user()?->getAuthIdentifier(),
            $id,
            (string) $validado['decisao'],
            $validado,
        );

        return (new ExcecaoEtariaResource($excecao))->response();
    }

    private function contexto(SolicitarExcecaoRequest $request): ?ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        return $contexto instanceof ContextoAtuacao ? $contexto : null;
    }
}
