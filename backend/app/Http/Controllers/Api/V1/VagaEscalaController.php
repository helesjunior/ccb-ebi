<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\ConsultarEscala;
use App\Domain\Escalas\Contracts\ConsultarVagaEscala;
use App\Domain\Escalas\Contracts\EscalaRef;
use App\Domain\Escalas\Contracts\GerirVagaEscala;
use App\Domain\Escalas\Contracts\ProjecaoVaga;
use App\Domain\Escalas\Services\DonaDaEscala;
use App\Domain\Identidade\Support\Operacoes;
use App\Http\Requests\Escalas\AbrirVagaRequest;
use App\Http\Support\AutorizacaoEscala;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Vagas de escala (E07, evolucao). A escrita e de quem gere a escala; a
 * oferta e da propria pessoa. A projecao de cada resposta segue a decisao 9 da
 * spec: sem `escala.ler` so a propria oferta, com ele a contagem e quem foi
 * escolhido, e a dona e a supervisao veem os nomes.
 */
class VagaEscalaController
{
    /** Mesmo teto das listagens de sessao. */
    private const DIAS_MAXIMOS = 92;

    public function __construct(
        private readonly AutorizacaoEscala $autorizacao,
        private readonly ConsultarEscala $escalas,
        private readonly ConsultarVagaEscala $consulta,
        private readonly GerirVagaEscala $gerir,
        private readonly DonaDaEscala $dona,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ebi_id' => ['required', 'string', 'max:26'],
            'status' => ['nullable', 'in:aberta,preenchida,cancelada'],
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
        ]);
        $this->autorizacao->garantir($request, Operacoes::ESCALA_LER, $dados['ebi_id']);

        [$inicio, $fim] = $this->janela($dados['inicio'] ?? null, $dados['fim'] ?? null);

        return new JsonResponse(['data' => $this->consulta->doEbi(
            $dados['ebi_id'],
            $inicio,
            $fim,
            $dados['status'] ?? null,
            ProjecaoVaga::Leitor,
        )]);
    }

    /** Vagas abertas para quem se oferecer: dos EBIs da pessoa, sem nomes alheios. */
    public function minhas(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
        ]);

        $inicio = CarbonImmutable::parse($dados['inicio'], 'America/Sao_Paulo');
        $fim = CarbonImmutable::parse($dados['fim'], 'America/Sao_Paulo');

        if ($fim->gt($inicio->addDays(self::DIAS_MAXIMOS))) {
            throw ValidationException::withMessages([
                'fim' => 'O intervalo não pode exceder '.self::DIAS_MAXIMOS.' dias.',
            ]);
        }

        $usuarioId = $this->autorId($request);

        return new JsonResponse(['data' => $this->consulta->paraMim(
            $usuarioId,
            $this->dona->escoposDeEquipe($usuarioId),
            $dados['inicio'],
            $dados['fim'],
        )]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $referencia = $this->escalas->referenciaDaVaga($id) ?? throw $this->naoEncontrada();

        return new JsonResponse(['data' => $this->consulta->uma($id, $this->projecao($request, $referencia), $this->autorId($request)) ?? throw $this->naoEncontrada()]);
    }

    public function store(AbrirVagaRequest $request, string $escalaId): JsonResponse
    {
        $referencia = $this->referencia($escalaId);
        $this->autorizacao->garantir($request, Operacoes::ESCALA_GERENCIAR, $referencia->ebiId);

        $vagaId = $this->gerir->abrir(
            $escalaId,
            $request->validated('substitui_membro_id'),
            $request->validated('observacao'),
            $this->autorizacao->contexto($request),
            $this->autorId($request),
        );

        return new JsonResponse(['data' => $this->consulta->uma($vagaId, $this->projecao($request, $referencia), $this->autorId($request))], 201);
    }

    public function cancelar(Request $request, string $id): JsonResponse
    {
        $referencia = $this->escalas->referenciaDaVaga($id) ?? throw $this->naoEncontrada();
        $this->autorizacao->garantir($request, Operacoes::ESCALA_GERENCIAR, $referencia->ebiId);

        $this->gerir->cancelar($id, $this->autorizacao->contexto($request), $this->autorId($request));

        return new JsonResponse(['data' => $this->consulta->uma($id, $this->projecao($request, $referencia), $this->autorId($request))]);
    }

    /** Oferta da propria pessoa: nao exige `escala.ler`. */
    public function oferecer(Request $request, string $id): JsonResponse
    {
        $this->gerir->oferecer($id, $this->autorId($request));

        // A vaga existe (o dominio ja a encontrou) e o cartao e o proprio.
        $referencia = $this->escalas->referenciaDaVaga($id) ?? throw $this->naoEncontrada();

        return new JsonResponse(['data' => $this->consulta->uma($id, $this->projecao($request, $referencia), $this->autorId($request))], 201);
    }

    public function escolher(Request $request, string $id): JsonResponse
    {
        $referencia = $this->escalas->referenciaDaVaga($id) ?? throw $this->naoEncontrada();
        $this->autorizacao->garantir($request, Operacoes::ESCALA_GERENCIAR, $referencia->ebiId);

        $dados = $request->validate([
            'oferta_id' => ['required', 'string', 'max:26'],
        ]);

        $membroId = $this->gerir->escolher($dados['oferta_id'], $this->autorizacao->contexto($request), $this->autorId($request));

        return new JsonResponse([
            'data' => $this->consulta->uma($id, $this->projecao($request, $referencia), $this->autorId($request)),
            'membro_id' => $membroId,
        ]);
    }

    /**
     * Quem ve os nomes: a dona e a supervisao (`pode_gerir`) veem tudo; quem
     * alcanca o EBI ve a contagem; o resto ve apenas a propria oferta.
     */
    private function projecao(Request $request, EscalaRef $escala): ProjecaoVaga
    {
        $contexto = $this->autorizacao->contexto($request);

        if ($this->dona->podeGerir($escala->id, $contexto)) {
            return ProjecaoVaga::Gestao;
        }

        return $this->autorizacao->alcanca($request, Operacoes::ESCALA_LER, $escala->ebiId)
            ? ProjecaoVaga::Leitor
            : ProjecaoVaga::Publica;
    }

    /**
     * Janela UTC do periodo pedido em dias locais da igreja; `null` sem filtro.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function janela(?string $inicio, ?string $fim): array
    {
        $de = $inicio === null ? null : CarbonImmutable::parse($inicio, 'America/Sao_Paulo')->startOfDay();
        $ate = $fim === null ? null : CarbonImmutable::parse($fim, 'America/Sao_Paulo')->startOfDay();

        if ($de !== null && $ate !== null && $ate->gt($de->addDays(self::DIAS_MAXIMOS))) {
            throw ValidationException::withMessages(['fim' => 'O intervalo não pode exceder '.self::DIAS_MAXIMOS.' dias.']);
        }

        return [$de?->utc()->toDateTimeString(), $ate?->addDay()->utc()->toDateTimeString()];
    }

    private function referencia(string $escalaId): EscalaRef
    {
        return $this->escalas->referencia($escalaId) ?? throw $this->naoEncontrada();
    }

    private function naoEncontrada(): ProblemException
    {
        return new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vaga ou escala não encontrada.');
    }

    private function autorId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
