<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\ConsultarEscala;
use App\Domain\Escalas\Contracts\EscalaRef;
use App\Domain\Escalas\Contracts\EscalaVisao;
use App\Domain\Escalas\Contracts\GerirEscala;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Http\Requests\Escalas\AlterarEscalaRequest;
use App\Http\Requests\Escalas\CriarEscalaRequest;
use App\Http\Support\AutorizacaoEscala;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Escalas de uma sessao (RF-07). A autorizacao passa pelo `AutorizacaoEscala`
 * para separar 403 (sem papel) de 404 (EBI fora do territorio ou inexistente)
 * e acontece antes de qualquer escrita. A identificacao da escala vem do
 * contrato `ConsultarEscala`; a HTTP nunca carrega modelo de modulo.
 */
class EscalaController
{
    public function __construct(
        private readonly AutorizacaoEscala $autorizacao,
        private readonly GerirEscala $gerir,
        private readonly ConsultarEscala $consulta,
        private readonly ConsultarSessao $sessoes,
    ) {}

    /** Sessoes do EBI no periodo, cada uma com a situacao da sua escala. */
    public function index(Request $request, string $id): JsonResponse
    {
        $this->autorizacao->garantir($request, Operacoes::ESCALA_LER, $id);
        $dados = $request->validate([
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
        ]);

        return new JsonResponse(['data' => $this->consulta->listarDoEbi($id, $dados['inicio'], $dados['fim'])]);
    }

    public function store(CriarEscalaRequest $request, string $sessaoId): JsonResponse
    {
        $sessao = $this->sessoes->detalhe($sessaoId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Sessão não encontrada.');
        $this->autorizacao->garantir($request, Operacoes::ESCALA_GERENCIAR, $sessao->ebiId);

        $escala = $this->gerir->criar(
            $sessaoId,
            (int) $request->validated('estimativa_participantes'),
            $request->validated('membros', []),
            $this->autorId($request),
        );

        return new JsonResponse(['escala' => $this->consulta->detalheDaEscala($escala, EscalaVisao::interna($this->autorId($request)))], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $referencia = $this->consulta->referencia($id);
        if ($referencia === null) {
            throw $this->naoEncontrada();
        }

        // Quem esta escalado le a propria escala mesmo sem `escala.ler`.
        if (! $this->consulta->ehMembroVigente($id, $this->autorId($request))) {
            $this->autorizacao->garantir($request, Operacoes::ESCALA_LER, $referencia->ebiId);
        }

        return $this->resposta($request, $referencia);
    }

    public function update(AlterarEscalaRequest $request, string $id): JsonResponse
    {
        $referencia = $this->autorizarGerenciar($request, $id);
        $this->gerir->alterarEstimativa($id, (int) $request->validated('estimativa_participantes'), $this->autorId($request));

        return $this->resposta($request, $referencia);
    }

    /**
     * Aprovacao (RF-07): o territorio exige `escala.aprovar`; quem aprova de
     * fato (chefe vigente, ou `adm_igreja` sem chefe) e decidido no dominio.
     */
    public function aprovar(Request $request, string $id): JsonResponse
    {
        $referencia = $this->autorizarPelaOperacao($request, $id, Operacoes::ESCALA_APROVAR);

        $this->gerir->aprovar($id, $this->autorizacao->contexto($request), $this->autorId($request));

        // Quem aprova decide sobre a escala: ve os conflitos com nomes.
        return $this->resposta($request, $referencia, visaoAmpla: true);
    }

    private function autorizarGerenciar(Request $request, string $escalaId): EscalaRef
    {
        return $this->autorizarPelaOperacao($request, $escalaId, Operacoes::ESCALA_GERENCIAR);
    }

    private function autorizarPelaOperacao(Request $request, string $escalaId, string $operacao): EscalaRef
    {
        $referencia = $this->consulta->referencia($escalaId)
            ?? throw $this->naoEncontrada();
        $this->autorizacao->garantir($request, $operacao, $referencia->ebiId);

        return $referencia;
    }

    private function resposta(Request $request, ?EscalaRef $referencia, int $status = 200, bool $visaoAmpla = false): JsonResponse
    {
        if ($referencia === null) {
            throw $this->naoEncontrada();
        }

        $visao = $visaoAmpla
            ? EscalaVisao::interna($this->autorId($request))
            : $this->autorizacao->visao($request, $this->consulta->ebisEmConflito($referencia->id));
        $detalhe = $this->consulta->detalhe($referencia->id, $visao);
        if ($detalhe === null) {
            throw $this->naoEncontrada();
        }

        return new JsonResponse(['escala' => $detalhe], $status);
    }

    private function naoEncontrada(): ProblemException
    {
        return new ProblemException('not_found', 'Recurso não encontrado', 404, 'Escala não encontrada.');
    }

    private function autorId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
