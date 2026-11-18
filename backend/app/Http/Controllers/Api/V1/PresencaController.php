<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Participantes\Contracts\AvaliarElegibilidade;
use App\Domain\Participantes\Contracts\ConsultarParticipante;
use App\Domain\Participantes\Contracts\ParticipanteResumo;
use App\Domain\Sessoes\Contracts\ConsultarPresenca;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\EstornarPresenca;
use App\Domain\Sessoes\Contracts\RegistrarPresenca;
use App\Domain\Sessoes\Contracts\SessaoEbi;
use App\Http\Requests\Agenda\ContingenciaPresencaRequest;
use App\Http\Requests\Agenda\EstornarPresencaRequest;
use App\Http\Requests\Agenda\RegistrarPresencaRequest;
use App\Http\Resources\PresencaResource;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PresencaController
{
    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly ConsultarPresenca $consulta,
        private readonly RegistrarPresenca $registrador,
        private readonly ConsultarParticipante $participantes,
        private readonly ConsultarEbi $ebis,
        private readonly ConsultarOrganizacao $organizacao,
        private readonly AvaliarElegibilidade $elegibilidade,
        private readonly RegistrarEvento $auditoria,
        private readonly EstornarPresenca $estornador,
    ) {}

    public function participantes(Request $request, string $id): JsonResponse
    {
        $sessao = $this->sessao($id);
        if (! Gate::any([Operacoes::PRESENCA_REGISTRAR, Operacoes::PRESENCA_LER], ['ebi', $sessao->ebiId])) {
            throw new AuthorizationException;
        }
        $finalidade = $this->finalidade($request);
        $termo = trim((string) $request->query('busca', ''));
        if (mb_strlen($termo) < 3) {
            throw new ProblemException('busca_curta', 'Dados inválidos', 422);
        }
        $ebi = $this->ebis->dados($sessao->ebiId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        $candidatos = $this->participantes->buscar($termo, 20);
        $igrejaIds = array_values(array_unique(array_filter(array_map(fn (ParticipanteResumo $p) => $p->igrejaReferenciaId, $candidatos))));
        $nomes = $this->organizacao->nomesDeIgrejas($igrejaIds);
        $data = array_map(function (ParticipanteResumo $p) use ($sessao, $ebi, $nomes): array {
            $resultado = $this->elegibilidade->avaliarPorId($p->id, CarbonImmutable::parse($sessao->inicioPrevisto), $ebi->timezone);

            return [
                'id' => $p->id, 'nome' => $p->nome, 'matricula' => $p->matricula,
                'igreja_referencia' => $p->igrejaReferenciaId === null ? null : ['id' => $p->igrejaReferenciaId, 'nome' => $nomes[$p->igrejaReferenciaId] ?? null],
                'elegivel' => $resultado->permitido, 'motivo_bloqueio' => $resultado->permitido ? null : $resultado->motivo,
            ];
        }, $candidatos);
        $this->auditoria->registrar([
            'acao' => 'participante.buscado', 'recurso' => 'participante', 'ator_id' => (string) $request->user()?->getAuthIdentifier(),
            'origem' => 'api', 'escopo' => "ebi:{$sessao->ebiId}", 'motivo' => $finalidade,
            'metadados_minimizados' => ['termo' => $termo, 'sessao_id' => $id, 'participante_ids' => array_column($data, 'id')],
        ]);

        return response()->json(['data' => $data]);
    }

    public function store(RegistrarPresencaRequest $request, string $id): JsonResponse
    {
        $sessao = $this->sessao($id);
        Gate::authorize(Operacoes::PRESENCA_REGISTRAR, ['ebi', $sessao->ebiId]);
        $dados = $request->validated();
        $resultado = $this->registrador->registrar($id, $dados['participante_id'], (string) $request->user()?->getAuthIdentifier(), $dados['origem'] ?? 'web');

        return response()->json([
            'presenca' => (new PresencaResource($resultado->presenca))->resolve($request), 'aviso' => $resultado->aviso,
        ], $resultado->nova ? 201 : 200);
    }

    public function contingencia(ContingenciaPresencaRequest $request, string $id): JsonResponse
    {
        $sessao = $this->sessao($id);
        Gate::authorize(Operacoes::SESSAO_GERENCIAR, ['ebi', $sessao->ebiId]);
        $dados = $request->validated();
        $resultado = $this->registrador->registrarContingencia($id, $dados['participante_id'], $dados['motivo'], (string) $request->user()?->getAuthIdentifier());

        return response()->json([
            'presenca' => (new PresencaResource($resultado->presenca))->resolve($request),
        ], $resultado->nova ? 201 : 200);
    }

    public function index(Request $request, string $id): JsonResponse
    {
        $sessao = $this->sessao($id);
        Gate::authorize(Operacoes::PRESENCA_LER, ['ebi', $sessao->ebiId]);

        return response()->json(['data' => PresencaResource::collection($this->consulta->daSessao($id))->resolve($request)]);
    }

    public function estornar(EstornarPresencaRequest $request, string $id): JsonResponse
    {
        $ebiId = $this->consulta->ebiDaPresenca($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        Gate::authorize(Operacoes::PRESENCA_ESTORNAR, ['ebi', $ebiId]);
        $presenca = $this->estornador->estornar($id, $request->validated('motivo'), (string) $request->user()?->getAuthIdentifier());

        return response()->json((new PresencaResource($presenca))->resolve($request));
    }

    private function sessao(string $id): SessaoEbi
    {
        return $this->sessoes->detalhe($id) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
    }

    private function finalidade(Request $request): string
    {
        $finalidade = trim((string) ($request->query('finalidade', $request->header('X-Finalidade', ''))));

        if ($finalidade === '') {
            throw new ProblemException(
                'finalidade_obrigatoria',
                'Dados inválidos',
                422,
                'A leitura de dado identificável exige a finalidade declarada.',
            );
        }

        return $finalidade;
    }
}
