<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Http\Requests\Organizacao\SetorRequest;
use App\Http\Resources\SectorResource;
use App\Support\PaginacaoCursor;
use App\Support\ProblemException;
use Illuminate\Http\Request;
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
        $setor = $this->gerir->atualizarSetor($id, $request->validated());

        if ($setor === null) {
            throw new ProblemException('not_found', 'Setor nao encontrado', 404);
        }

        return (new SectorResource($setor))->response();
    }
}
