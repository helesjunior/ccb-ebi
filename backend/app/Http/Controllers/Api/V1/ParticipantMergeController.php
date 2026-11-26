<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Participantes\Contracts\GerirMesclagem;
use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Http\Requests\Participantes\MesclarParticipanteRequest;
use App\Http\Resources\ParticipanteResource;
use App\Support\Participantes\AutorizacaoParticipante;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mesclagem de duplicados (CA-27): funde e desfaz, sempre auditado e reversivel.
 * Rotas sob `can:participante.mesclar`.
 *
 * O gate HTTP (`can:participante.mesclar`) faz o RBAC; aqui aplica-se o escopo
 * do ADR-0027 via `AutorizacaoParticipante::exigirLigados`: a coordenadora precisa
 * dos dois cadastros ligados a um EBI dela; a coordenadora chefe, de um.
 */
class ParticipantMergeController
{
    public function __construct(
        private readonly GerirMesclagem $mesclagem,
        private readonly GerirParticipante $participantes,
        private readonly AutorizacaoParticipante $autorizacao,
    ) {}

    public function store(MesclarParticipanteRequest $request, string $id): JsonResponse
    {
        $usuarioId = (string) $request->user()?->getAuthIdentifier();
        $duplicadoId = (string) $request->validated('duplicado_id');

        $principal = $this->participantes->encontrar($id) ?? $this->naoEncontrado();
        $duplicado = $this->participantes->encontrar($duplicadoId) ?? $this->naoEncontrado();

        $this->autorizacao->exigirLigados($this->contexto($request), [$principal, $duplicado], 'participante.mesclar');

        $principal = $this->mesclagem->mesclar($usuarioId, $id, $duplicadoId);

        return (new ParticipanteResource($principal))->response();
    }

    public function undo(Request $request, string $id): JsonResponse
    {
        $principal = $this->participantes->encontrar($id) ?? $this->naoEncontrado();
        $duplicadoId = $principal->snapshot_mesclagem['duplicado']['id'] ?? null;
        $duplicado = is_string($duplicadoId) ? $this->participantes->encontrar($duplicadoId) : null;

        // Desfazer altera os dois cadastros: confere os dois.
        $this->autorizacao->exigirLigados(
            $this->contexto($request),
            $duplicado !== null ? [$principal, $duplicado] : [$principal],
            'participante.mesclar',
        );

        $principal = $this->mesclagem->desfazer(
            (string) $request->user()?->getAuthIdentifier(),
            $id,
        );

        return (new ParticipanteResource($principal))->response();
    }

    private function naoEncontrado(): never
    {
        throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');
    }

    private function contexto(Request $request): ?ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        return $contexto instanceof ContextoAtuacao ? $contexto : null;
    }
}
