<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Agenda\Contracts\GerirAgenda;
use App\Domain\Identidade\Support\Operacoes;
use App\Http\Requests\Agenda\AgendaFaixaRequest;
use App\Http\Resources\AgendaFaixaResource;
use App\Support\ProblemException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AgendaController
{
    public function __construct(private readonly GerirAgenda $gerir) {}

    public function index(string $id): Response
    {
        return AgendaFaixaResource::collection($this->gerir->faixas($id))->response();
    }

    public function store(AgendaFaixaRequest $request, string $id): Response
    {
        return (new AgendaFaixaResource($this->gerir->criarFaixa($id, $request->validated())))
            ->response()->setStatusCode(201);
    }

    public function update(AgendaFaixaRequest $request, string $id): Response
    {
        $faixa = $this->gerir->faixa($id) ?? $this->naoEncontrada();
        Gate::authorize(Operacoes::AGENDA_GERENCIAR, ['ebi', $faixa->ebiId]);

        return (new AgendaFaixaResource($this->gerir->atualizarFaixa($id, $request->validated())))->response();
    }

    public function destroy(string $id): Response
    {
        $faixa = $this->gerir->faixa($id) ?? $this->naoEncontrada();
        Gate::authorize(Operacoes::AGENDA_GERENCIAR, ['ebi', $faixa->ebiId]);
        $this->gerir->encerrarFaixa($id);

        return response()->noContent();
    }

    private function naoEncontrada(): never
    {
        throw new ProblemException('not_found', 'Recurso não encontrado', 404);
    }
}
