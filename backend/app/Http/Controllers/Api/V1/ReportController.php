<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Relatorios\Contracts\ConsultarRelatorio;
use App\Http\Requests\Relatorios\RelatorioPresencaRequest;
use App\Http\Resources\RelatorioPresencaResource;
use App\Support\ProblemException;
use App\Support\RecursoNaoEncontradoException;
use App\Support\Relatorios\ExportadorCsv;
use App\Support\Relatorios\VisibilidadeIgreja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relatorio de presenca agregado (E06, RF-14). Le __so__ contratos dos modulos
 * de dominio; o calculo e a supressao ficam em `Relatorios`.
 */
class ReportController
{
    public function __construct(
        private readonly ConsultarRelatorio $relatorio,
        private readonly ConsultarHierarquia $hierarquia,
        private readonly ResolverEscopo $escopo,
        private readonly RegistrarEvento $auditoria,
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

        if (($dados['formato'] ?? 'json') === 'csv') {
            return $this->csv($payload, $contexto);
        }

        return (new RelatorioPresencaResource($payload))->response();
    }

    /**
     * Mesma supressao do JSON, auditada com escopo, no e periodo e sem nomes.
     *
     * @param  array<string, mixed>  $payload
     */
    private function csv(array $payload, ContextoAtuacao $contexto): Response
    {
        $exportador = new ExportadorCsv;
        $corpo = $exportador->gerar($payload);

        $this->auditoria->registrar([
            'acao' => 'relatorio.exportado',
            'recurso' => 'relatorio',
            'ator_id' => $contexto->usuarioId,
            'origem' => 'api',
            'escopo' => "{$contexto->tipoEscopo}:{$contexto->escopoId}",
            'motivo' => null,
            'metadados_minimizados' => [
                'nivel' => $payload['no']['nivel'],
                'id' => $payload['no']['id'],
                'inicio' => $payload['periodo']['inicio'],
                'fim' => $payload['periodo']['fim'],
            ],
            'ocorrido_em' => now(),
        ]);

        return response($corpo, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$exportador->nomeDoArquivo($payload).'"',
        ]);
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
        return VisibilidadeIgreja::suprime($contexto);
    }
}
