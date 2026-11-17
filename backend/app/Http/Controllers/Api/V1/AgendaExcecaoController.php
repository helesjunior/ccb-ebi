<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Agenda\Contracts\GerirAgenda;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Sessoes\Contracts\CancelarSessoesPrevistas;
use App\Http\Requests\Agenda\AgendaExcecaoRequest;
use App\Http\Resources\AgendaExcecaoResource;
use App\Support\ProblemException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AgendaExcecaoController
{
    public function __construct(
        private readonly GerirAgenda $gerir,
        private readonly CancelarSessoesPrevistas $cancelar,
    ) {}

    public function index(Request $request, string $id): Response
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
        ]);

        return AgendaExcecaoResource::collection($this->gerir->excecoes($id, $filtros['de'] ?? null, $filtros['ate'] ?? null))->response();
    }

    public function store(AgendaExcecaoRequest $request, string $id): Response
    {
        $excecao = DB::transaction(function () use ($request, $id) {
            $excecao = $this->gerir->criarExcecao($id, $request->validated());
            if (in_array($excecao->tipo, ['feriado', 'cancelamento'], true)) {
                $this->cancelar->naData($id, $excecao->data, $excecao->motivo, (string) $request->user()->getAuthIdentifier());
            }

            return $excecao;
        });

        return (new AgendaExcecaoResource($excecao))->response()->setStatusCode(201);
    }

    public function destroy(string $id): Response
    {
        $excecao = $this->gerir->excecao($id) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        Gate::authorize(Operacoes::AGENDA_GERENCIAR, ['ebi', $excecao->ebiId]);
        $this->gerir->removerExcecao($id);

        return response()->noContent();
    }
}
