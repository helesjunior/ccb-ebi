<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Domain\Organizacao\Contracts\TransferirIgreja;
use App\Domain\Organizacao\Exceptions\SobreposicaoVigenciaException;
use App\Http\Requests\Organizacao\IgrejaRequest;
use App\Http\Requests\Organizacao\TransferenciaRequest;
use App\Http\Resources\ChurchResource;
use App\Support\PaginacaoCursor;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        // O vinculo vigente + setor ja vieram eager-loaded (sem N+1); so
        // projetamos o setor na relacao que o resource le.
        collect($pagina->items())->each(function ($igreja): void {
            $igreja->setRelation('setorVigente', $igreja->vinculacaoVigente?->setor);
        });

        return response()->json([
            'data' => ChurchResource::collection($pagina->items()),
            'meta' => ['proximo_cursor' => $pagina->nextCursor()?->encode()],
        ]);
    }

    public function store(IgrejaRequest $request): Response
    {
        $igreja = $this->gerir->criarIgreja($request->validated());
        $igreja->setRelation('setorVigente', null);

        return (new ChurchResource($igreja))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $id): Response
    {
        $igreja = $this->gerir->encontrarIgreja($id);

        if ($igreja === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Igreja não encontrada');
        }

        $igreja->setRelation('setorVigente', $this->consulta->setorVigente($igreja->id));

        return (new ChurchResource($igreja))->response();
    }

    public function update(IgrejaRequest $request, string $id): Response
    {
        if ($this->gerir->encontrarIgreja($id) === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Igreja não encontrada');
        }

        Gate::authorize(Operacoes::ORGANIZACAO_GERENCIAR, ['igreja', $id]);
        $igreja = $this->gerir->atualizarIgreja($id, $request->validated());

        if ($igreja === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Igreja não encontrada');
        }

        $igreja->setRelation('setorVigente', $this->consulta->setorVigente($igreja->id));

        return (new ChurchResource($igreja))->response();
    }

    public function transfer(TransferenciaRequest $request, string $id): Response
    {
        Gate::authorize(Operacoes::ORGANIZACAO_GERENCIAR, ['igreja', $id]);
        $sectorId = $request->string('sector_id')->toString();
        Gate::authorize(Operacoes::ORGANIZACAO_GERENCIAR, ['setor', $sectorId]);

        try {
            $contexto = $request->attributes->get('contexto_atuacao');

            $this->transferir->executar(
                $id,
                $sectorId,
                new \DateTimeImmutable($request->string('date')->toString()),
                $request->input('reason'),
                $contexto instanceof ContextoAtuacao ? $contexto->usuarioId : null,
            );
        } catch (SobreposicaoVigenciaException $e) {
            throw new ProblemException('transferencia_invalida', 'Transferência inválida', 422, $e->getMessage());
        }

        $igreja = $this->gerir->encontrarIgreja($id);

        if ($igreja === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Igreja não encontrada');
        }

        $igreja->setRelation('setorVigente', $this->consulta->setorVigente($igreja->id));

        return (new ChurchResource($igreja))->response()->setStatusCode(201);
    }
}
