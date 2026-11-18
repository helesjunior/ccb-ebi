<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Http\Requests\Organizacao\EbiRequest;
use App\Http\Resources\EbiResource;
use App\Support\PaginacaoCursor;
use App\Support\ProblemException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EbiController
{
    public function __construct(private readonly GerirOrganizacao $gerir) {}

    public function index(Request $request): Response
    {
        $pagina = $this->gerir->listarEbis(PaginacaoCursor::limit($request));

        return EbiResource::collection($pagina->items())
            ->additional(['meta' => ['proximo_cursor' => $pagina->nextCursor()?->encode()]])
            ->response();
    }

    public function store(EbiRequest $request): Response
    {
        $ebi = $this->gerir->criarEbi($request->validated());

        return (new EbiResource($ebi))->response()->setStatusCode(201);
    }

    public function update(EbiRequest $request, string $id): Response
    {
        if ($this->gerir->encontrarEbi($id) === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'EBI não encontrada');
        }

        Gate::authorize(Operacoes::ORGANIZACAO_GERENCIAR, ['ebi', $id]);
        $dados = $request->validated();
        if (isset($dados['igreja_id'])) {
            // Reparentar exige alcance tambem sobre a igreja de destino.
            Gate::authorize(Operacoes::ORGANIZACAO_GERENCIAR, ['igreja', (string) $dados['igreja_id']]);
        }
        $ebi = $this->gerir->atualizarEbi($id, $dados);

        if ($ebi === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'EBI não encontrada');
        }

        return (new EbiResource($ebi))->response();
    }
}
