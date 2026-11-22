<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\ConsultarEscala;
use App\Domain\Escalas\Contracts\EscalaRef;
use App\Domain\Escalas\Contracts\GerirEscala;
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
    ) {}

    public function store(IncluirMembroRequest $request, string $escalaId): JsonResponse
    {
        $this->autorizarPelaEscala($request, $escalaId);

        return $this->resposta($this->consulta->referencia((string) $this->gerir->incluirMembro(
            $escalaId,
            (string) $request->validated('usuario_id'),
            (string) $request->validated('funcao'),
            $this->autorId($request),
        )->id));
    }

    public function update(MudarFuncaoRequest $request, string $id): JsonResponse
    {
        $this->autorizarPeloMembro($request, $id);

        return $this->resposta($this->consulta->referencia((string) $this->gerir->mudarFuncao($id, (string) $request->validated('funcao'), $this->autorId($request))->id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->autorizarPeloMembro($request, $id);

        return $this->resposta($this->consulta->referencia((string) $this->gerir->retirarMembro($id, $this->autorId($request))->id));
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

    private function resposta(?EscalaRef $referencia): JsonResponse
    {
        $detalhe = $referencia === null ? null : $this->consulta->detalhe($referencia->id, null);
        if ($detalhe === null) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Escala não encontrada.');
        }

        return new JsonResponse(['escala' => $detalhe]);
    }

    private function autorId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
