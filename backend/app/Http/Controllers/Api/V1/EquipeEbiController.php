<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Http\Support\AutorizacaoEscala;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipeEbiController
{
    public function __construct(
        private readonly AutorizacaoEscala $autorizacao,
        private readonly ConsultarEbi $ebis,
        private readonly ConsultarEquipeEbi $equipe,
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $this->autorizacao->garantir($request, Operacoes::ESCALA_GERENCIAR, $id);

        $ebi = $this->ebis->dados($id) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'EBI não encontrada.');

        return new JsonResponse(['data' => $this->equipe->equipe($id, $ebi->igrejaId)]);
    }
}
