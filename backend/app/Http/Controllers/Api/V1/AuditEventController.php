<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auditoria\Contracts\ConsultarAuditoria;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Http\Resources\EventoAuditoriaResource;
use App\Support\PaginacaoCursor;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditEventController
{
    public function __construct(private readonly ConsultarAuditoria $consulta) {}

    public function index(Request $request): JsonResponse
    {
        $contexto = $this->contexto($request);

        $pagina = $this->consulta->listar(
            $contexto->papelCodigo,
            $contexto->tipoEscopo,
            $contexto->escopoId,
            $request->only(['recurso', 'ator_id', 'de', 'ate']),
            PaginacaoCursor::limit($request),
        );

        return response()->json([
            'data' => EventoAuditoriaResource::collection($pagina->items())->resolve($request),
            'meta' => ['proximo_cursor' => $pagina->nextCursor()?->encode()],
        ]);
    }

    private function contexto(Request $request): ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao) {
            throw new ProblemException('sem_contexto', 'Acesso negado', 403, 'Sem contexto de atuação vigente.');
        }

        return $contexto;
    }
}
