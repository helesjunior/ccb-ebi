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
 * M6 (design §6.7): a igreja de referencia gateia a mesclagem — a coordenadora so
 * funde cadastros sob sua atuacao. O gate HTTP (`can:participante.mesclar`) faz o
 * RBAC; aqui aplica-se o ABAC territorial sobre **ambos** os cadastros (principal
 * e duplicado), via `AutorizacaoParticipante`.
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
        $contexto = $this->contexto($request);

        // MB: ambos os cadastros precisam estar sob a atuacao do ator.
        $this->exigirEscopo($contexto, $usuarioId, $id);
        $this->exigirEscopo($contexto, $usuarioId, $duplicadoId);

        $principal = $this->mesclagem->mesclar($usuarioId, $id, $duplicadoId);

        return (new ParticipanteResource($principal))->response();
    }

    public function undo(Request $request, string $id): JsonResponse
    {
        $this->exigirEscopo($this->contexto($request), (string) $request->user()?->getAuthIdentifier(), $id);

        $principal = $this->mesclagem->desfazer(
            (string) $request->user()?->getAuthIdentifier(),
            $id,
        );

        return (new ParticipanteResource($principal))->response();
    }

    private function exigirEscopo(?ContextoAtuacao $contexto, string $usuarioId, string $participanteId): void
    {
        $participante = $this->participantes->encontrar($participanteId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');

        $this->autorizacao->exigir($contexto, $usuarioId, $participante, 'participante.mesclar');
    }

    private function contexto(Request $request): ?ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        return $contexto instanceof ContextoAtuacao ? $contexto : null;
    }
}
