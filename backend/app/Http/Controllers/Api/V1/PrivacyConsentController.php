<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\ConsultarUsuarios;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Privacidade\Contracts\ConsultarConsentimentos;
use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Domain\Privacidade\Contracts\RegistrarConsentimento;
use App\Http\Requests\Privacidade\ConsentimentoRequest;
use App\Http\Resources\ManifestacaoPrivacidadeResource;
use App\Support\EvidenciaAceite;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyConsentController
{
    public function __construct(
        private readonly RegistrarConsentimento $registrar,
        private readonly ConsultarConsentimentos $consulta,
        private readonly ConsultarUsuarios $usuarios,
        private readonly GerirTermos $termos,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $manifestacoes = $this->consulta->atuais($this->usuarioId($request));

        return response()->json([
            'data' => ManifestacaoPrivacidadeResource::collection($manifestacoes)->resolve($request),
        ]);
    }

    public function store(ConsentimentoRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $usuarioId = $this->usuarioId($request);

        $termo = $this->termos->encontrar($dados['termo_versao_id']);
        $hashTermo = $termo === null ? '' : (string) $termo->texto_hash;
        $evidencia = EvidenciaAceite::deRequest($request, $hashTermo);

        $manifestacao = ($dados['retirar'] ?? false)
            ? $this->registrar->retirar($usuarioId, $dados['termo_versao_id'], $evidencia)
            : $this->registrar->registrar(
                $usuarioId,
                $dados['termo_versao_id'],
                $dados['decisao'],
                'proprio',
                $evidencia,
            );

        return response()->json(
            (new ManifestacaoPrivacidadeResource($manifestacao))->resolve($request),
            201,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $manifestacao = $this->consulta->encontrar($id);

        if ($manifestacao === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Manifestação não encontrada.');
        }

        // A policy cobre a operacao; a subarvore do titular e conferida aqui,
        // como em `UserController::show` (o dono precisa estar no escopo).
        if ($this->usuarios->encontrar($this->contexto($request), (string) $manifestacao->usuario_id) === null) {
            throw new ProblemException('consentimento_fora_escopo', 'Acesso negado', 403, 'Consentimento fora da sua autoridade.');
        }

        return (new ManifestacaoPrivacidadeResource($manifestacao))->response();
    }

    private function usuarioId(Request $request): string
    {
        // Rota sob `auth:sanctum`: nao ha nulo em runtime; o `?->` satisfaz o
        // analisador estatico sem importar o modelo do modulo Identidade.
        return (string) ($request->user()?->getAuthIdentifier() ?? '');
    }

    private function contexto(Request $request): ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        if (! $contexto instanceof ContextoAtuacao) {
            throw new ProblemException('sem_contexto', 'Acesso negado', 403, 'Sem contexto de atuação vigente.');
        }

        return $contexto;
    }
}
