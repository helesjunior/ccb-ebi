<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Domain\Organizacao\Contracts\TransferirIgreja;
use App\Domain\Organizacao\Exceptions\SobreposicaoVigenciaException;
use App\Http\Requests\Organizacao\IgrejaRequest;
use App\Http\Requests\Organizacao\TransferenciaRequest;
use App\Http\Resources\ChurchResource;
use App\Support\Idempotencia;
use App\Support\PaginacaoCursor;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChurchController
{
    public function __construct(
        private readonly GerirOrganizacao $gerir,
        private readonly ConsultarOrganizacao $consulta,
        private readonly TransferirIgreja $transferir,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $pagina = $this->gerir->listarIgrejas(
            $request->only(['status', 'codigo']),
            PaginacaoCursor::limit($request),
        );

        $igrejas = collect($pagina->items())->map(function ($igreja) {
            $igreja->setRelation('setorVigente', $this->consulta->setorVigente($igreja->id));

            return $igreja;
        });

        return response()->json([
            'data' => ChurchResource::collection($igrejas),
            'meta' => ['proximo_cursor' => $pagina->nextCursor()?->encode()],
        ]);
    }

    public function store(IgrejaRequest $request): Response
    {
        return Idempotencia::executar($request, function () use ($request): Response {
            $igreja = $this->gerir->criarIgreja($request->validated());
            $igreja->setRelation('setorVigente', null);

            return (new ChurchResource($igreja))
                ->response()
                ->setStatusCode(201);
        });
    }

    public function show(Request $request, string $id): Response
    {
        $igreja = $this->gerir->encontrarIgreja($id);

        if ($igreja === null) {
            throw new ProblemException('not_found', 'Igreja nao encontrada', 404);
        }

        $igreja->setRelation('setorVigente', $this->consulta->setorVigente($igreja->id));

        return (new ChurchResource($igreja))->response();
    }

    public function update(IgrejaRequest $request, string $id): Response
    {
        $igreja = $this->gerir->atualizarIgreja($id, $request->validated());

        if ($igreja === null) {
            throw new ProblemException('not_found', 'Igreja nao encontrada', 404);
        }

        $igreja->setRelation('setorVigente', $this->consulta->setorVigente($igreja->id));

        return (new ChurchResource($igreja))->response();
    }

    public function transfer(TransferenciaRequest $request, string $id): Response
    {
        return Idempotencia::executar($request, function () use ($request, $id): Response {
            try {
                $this->transferir->executar(
                    $id,
                    $request->string('sector_id')->toString(),
                    new \DateTimeImmutable($request->string('date')->toString()),
                    $request->input('reason'),
                );
            } catch (SobreposicaoVigenciaException $e) {
                throw new ProblemException('transferencia_invalida', $e->getMessage(), 422);
            }

            $igreja = $this->gerir->encontrarIgreja($id);

            if ($igreja === null) {
                throw new ProblemException('not_found', 'Igreja nao encontrada', 404);
            }

            $igreja->setRelation('setorVigente', $this->consulta->setorVigente($igreja->id));

            return (new ChurchResource($igreja))->response()->setStatusCode(201);
        });
    }
}
