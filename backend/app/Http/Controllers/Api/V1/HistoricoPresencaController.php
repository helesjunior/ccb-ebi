<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Participantes\Contracts\ConsultarParticipante;
use App\Domain\Sessoes\Contracts\ConsultarPresenca;
use App\Domain\Sessoes\Contracts\HistoricoPresenca;
use App\Support\Presenca\AutorizacaoHistoricoPresenca;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoricoPresencaController
{
    public function __construct(
        private readonly ConsultarParticipante $participantes,
        private readonly ConsultarPresenca $consulta,
        private readonly AutorizacaoHistoricoPresenca $autorizacao,
    ) {}

    public function operador(Request $request, string $id): JsonResponse
    {
        $participante = $this->participantes->encontrar($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        $this->autorizacao->exigirOperador($request->attributes->get('contexto_atuacao'), $id, $participante->igrejaReferenciaId);

        return $this->historico($request, $id);
    }

    public function responsavel(Request $request, string $id): JsonResponse
    {
        $this->participantes->encontrar($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        $this->autorizacao->exigirResponsavel((string) $request->user()?->getAuthIdentifier(), $id);

        return $this->historico($request, $id);
    }

    private function historico(Request $request, string $id): JsonResponse
    {
        $filtros = $request->validate(['de' => ['nullable', 'date'], 'ate' => ['nullable', 'date', 'after_or_equal:de']]);
        $historico = $this->consulta->doParticipante($id, $filtros['de'] ?? null, $filtros['ate'] ?? null);

        return response()->json(['data' => array_map(fn (HistoricoPresenca $p) => [
            'id' => $p->id, 'sessao_id' => $p->sessaoId,
            'ebi' => ['id' => $p->ebiId, 'nome' => $p->ebiNome, 'timezone' => $p->ebiTimezone],
            'inicio_previsto' => $p->inicioPrevisto, 'status' => $p->status, 'registrado_em' => $p->registradoEm,
        ], $historico)]);
    }
}
