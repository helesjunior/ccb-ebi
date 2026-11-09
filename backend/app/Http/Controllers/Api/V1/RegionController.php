<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Http\Requests\Organizacao\RegionalRequest;
use App\Http\Resources\RegionResource;
use App\Http\Resources\SectorResource;
use App\Support\PaginacaoCursor;
use App\Support\ProblemException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegionController
{
    public function __construct(private readonly GerirOrganizacao $gerir) {}

    public function index(Request $request): Response
    {
        $pagina = $this->gerir->listarRegionais(PaginacaoCursor::limit($request));

        return RegionResource::collection($pagina->items())
            ->additional(['meta' => ['proximo_cursor' => $pagina->nextCursor()?->encode()]])
            ->response();
    }

    public function store(RegionalRequest $request): Response
    {
        $regional = $this->gerir->criarRegional($request->validated());

        return (new RegionResource($regional))->response()->setStatusCode(201);
    }

    public function update(RegionalRequest $request, string $id): Response
    {
        $regional = $this->gerir->atualizarRegional($id, $request->validated());

        if ($regional === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Regional não encontrada');
        }

        return (new RegionResource($regional))->response();
    }

    public function sectors(Request $request, string $id): Response
    {
        $pagina = $this->gerir->listarSetoresDaRegional($id, PaginacaoCursor::limit($request));

        return SectorResource::collection($pagina->items())
            ->additional(['meta' => ['proximo_cursor' => $pagina->nextCursor()?->encode()]])
            ->response();
    }
}
