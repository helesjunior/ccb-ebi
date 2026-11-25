<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\ConsultarEquipe;
use App\Domain\Escalas\Contracts\GerirEquipe;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Organizacao\Contracts\EbiDados;
use App\Http\Requests\Escalas\CriarEquipeRequest;
use App\Http\Requests\Escalas\IncluirMembroEquipeRequest;
use App\Http\Support\AutorizacaoEscala;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Equipes de escala (E07, evolucao). O territorio exige `equipe.gerenciar`
 * na escrita e `escala.ler` na leitura; quem edita cada equipe e decidido no
 * dominio (`PoliticaEquipe`).
 */
class EquipeController
{
    public function __construct(
        private readonly AutorizacaoEscala $autorizacao,
        private readonly ConsultarEbi $ebis,
        private readonly ConsultarEquipe $consulta,
        private readonly GerirEquipe $gerir,
    ) {}

    public function index(Request $request, string $ebiId): JsonResponse
    {
        $this->autorizacao->garantir($request, Operacoes::ESCALA_LER, $ebiId);
        $ebi = $this->ebi($ebiId);

        return new JsonResponse(['data' => $this->consulta->resumosDoEbi($ebi->id, $ebi->igrejaId)]);
    }

    public function store(CriarEquipeRequest $request, string $ebiId): JsonResponse
    {
        $this->autorizacao->garantir($request, Operacoes::EQUIPE_GERENCIAR, $ebiId);

        $equipe = $this->gerir->criar(
            $ebiId,
            (string) $request->validated('coordenadora_id'),
            $this->autorizacao->contexto($request),
            $this->autorId($request),
        );

        return new JsonResponse(['data' => $equipe->getAttribute('detalhe')], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $referencia = $this->consulta->referencia($id) ?? throw $this->naoEncontrada();
        $this->autorizacao->garantir($request, Operacoes::ESCALA_LER, $referencia->ebiId);

        return new JsonResponse(['data' => $this->consulta->detalhe($id) ?? throw $this->naoEncontrada()]);
    }

    public function incluirMembro(IncluirMembroEquipeRequest $request, string $id): JsonResponse
    {
        $this->garantirGerenciar($request, $id);

        $equipe = $this->gerir->incluirMembro(
            $id,
            (string) $request->validated('usuario_id'),
            $this->autorizacao->contexto($request),
            $this->autorId($request),
        );

        return new JsonResponse(['data' => $equipe->getAttribute('detalhe')]);
    }

    public function retirarMembro(Request $request, string $id, string $usuarioId): JsonResponse
    {
        $this->garantirGerenciar($request, $id);

        $equipe = $this->gerir->retirarMembro(
            $id,
            $usuarioId,
            $this->autorizacao->contexto($request),
            $this->autorId($request),
        );

        return new JsonResponse(['data' => $equipe->getAttribute('detalhe')]);
    }

    private function garantirGerenciar(Request $request, string $equipeId): void
    {
        $referencia = $this->consulta->referencia($equipeId) ?? throw $this->naoEncontrada();
        $this->autorizacao->garantir($request, Operacoes::EQUIPE_GERENCIAR, $referencia->ebiId);
    }

    private function ebi(string $ebiId): EbiDados
    {
        return $this->ebis->dados($ebiId) ?? throw $this->naoEncontrada();
    }

    private function naoEncontrada(): ProblemException
    {
        return new ProblemException('not_found', 'Recurso não encontrado', 404, 'Equipe ou EBI não encontrada.');
    }

    private function autorId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
