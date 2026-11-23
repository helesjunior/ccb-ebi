<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Escalas\Contracts\GerirTrocaEscala;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\IntervaloSessao;
use App\Support\ProblemException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Troca de vaga (RF-07). O pedido, o cancelamento e a decisao rodam na
 * `TravaEscala`; a decisao checa quem decide (a mesma regra da aprovacao, com
 * o solicitante fora) antes de olhar o estado. Aprovar nunca derruba a
 * aprovacao da escala: o original fica `substituido` e nasce um membro novo.
 */
class GerenciadorTrocaEscala implements GerirTrocaEscala
{
    private const DECISOES = ['aprovar', 'recusar'];

    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly ConsultarEquipeEbi $equipe,
        private readonly TravaEscala $trava,
        private readonly RegistrarEvento $auditoria,
        private readonly QuemAprova $quemAprova,
        private readonly AvaliadorEquipe $avaliador,
    ) {}

    public function solicitar(string $membroId, ?string $motivo, string $usuarioId): SolicitacaoTrocaEscala
    {
        $escalaId = $this->escalaDoMembro($membroId);

        return $this->trava->executar($escalaId, function (Escala $escala) use ($membroId, $motivo, $usuarioId): SolicitacaoTrocaEscala {
            $membro = $this->membroDaPessoa($escala, $membroId, $usuarioId);

            if ($escala->status !== Escala::STATUS_APROVADA) {
                throw new ProblemException('escala_em_estado_invalido', 'Escala em estado inválido', 409, 'Só é possível pedir troca numa escala aprovada.');
            }

            $sessao = $this->sessao((string) $escala->sessao_id);

            if ($sessao->status !== 'prevista') {
                throw new ProblemException('sessao_em_estado_invalido', 'Sessão em estado inválido', 409, 'Só é possível pedir troca antes de a sessão começar.');
            }

            if (! in_array($membro->status, [EscalaMembro::STATUS_ESCALADO, EscalaMembro::STATUS_CONFIRMADO], true)
                || $this->temTrocaPendente((string) $membro->id)) {
                throw new ProblemException('membro_em_estado_invalido', 'Membro em estado inválido', 409, 'Esta vaga não aceita pedido de troca agora.');
            }

            $troca = SolicitacaoTrocaEscala::query()->create([
                'escala_membro_id' => $membro->id,
                'solicitante_id' => $usuarioId,
                'motivo' => $motivo,
                'status' => SolicitacaoTrocaEscala::STATUS_PENDENTE,
            ]);

            $membro->forceFill(['status' => EscalaMembro::STATUS_TROCA_SOLICITADA])->save();

            $this->auditoria->registrar([
                'acao' => 'escala.troca_solicitada',
                'recurso' => 'solicitacao_troca_escala',
                'ator_id' => $usuarioId,
                'origem' => 'api',
                'escopo' => 'ebi:'.$sessao->ebiId,
                'metadados_minimizados' => [
                    'troca_id' => (string) $troca->id,
                    'escala_id' => (string) $escala->id,
                    'membro_id' => (string) $membro->id,
                    'status' => SolicitacaoTrocaEscala::STATUS_PENDENTE,
                ],
            ]);

            return $troca->refresh();
        });
    }

    public function cancelar(string $trocaId, string $usuarioId): SolicitacaoTrocaEscala
    {
        $escalaId = $this->escalaDaTroca($trocaId);

        return $this->trava->executar($escalaId, function (Escala $escala) use ($trocaId, $usuarioId): SolicitacaoTrocaEscala {
            $troca = $this->troca($escala, $trocaId);

            if ((string) $troca->solicitante_id !== $usuarioId) {
                throw $this->naoEncontrada();
            }

            if ($troca->status !== SolicitacaoTrocaEscala::STATUS_PENDENTE) {
                throw new ProblemException('troca_ja_decidida', 'Troca já decidida', 409, 'Esta troca já foi decidida.');
            }

            $troca->forceFill([
                'status' => SolicitacaoTrocaEscala::STATUS_CANCELADA,
                'decidido_em' => now(),
            ])->save();

            $this->devolverMembro($troca);
            $this->auditarTroca('escala.troca_cancelada', $escala, $troca, $usuarioId, SolicitacaoTrocaEscala::STATUS_CANCELADA);

            return $troca->refresh();
        });
    }

    public function decidir(string $trocaId, string $decisao, ?string $substitutoUsuarioId, ?string $motivo, ContextoDecisao $contexto, ?string $autorId): SolicitacaoTrocaEscala
    {
        if (! in_array($decisao, self::DECISOES, true)) {
            throw new ProblemException('decisao_invalida', 'Decisão inválida', 422, 'A decisão precisa ser aprovar ou recusar.');
        }

        $escalaId = $this->escalaDaTroca($trocaId);

        return $this->trava->executar($escalaId, function (Escala $escala) use ($trocaId, $decisao, $substitutoUsuarioId, $motivo, $contexto, $autorId): SolicitacaoTrocaEscala {
            $troca = $this->troca($escala, $trocaId);
            $sessao = $this->sessao((string) $escala->sessao_id);

            if ((string) $troca->solicitante_id === $contexto->usuarioId) {
                throw new ProblemException('sem_permissao', 'Acesso negado', 403, 'Você não pode decidir o próprio pedido de troca.');
            }

            if (! $this->quemAprova->podeDecidirTroca($contexto, $sessao->ebiId, $sessao->igrejaId, (string) $troca->solicitante_id)) {
                throw new ProblemException('aprovador_invalido', 'Aprovador inválido', 403, 'Você não pode decidir esta troca.');
            }

            if ($troca->status !== SolicitacaoTrocaEscala::STATUS_PENDENTE) {
                throw new ProblemException('troca_ja_decidida', 'Troca já decidida', 409, 'Esta troca já foi decidida.');
            }

            if ($escala->status !== Escala::STATUS_APROVADA) {
                throw new ProblemException('escala_em_estado_invalido', 'Escala em estado inválido', 409, 'Só é possível decidir troca numa escala aprovada.');
            }

            if ($sessao->status !== 'prevista') {
                throw new ProblemException('sessao_em_estado_invalido', 'Sessão em estado inválido', 409, 'A sessão desta troca não está mais prevista.');
            }

            if ($decisao === 'aprovar') {
                $this->aprovar($escala, $troca, $sessao, $substitutoUsuarioId);
            } else {
                $this->devolverMembro($troca);
            }

            $troca->forceFill([
                'status' => $decisao === 'aprovar' ? SolicitacaoTrocaEscala::STATUS_APROVADA : SolicitacaoTrocaEscala::STATUS_RECUSADA,
                'decidido_por' => $autorId,
                'decidido_em' => now(),
                'motivo_decisao' => $motivo,
            ])->save();

            $this->auditarTroca('escala.troca_decidida', $escala, $troca, $autorId, (string) $troca->status, $decisao);

            return $troca->refresh();
        });
    }

    public function listar(string $ebiId, ?string $status): array
    {
        /** @var array<int, SolicitacaoTrocaEscala> $trocas */
        $trocas = SolicitacaoTrocaEscala::query()
            ->whereIn('escala_membro_id', EscalaMembro::query()->whereIn(
                'escala_id',
                Escala::query()->where('ebi_id', $ebiId)->select('id'),
            )->select('id'))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();

        if ($trocas === []) {
            return [];
        }

        $membros = EscalaMembro::query()
            ->whereIn('id', array_map(fn (SolicitacaoTrocaEscala $t): string => (string) $t->escala_membro_id, $trocas))
            ->with('escala')
            ->get()
            ->keyBy(fn (EscalaMembro $m): string => (string) $m->id);

        $intervalos = $this->sessoes->intervalos(
            $membros->map(fn (EscalaMembro $m): string => $m->sessaoId())
                ->filter()->unique()->values()->all(),
        );
        $nomes = $this->equipe->nomes(array_map(fn (SolicitacaoTrocaEscala $t): string => (string) $t->solicitante_id, $trocas));

        return array_map(function (SolicitacaoTrocaEscala $troca) use ($membros, $intervalos, $nomes): array {
            $membro = $membros[(string) $troca->escala_membro_id] ?? null;
            $sessaoId = $membro === null ? '' : $membro->sessaoId();
            $sessao = $intervalos[$sessaoId] ?? null;

            $situacao = $troca->status === SolicitacaoTrocaEscala::STATUS_PENDENTE && $sessao?->status !== 'prevista'
                ? 'expirada'
                : (string) $troca->status;

            return [
                'id' => (string) $troca->id,
                'escala_id' => (string) ($membro === null ? '' : $membro->escala_id),
                'sessao_id' => $sessaoId,
                'membro_id' => (string) $troca->escala_membro_id,
                'solicitante_id' => (string) $troca->solicitante_id,
                'solicitante_nome' => $nomes[(string) $troca->solicitante_id] ?? '',
                'motivo' => $troca->motivo,
                'status' => (string) $troca->status,
                'situacao' => $situacao,
                'criado_em' => $troca->created_at?->toIso8601String() ?? '',
            ];
        }, $trocas);
    }

    public function representar(SolicitacaoTrocaEscala $troca): array
    {
        return [
            'id' => (string) $troca->id,
            'escala_membro_id' => (string) $troca->escala_membro_id,
            'solicitante_id' => (string) $troca->solicitante_id,
            'motivo' => $troca->motivo,
            'status' => (string) $troca->status,
            // A troca decidida nao expira mesmo com a sessao encerrada.
            'situacao' => (string) $troca->status,
            'substituto_membro_id' => $troca->substituto_membro_id === null ? null : (string) $troca->substituto_membro_id,
            'decidido_por' => $troca->decidido_por === null ? null : (string) $troca->decidido_por,
            'decidido_em' => $troca->decidido_em?->toIso8601String(),
            'motivo_decisao' => $troca->motivo_decisao,
            'criado_em' => $troca->created_at?->toIso8601String(),
        ];
    }

    /**
     * Aprovacao: o substituto precisa ser da equipe, vigente na data e estar
     * fora da escala. O original sai (`substituido`) e o novo entra na mesma
     * funcao, apontando para quem substituiu.
     */
    private function aprovar(Escala $escala, SolicitacaoTrocaEscala $troca, IntervaloSessao $sessao, ?string $substitutoUsuarioId): void
    {
        $original = $this->membro($troca);
        $usuarioId = trim((string) $substitutoUsuarioId);

        if ($usuarioId === '' || ! $this->elegivel($escala, $sessao, $usuarioId, (string) $original->funcao)) {
            throw new ProblemException('membro_inelegivel', 'Membro inelegível', 422, 'Escolha um substituto com vínculo vigente neste EBI.');
        }

        if ($escala->membros()->ativos()->where('usuario_id', $usuarioId)->exists()) {
            throw new ProblemException('membro_ja_escalado', 'Membro já escalado', 422, 'Esta pessoa já está na escala desta sessão.');
        }

        $original->forceFill(['status' => EscalaMembro::STATUS_SUBSTITUIDO])->save();

        $novo = EscalaMembro::query()->create([
            'escala_id' => $escala->id,
            'usuario_id' => $usuarioId,
            'funcao' => (string) $original->funcao,
            'status' => EscalaMembro::STATUS_ESCALADO,
            'substitui_membro_id' => (string) $original->id,
        ]);

        $troca->forceFill(['substituto_membro_id' => (string) $novo->id])->save();
    }

    /**
     * O substituto precisa pertencer a equipe do EBI, com o papel da funcao
     * vigente na data local da sessao (mesma conta do `AvaliadorEquipe`).
     */
    private function elegivel(Escala $escala, IntervaloSessao $sessao, string $usuarioId, string $funcao): bool
    {
        $papeis = null;

        foreach ($this->equipe->equipe($sessao->ebiId, $sessao->igrejaId) as $membro) {
            if ($membro['usuario_id'] === $usuarioId) {
                $papeis = $membro['papeis'];
                break;
            }
        }

        if ($papeis === null) {
            return false;
        }

        $vigencias = $this->equipe->vigencias([$usuarioId], $sessao->ebiId, $sessao->igrejaId)[$usuarioId] ?? [];

        return $this->avaliador->vigenteEm($vigencias, $sessao->dataLocal, $funcao);
    }

    /** O membro da troca volta ao estado de antes do pedido. */
    private function devolverMembro(SolicitacaoTrocaEscala $troca): void
    {
        $membro = $this->membro($troca);

        if ($membro->status === EscalaMembro::STATUS_TROCA_SOLICITADA) {
            $membro->forceFill(['status' => $membro->statusAnterior()])->save();
        }
    }

    private function auditarTroca(string $acao, Escala $escala, SolicitacaoTrocaEscala $troca, ?string $autorId, string $status, string $ordemExtra = ''): void
    {
        $metadados = [
            'troca_id' => (string) $troca->id,
            'escala_id' => (string) $escala->id,
            'membro_id' => (string) $troca->escala_membro_id,
            'status' => $status,
            'substituto_membro_id' => $troca->substituto_membro_id === null ? null : (string) $troca->substituto_membro_id,
        ];

        if ($ordemExtra !== '') {
            $metadados['decisao'] = $ordemExtra;
        }

        $this->auditoria->registrar([
            'acao' => $acao,
            'recurso' => 'solicitacao_troca_escala',
            'ator_id' => $autorId,
            'origem' => 'api',
            'escopo' => 'ebi:'.$escala->ebi_id,
            'metadados_minimizados' => $metadados,
        ]);
    }

    /** Membro da vaga: vaga de outra pessoa ou inexistente e 404. */
    private function membroDaPessoa(Escala $escala, string $membroId, string $usuarioId): EscalaMembro
    {
        $membro = $escala->membros()->whereKey($membroId)->first();

        if ($membro === null || (string) $membro->usuario_id !== $usuarioId) {
            throw $this->naoEncontrada();
        }

        return $membro;
    }

    /** Troca pertencente a escala da trava. */
    private function troca(Escala $escala, string $trocaId): SolicitacaoTrocaEscala
    {
        $troca = SolicitacaoTrocaEscala::query()
            ->whereKey($trocaId)
            ->whereIn('escala_membro_id', $escala->membros()->select('id'))
            ->first();

        return $troca ?? throw $this->naoEncontrada();
    }

    private function membro(SolicitacaoTrocaEscala $troca): EscalaMembro
    {
        return EscalaMembro::query()->whereKey($troca->escala_membro_id)->first()
            ?? throw $this->naoEncontrada();
    }

    private function temTrocaPendente(string $membroId): bool
    {
        return SolicitacaoTrocaEscala::query()
            ->where('escala_membro_id', $membroId)
            ->where('status', SolicitacaoTrocaEscala::STATUS_PENDENTE)
            ->exists();
    }

    private function sessao(string $sessaoId): IntervaloSessao
    {
        return $this->sessoes->intervalos([$sessaoId])[$sessaoId]
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Sessão não encontrada.');
    }

    /** Escala dona da vaga; vaga inexistente e 404. */
    private function escalaDoMembro(string $membroId): string
    {
        $escalaId = (string) EscalaMembro::query()->whereKey($membroId)->value('escala_id');

        return $escalaId !== '' ? $escalaId : throw $this->naoEncontrada();
    }

    /** Escala dona da troca; troca inexistente e 404. */
    private function escalaDaTroca(string $trocaId): string
    {
        $membroId = (string) SolicitacaoTrocaEscala::query()->whereKey($trocaId)->value('escala_membro_id');

        if ($membroId === '') {
            throw $this->naoEncontrada();
        }

        return $this->escalaDoMembro($membroId);
    }

    private function naoEncontrada(): ProblemException
    {
        return new ProblemException('not_found', 'Recurso não encontrado', 404, 'Troca não encontrada.');
    }
}
