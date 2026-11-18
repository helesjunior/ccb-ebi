<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Http\Requests\Organizacao\SetorRequest;
use App\Http\Resources\SectorResource;
use App\Support\PaginacaoCursor;
use App\Support\ProblemException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SectorController
{
    public function __construct(private readonly GerirOrganizacao $gerir) {}

    public function index(Request $request): Response
    {
        $pagina = $this->gerir->listarSetores(PaginacaoCursor::limit($request));

        return SectorResource::collection($pagina->items())
            ->additional(['meta' => ['proximo_cursor' => $pagina->nextCursor()?->encode()]])
            ->response();
    }

    public function store(SetorRequest $request): Response
    {
        $setor = $this->gerir->criarSetor($request->validated());

        return (new SectorResource($setor))->response()->setStatusCode(201);
    }

    public function update(SetorRequest $request, string $id): Response
    {
        Gate::authorize(Operacoes::ORGANIZACAO_GERENCIAR, ['setor', $id]);
        $dados = $request->validated();
        if (isset($dados['regional_id'])) {
            Gate::authorize(Operacoes::ORGANIZACAO_GERENCIAR, ['regional', (string) $dados['regional_id']]);
        }
        $setor = $this->gerir->atualizarSetor($id, $dados);

        if ($setor === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Setor não encontrado');
        }

        return (new SectorResource($setor))->response();
    }
}
