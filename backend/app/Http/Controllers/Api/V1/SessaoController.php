<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\ConsultarEquipeEscalada;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\GerarSessoesPrevistas;
use App\Domain\Sessoes\Contracts\GerirSessaoEbi;
use App\Domain\Sessoes\Contracts\SessaoEbi;
use App\Http\Requests\Agenda\CancelarSessaoRequest;
use App\Http\Requests\Agenda\CorrigirSessaoRequest;
use App\Http\Requests\Agenda\EncerrarSessaoRequest;
use App\Http\Requests\Agenda\GerarSessoesRequest;
use App\Http\Resources\SessaoResource;
use App\Support\EquipeMinima\CalculadoraEquipeMinima;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SessaoController
{
    public function __construct(
        private readonly GerarSessoesPrevistas $gerador,
        private readonly ConsultarSessao $consulta,
        private readonly GerirSessaoEbi $gerir,
        private readonly ConsultarEbi $ebis,
        private readonly CalculadoraEquipeMinima $calculadora,
        private readonly ConsultarEquipeEscalada $escala,
    ) {}

    public function index(Request $request, string $id): Response
    {
        Gate::authorize(Operacoes::SESSAO_LER, ['ebi', $id]);
        $dados = $request->validate([
            'de' => ['required', 'date_format:Y-m-d'],
            'ate' => ['required', 'date_format:Y-m-d', 'after_or_equal:de'],
            'status' => ['nullable', Rule::in(['prevista', 'aberta', 'encerrada', 'cancelada'])],
        ]);

        $sessoes = $this->consulta->listar($id, $dados['de'], $dados['ate'], $dados['status'] ?? null);

        // A coleção passa o índice ao construtor; o segundo argumento aqui é a equipe mínima.
        return SessaoResource::collection(array_map(fn (SessaoEbi $sessao) => new SessaoResource($sessao), $sessoes))->response();
    }

    public function show(string $id): Response
    {
        $sessao = $this->autorizar($id, Operacoes::SESSAO_LER);
        $ebi = $this->ebis->dados($sessao->ebiId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        $equipe = $this->equipeMinima($sessao, $ebi->participantesPorAuxiliar);

        return (new SessaoResource($sessao, $equipe))->response();
    }

    /**
     * Com escala aprovada, quem conta sao os auxiliares da escala (confirmados
     * e vigentes na data); sem ela, o campo antigo da sessao.
     *
     * @return array<string, mixed>
     */
    private function equipeMinima(SessaoEbi $sessao, int $porAuxiliar): array
    {
        $daEscala = $this->escala->auxiliaresDaEscalaAprovada($sessao->id);
        $informados = $daEscala ?? $sessao->auxiliaresEscalados;
        $equipe = $this->calculadora->calcular($sessao->presentes, $porAuxiliar, $informados);

        return $equipe + ['origem' => $daEscala === null ? 'informado' : 'escala'];
    }

    public function open(Request $request, string $id): Response
    {
        $this->autorizar($id, Operacoes::SESSAO_GERENCIAR);

        return (new SessaoResource($this->gerir->abrir($id, (string) $request->user()?->getAuthIdentifier())))->response();
    }

    public function close(EncerrarSessaoRequest $request, string $id): Response
    {
        $this->autorizar($id, Operacoes::SESSAO_GERENCIAR);
        $dados = $request->validated();

        return (new SessaoResource($this->gerir->encerrar($id, (bool) $dados['realizada'], $dados['fim_real'] ?? null, (string) $request->user()?->getAuthIdentifier())))->response();
    }

    public function cancel(CancelarSessaoRequest $request, string $id): Response
    {
        $this->autorizar($id, Operacoes::SESSAO_GERENCIAR);

        return (new SessaoResource($this->gerir->cancelar($id, $request->validated('motivo'), (string) $request->user()?->getAuthIdentifier())))->response();
    }

    public function update(CorrigirSessaoRequest $request, string $id): Response
    {
        $sessao = $this->autorizar($id, Operacoes::SESSAO_GERENCIAR);

        // Com escala aprovada, o campo antigo de auxiliares deixa de valer.
        if ($request->has('auxiliares_escalados') && $this->escala->auxiliaresDaEscalaAprovada($id) !== null) {
            throw new ProblemException('escala_aprovada_define_equipe', 'Escala define a equipe', 422, 'Há escala aprovada para esta sessão; os auxiliares vêm da escala.');
        }

        return (new SessaoResource($this->gerir->corrigir($id, $request->validated(), (string) $request->user()?->getAuthIdentifier())))->response();
    }

    private function autorizar(string $id, string $operacao): SessaoEbi
    {
        $sessao = $this->consulta->detalhe($id) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        Gate::authorize($operacao, ['ebi', $sessao->ebiId]);

        return $sessao;
    }

    public function gerar(GerarSessoesRequest $request, string $id): JsonResponse
    {
        Gate::authorize(Operacoes::AGENDA_GERENCIAR, ['ebi', $id]);
        $ebi = $this->ebis->dados($id) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        $dados = $request->validated();
        $hoje = CarbonImmutable::now($ebi->timezone);
        $de = $dados['de'] ?? $hoje->toDateString();
        $ate = $dados['ate'] ?? CarbonImmutable::parse($de, $ebi->timezone)->addWeeks((int) ($dados['semanas'] ?? 4))->toDateString();

        return response()->json($this->gerador->gerar($id, $de, $ate), 201);
    }
}
