<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Relatorios\Contracts\ConsultarRelatorio;
use App\Http\Requests\Relatorios\RelatorioPresencaRequest;
use App\Http\Resources\RelatorioPresencaResource;
use App\Support\ProblemException;
use App\Support\RecursoNaoEncontradoException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relatorio de presenca agregado (E06, RF-14). Le __so__ contratos dos modulos
 * de dominio; o calculo e a supressao ficam em `Relatorios`.
 */
class ReportController
{
    /**
     * Papeis que veem os numeros reais da igreja (ADR-0020). Os demais recebem
     * supressao. A isencao exige tambem contexto de igreja ou EBI (decisao 6).
     */
    private const VE_A_IGREJA = ['coordenadora', 'adm_igreja', 'ministerio_local'];

    public function __construct(
        private readonly ConsultarRelatorio $relatorio,
        private readonly ConsultarHierarquia $hierarquia,
        private readonly ResolverEscopo $escopo,
    ) {}

    public function attendance(RelatorioPresencaRequest $request): Response
    {
        // Decisao 3, nesta ordem: papel (403), no inexistente (404), no fora do
        // escopo (404).
        Gate::authorize(Operacoes::RELATORIO_LER);

        $contexto = $this->contexto($request);
        [$nivel, $id] = $this->alvo($request, $contexto);

        try {
            $ancestrais = $this->hierarquia->ancestrais($nivel, $id);
        } catch (RecursoNaoEncontradoException) {
            $ancestrais = [];
        }

        if ($ancestrais === []
            || ! $this->escopo->cobre($contexto->tipoEscopo, $contexto->escopoId, $nivel, $id)) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404);
        }

        $dados = $request->validated();
        $payload = $this->relatorio->presenca(
            $nivel,
            $id,
            $dados['inicio'],
            $dados['fim'],
            $this->suprimir($contexto),
        );

        return (new RelatorioPresencaResource($payload))->response();
    }

    private function contexto(Request $request): ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        return $contexto instanceof ContextoAtuacao
            ? $contexto
            : throw new ProblemException('sem_vinculo', 'Acesso negado', 403);
    }

    /** @return array{0: string, 1: string} */
    private function alvo(RelatorioPresencaRequest $request, ContextoAtuacao $contexto): array
    {
        $nivel = $request->validated('nivel');
        $id = $request->validated('id');

        if ($nivel === null || $id === null) {
            return [$contexto->tipoEscopo, $contexto->escopoId];
        }

        return [$nivel, $id];
    }

    private function suprimir(ContextoAtuacao $contexto): bool
    {
        $veAIgreja = in_array($contexto->papelCodigo, self::VE_A_IGREJA, true)
            && in_array($contexto->tipoEscopo, ['igreja', 'ebi'], true);

        return ! $veAIgreja;
    }
}
