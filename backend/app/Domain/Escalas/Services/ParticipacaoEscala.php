<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Escalas\Contracts\ParticiparEscala;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Support\ProblemException;

/**
 * Confirmacao do membro (RF-07). As guardas rodam na `TravaEscala`, na ordem:
 * dono da vaga, escala aprovada, sessao aceita, vinculo vigente e estado do
 * membro. Confirmar de novo e idempotente e nao gera evento.
 */
class ParticipacaoEscala implements ParticiparEscala
{
    private const STATUS_SESSAO_ACEITOS = ['prevista', 'aberta'];

    /** Estados em que o membro nao mais responde pela vaga. */
    private const STATUS_SEM_CONFIRMACAO = [
        EscalaMembro::STATUS_TROCA_SOLICITADA,
        EscalaMembro::STATUS_SUBSTITUIDO,
    ];

    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly TravaEscala $trava,
        private readonly AvaliadorEquipe $avaliador,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function confirmar(string $membroId, string $usuarioId): EscalaMembro
    {
        $escalaId = (string) EscalaMembro::query()->whereKey($membroId)->value('escala_id');

        if ($escalaId === '') {
            throw $this->naoEncontrado();
        }

        return $this->trava->executar($escalaId, function (Escala $escala) use ($membroId, $usuarioId): EscalaMembro {
            $membro = $escala->membros()->whereKey($membroId)->first();

            // Nao revela que a vaga existe para quem nao e o dono.
            if ($membro === null || (string) $membro->usuario_id !== $usuarioId) {
                throw $this->naoEncontrado();
            }

            if ($escala->status !== Escala::STATUS_APROVADA) {
                throw new ProblemException('escala_em_estado_invalido', 'Escala em estado inválido', 409, 'Só é possível confirmar a vaga de uma escala aprovada.');
            }

            $sessao = $this->sessoes->intervalos([(string) $escala->sessao_id])[(string) $escala->sessao_id]
                ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Sessão não encontrada.');

            if (! in_array($sessao->status, self::STATUS_SESSAO_ACEITOS, true)) {
                throw new ProblemException('sessao_em_estado_invalido', 'Sessão em estado inválido', 409, 'A sessão desta escala não está mais prevista.');
            }

            if (isset($this->avaliador->semVinculo($escala, $sessao)[(string) $membro->id])) {
                throw new ProblemException('membro_sem_vinculo', 'Membro sem vínculo', 422, 'Seu vínculo com este EBI não está vigente na data da sessão.');
            }

            if (in_array($membro->status, self::STATUS_SEM_CONFIRMACAO, true)) {
                throw new ProblemException('membro_em_estado_invalido', 'Membro em estado inválido', 409, 'Esta vaga não aceita mais confirmação.');
            }

            if ($membro->status === EscalaMembro::STATUS_CONFIRMADO) {
                return $membro;
            }

            $membro->forceFill([
                'status' => EscalaMembro::STATUS_CONFIRMADO,
                'confirmado_em' => now(),
            ])->save();

            $this->auditoria->registrar([
                'acao' => 'escala.membro_confirmado',
                'recurso' => 'escala_membro',
                'ator_id' => $usuarioId,
                'origem' => 'api',
                'escopo' => 'ebi:'.$sessao->ebiId,
                'metadados_minimizados' => [
                    'escala_id' => (string) $escala->id,
                    'membro_id' => (string) $membro->id,
                    'sessao_id' => $sessao->id,
                    'status' => EscalaMembro::STATUS_CONFIRMADO,
                ],
            ]);

            return $membro->refresh();
        });
    }

    private function naoEncontrado(): ProblemException
    {
        return new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vaga não encontrada.');
    }
}
