<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\ConsultarEscala;
use App\Domain\Escalas\Contracts\EscalaRef;
use App\Domain\Escalas\Contracts\GerirEscala;
use App\Domain\Escalas\Contracts\ParticiparEscala;
use App\Domain\Identidade\Support\Operacoes;
use App\Http\Requests\Escalas\IncluirMembroRequest;
use App\Http\Requests\Escalas\MudarFuncaoRequest;
use App\Http\Support\AutorizacaoEscala;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Membros de uma escala (RF-07): incluir, mudar funcao e retirar. Cada
 * operacao devolve a escala inteira, ja no estado pos-alteracao (volta a
 * `prevista` quando estava aprovada).
 */
class EscalaMembroController
{
    public function __construct(
        private readonly AutorizacaoEscala $autorizacao,
        private readonly GerirEscala $gerir,
        private readonly ConsultarEscala $consulta,
        private readonly ParticiparEscala $participar,
    ) {}

    /**
     * Confirmacao do proprio membro (RF-07): sem `escala.gerenciar`; a
     * autorizacao e o dono da vaga, decidido no dominio (vaga de outro e 404).
     */
    public function confirmar(Request $request, string $id): JsonResponse
    {
        $usuarioId = $this->autorId($request);
        $referencia = $this->consulta->referenciaDoMembro($id);
        $membro = $this->participar->confirmar($id, $usuarioId);

        $conflitos = $referencia === null
            ? []
            : $this->conflitoDoMembro($request, $referencia, (string) $membro->id);

        return new JsonResponse([
            'membro' => [
                'id' => (string) $membro->id,
                'escala_id' => (string) $membro->escala_id,
                'usuario_id' => (string) $membro->usuario_id,
                'funcao' => (string) $membro->funcao,
                'status' => (string) $membro->status,
                'confirmado_em' => $membro->confirmado_em?->toIso8601String(),
                'presente' => $membro->presente,
            ],
            'conflitos' => $conflitos,
        ]);
    }

    public function store(IncluirMembroRequest $request, string $escalaId): JsonResponse
    {
        $this->autorizarPelaEscala($request, $escalaId);

        return $this->resposta($request, $this->consulta->referencia((string) $this->gerir->incluirMembro(
            $escalaId,
            (string) $request->validated('usuario_id'),
            (string) $request->validated('funcao'),
            $this->autorizacao->contexto($request),
            $this->autorId($request),
        )->id));
    }

    public function update(MudarFuncaoRequest $request, string $id): JsonResponse
    {
        $this->autorizarPeloMembro($request, $id);

        return $this->resposta($request, $this->consulta->referencia((string) $this->gerir->mudarFuncao($id, (string) $request->validated('funcao'), $this->autorizacao->contexto($request), $this->autorId($request))->id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->autorizarPeloMembro($request, $id);

        return $this->resposta($request, $this->consulta->referencia((string) $this->gerir->retirarMembro($id, $this->autorizacao->contexto($request), $this->autorId($request))->id));
    }

    /**
     * Autoriza pelo EBI dono da escala, antes de qualquer escrita.
     */
    private function autorizarPelaEscala(Request $request, string $escalaId): EscalaRef
    {
        $referencia = $this->consulta->referencia($escalaId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Escala não encontrada.');
        $this->autorizacao->garantir($request, Operacoes::ESCALA_GERENCIAR, $referencia->ebiId);

        return $referencia;
    }

    /**
     * Autoriza pelo EBI dono da escala do membro; membro inexistente e 404.
     */
    private function autorizarPeloMembro(Request $request, string $membroId): EscalaRef
    {
        $referencia = $this->consulta->referenciaDoMembro($membroId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Membro não encontrado.');
        $this->autorizacao->garantir($request, Operacoes::ESCALA_GERENCIAR, $referencia->ebiId);

        return $referencia;
    }

    private function resposta(Request $request, ?EscalaRef $referencia): JsonResponse
    {
        if ($referencia === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Escala não encontrada.');
        }

        $visao = $this->autorizacao->visao($request, $this->consulta->ebisEmConflito($referencia->id), $referencia->ebiId);
        $detalhe = $this->consulta->detalhe($referencia->id, $visao);
        if ($detalhe === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Escala não encontrada.');
        }

        return new JsonResponse(['escala' => $detalhe]);
    }

    private function autorId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }

    /**
     * Conflitos da propria pessoa na escala (CA-12), com os nomes que ela
     * alcanca. A pessoa ve o proprio conflito mesmo sem `escala.ler`.
     *
     * @return array<int, array{sessao_id: ?string, inicio: string, fim: string, igreja_nome: ?string, ebi_nome: ?string}>
     */
    private function conflitoDoMembro(Request $request, EscalaRef $referencia, string $membroId): array
    {
        $visao = $this->autorizacao->visao($request, $this->consulta->ebisEmConflito($referencia->id), $referencia->ebiId);
        $detalhe = $this->consulta->detalhe($referencia->id, $visao);

        foreach ($detalhe['membros'] ?? [] as $membro) {
            if ($membro['id'] === $membroId) {
                return $membro['conflitos'];
            }
        }

        return [];
    }
}
