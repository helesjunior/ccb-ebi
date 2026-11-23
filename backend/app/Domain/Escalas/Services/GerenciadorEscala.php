<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Escalas\Contracts\GerirEscala;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Organizacao\Contracts\EbiDados;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\IntervaloSessao;
use App\Support\ProblemException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Escrita da escala (RF-07): criar, mudar a estimativa, incluir, retirar e
 * mudar funcao. Toda regra que depende do estado da escala roda dentro da
 * `TravaEscala`; a criacao e uma transacao propria porque a escala ainda nao
 * existe para ser travada.
 *
 * Qualquer alteracao desfaz a aprovacao: a escala volta a `prevista`, o aviso
 * de equipe minima deixa de valer e as trocas pendentes sao canceladas (o
 * membro volta ao status anterior).
 */
class GerenciadorEscala implements GerirEscala
{
    /** Funcoes aceitas na escala e os papeis que cada uma exige. */
    private const PAPEIS_POR_FUNCAO = [
        EscalaMembro::FUNCAO_COORDENADORA => ['coordenadora', 'coordenadora_chefe'],
        EscalaMembro::FUNCAO_AUXILIAR => ['auxiliar', 'coordenadora', 'coordenadora_chefe'],
    ];

    private const STATUS_SESSAO_ACEITOS = ['prevista', 'aberta'];

    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly ConsultarEbi $ebis,
        private readonly ConsultarEquipeEbi $equipe,
        private readonly TravaEscala $trava,
        private readonly RegistrarEvento $auditoria,
        private readonly QuemAprova $quemAprova,
        private readonly AvaliadorEquipe $avaliador,
    ) {}

    public function criar(string $sessaoId, int $estimativa, array $membros, ?string $autorId): Escala
    {
        $sessao = $this->sessaoEditavel($sessaoId);
        $ebi = $this->ebi($sessao->ebiId);
        $funcoes = $this->validarMembros($sessao, $membros, []);

        try {
            return DB::transaction(function () use ($sessao, $ebi, $estimativa, $membros, $funcoes, $autorId): Escala {
                $escala = Escala::query()->create([
                    'sessao_id' => $sessao->id,
                    'ebi_id' => $sessao->ebiId,
                    'igreja_id' => $sessao->igrejaId,
                    'estimativa_participantes' => $estimativa,
                    'participantes_por_auxiliar' => $ebi->participantesPorAuxiliar,
                    'status' => Escala::STATUS_PREVISTA,
                    'criado_por' => $autorId,
                ]);

                foreach ($membros as $indice => $membro) {
                    EscalaMembro::query()->create([
                        'escala_id' => $escala->id,
                        'usuario_id' => $membro['usuario_id'],
                        'funcao' => $funcoes[$indice],
                        'status' => EscalaMembro::STATUS_ESCALADO,
                    ]);
                }

                $this->auditoria->registrar([
                    'acao' => 'escala.criada',
                    'recurso' => 'escala',
                    'ator_id' => $autorId,
                    'origem' => 'api',
                    'escopo' => 'ebi:'.$sessao->ebiId,
                    'metadados_minimizados' => [
                        'escala_id' => (string) $escala->id,
                        'sessao_id' => $sessao->id,
                        'estimativa_participantes' => $estimativa,
                        'membros' => count($membros),
                        'status' => Escala::STATUS_PREVISTA,
                    ],
                ]);

                return $escala;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), 'sessao_id')) {
                throw $e;
            }

            throw new ProblemException('escala_existente', 'Escala já existe', 409, 'Esta sessão já possui uma escala.');
        }
    }

    public function alterarEstimativa(string $escalaId, int $estimativa, ?string $autorId): Escala
    {
        return $this->trava->executar($escalaId, function (Escala $escala) use ($estimativa, $autorId): Escala {
            $sessao = $this->sessaoEditavel((string) $escala->sessao_id);
            $escala->forceFill(['estimativa_participantes' => $estimativa]);

            return $this->salvarAlteracao($escala, $sessao, $autorId);
        });
    }

    public function incluirMembro(string $escalaId, string $usuarioId, string $funcao, ?string $autorId): Escala
    {
        return $this->trava->executar($escalaId, function (Escala $escala) use ($usuarioId, $funcao, $autorId): Escala {
            $sessao = $this->sessaoEditavel((string) $escala->sessao_id);
            $this->validarMembros($sessao, [['usuario_id' => $usuarioId, 'funcao' => $funcao]], $this->usuariosAtivos($escala));

            EscalaMembro::query()->create([
                'escala_id' => $escala->id,
                'usuario_id' => $usuarioId,
                'funcao' => $funcao,
                'status' => EscalaMembro::STATUS_ESCALADO,
            ]);

            return $this->salvarAlteracao($escala, $sessao, $autorId);
        });
    }

    public function retirarMembro(string $membroId, ?string $autorId): Escala
    {
        $escalaId = (string) EscalaMembro::query()->whereKey($membroId)->value('escala_id');

        return $this->trava->executar($escalaId, function (Escala $escala) use ($membroId, $autorId): Escala {
            $sessao = $this->sessaoEditavel((string) $escala->sessao_id);
            $membro = $escala->membros()->whereKey($membroId)->first()
                ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Membro não encontrado.');

            if ($membro->status === EscalaMembro::STATUS_SUBSTITUIDO) {
                throw new ProblemException('membro_em_estado_invalido', 'Membro em estado inválido', 409, 'Este membro já foi substituído.');
            }

            $membro->delete();

            return $this->salvarAlteracao($escala, $sessao, $autorId);
        });
    }

    public function mudarFuncao(string $membroId, string $funcao, ?string $autorId): Escala
    {
        $escalaId = (string) EscalaMembro::query()->whereKey($membroId)->value('escala_id');

        return $this->trava->executar($escalaId, function (Escala $escala) use ($membroId, $funcao, $autorId): Escala {
            $sessao = $this->sessaoEditavel((string) $escala->sessao_id);
            $membro = $escala->membros()->whereKey($membroId)->first()
                ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Membro não encontrado.');

            if ($membro->status === EscalaMembro::STATUS_SUBSTITUIDO) {
                throw new ProblemException('membro_em_estado_invalido', 'Membro em estado inválido', 409, 'Este membro já foi substituído.');
            }

            $this->validarMembros(
                $sessao,
                [['usuario_id' => (string) $membro->usuario_id, 'funcao' => $funcao]],
                array_diff($this->usuariosAtivos($escala), [(string) $membro->usuario_id]),
            );

            if ($membro->funcao !== $funcao) {
                $membro->forceFill(['funcao' => $funcao])->save();
            }

            return $this->salvarAlteracao($escala, $sessao, $autorId);
        });
    }

    /**
     * Aprova a escala, na ordem: quem aprova, estado da escala, estado da
     * sessao, vinculos dos membros e equipe minima. A primeira guarda que
     * recusa deixa a escala como estava.
     */
    public function aprovar(string $escalaId, ContextoDecisao $contexto, ?string $autorId): Escala
    {
        return $this->trava->executar($escalaId, function (Escala $escala) use ($contexto, $autorId): Escala {
            $sessao = $this->sessao((string) $escala->sessao_id);

            if (! $this->quemAprova->podeAprovarEscala($contexto, $sessao->ebiId, $sessao->igrejaId)) {
                throw new ProblemException('aprovador_invalido', 'Aprovador inválido', 403, 'Você não pode aprovar a escala deste EBI.');
            }

            if ($escala->status === Escala::STATUS_APROVADA) {
                throw new ProblemException('escala_em_estado_invalido', 'Escala em estado inválido', 409, 'Esta escala já está aprovada.');
            }

            if (! in_array($sessao->status, self::STATUS_SESSAO_ACEITOS, true)) {
                throw new ProblemException('sessao_em_estado_invalido', 'Sessão em estado inválido', 409, 'Só é possível aprovar a escala de uma sessão prevista ou aberta.');
            }

            if ($this->avaliador->semVinculo($escala, $sessao) !== []) {
                throw new ProblemException('membro_sem_vinculo', 'Membro sem vínculo', 422, 'Há membro escalado sem vínculo vigente na data da sessão.');
            }

            if (! $this->avaliador->equipeMinima($escala, $sessao)['atingida']) {
                throw new ProblemException('equipe_minima_nao_atingida', 'Equipe mínima não atingida', 422, 'A escala ainda não atinge a equipe mínima desta sessão.');
            }

            $escala->forceFill([
                'status' => Escala::STATUS_APROVADA,
                'aprovado_por' => $autorId,
                'aprovado_em' => now(),
            ])->save();

            $this->auditoria->registrar([
                'acao' => 'escala.aprovada',
                'recurso' => 'escala',
                'ator_id' => $autorId,
                'origem' => 'api',
                'escopo' => 'ebi:'.$sessao->ebiId,
                'metadados_minimizados' => [
                    'escala_id' => (string) $escala->id,
                    'sessao_id' => $sessao->id,
                    'status' => Escala::STATUS_APROVADA,
                ],
            ]);

            return $escala->refresh();
        });
    }

    /**
     * Persiste a alteracao e desfaz a aprovacao: volta a prevista, copia o
     * divisor atual do EBI, mantem a confirmacao e cancela as trocas pendentes.
     */
    private function salvarAlteracao(Escala $escala, IntervaloSessao $sessao, ?string $autorId): Escala
    {
        $voltouPrevista = $escala->status !== Escala::STATUS_PREVISTA;
        $ebi = $this->ebi($sessao->ebiId);

        $escala->forceFill([
            'status' => Escala::STATUS_PREVISTA,
            'aprovado_por' => null,
            'aprovado_em' => null,
            'participantes_por_auxiliar' => $ebi->participantesPorAuxiliar,
        ])->save();

        $canceladas = 0;

        foreach ($this->trocasPendentes($escala) as $troca) {
            $troca->forceFill([
                'status' => SolicitacaoTrocaEscala::STATUS_CANCELADA,
                'motivo_decisao' => 'escala alterada',
                'decidido_em' => now(),
            ])->save();

            $membro = $troca->membro;
            if ($membro !== null && $membro->status === EscalaMembro::STATUS_TROCA_SOLICITADA) {
                $membro->forceFill(['status' => $membro->statusAnterior()])->save();
            }

            $this->auditoria->registrar([
                'acao' => 'escala.troca_cancelada',
                'recurso' => 'solicitacao_troca_escala',
                'ator_id' => $autorId,
                'origem' => 'api',
                'escopo' => 'ebi:'.$sessao->ebiId,
                'motivo' => 'escala alterada',
                'metadados_minimizados' => [
                    'troca_id' => (string) $troca->id,
                    'escala_id' => (string) $escala->id,
                    'membro_id' => (string) $troca->escala_membro_id,
                    'status' => SolicitacaoTrocaEscala::STATUS_CANCELADA,
                ],
            ]);

            $canceladas += 1;
        }

        $this->auditoria->registrar([
            'acao' => 'escala.alterada',
            'recurso' => 'escala',
            'ator_id' => $autorId,
            'origem' => 'api',
            'escopo' => 'ebi:'.$sessao->ebiId,
            'metadados_minimizados' => [
                'escala_id' => (string) $escala->id,
                'sessao_id' => $sessao->id,
                'voltou_prevista' => $voltouPrevista,
                'estimativa_participantes' => (int) $escala->estimativa_participantes,
                'trocas_canceladas' => $canceladas,
            ],
        ]);

        return $escala->refresh();
    }

    /**
     * Intervalo da sessao pelo id; inexistente e 404 (sem olhar o status).
     */
    private function sessao(string $sessaoId): IntervaloSessao
    {
        return $this->sessoes->intervalos([$sessaoId])[$sessaoId]
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Sessão não encontrada.');
    }

    /**
     * Sessao que aceita escala: `prevista` ou `aberta`. Inexistente e 404;
     * cancelada/encerrada e 409.
     */
    private function sessaoEditavel(string $sessaoId): IntervaloSessao
    {
        $sessao = $this->sessao($sessaoId);

        if (! in_array($sessao->status, self::STATUS_SESSAO_ACEITOS, true)) {
            throw new ProblemException(
                'sessao_em_estado_invalido',
                'Sessão em estado inválido',
                409,
                'SÃ³ Ã© possÃ­vel montar a escala de uma sessão prevista ou aberta.',
            );
        }

        return $sessao;
    }

    private function ebi(string $ebiId): EbiDados
    {
        return $this->ebis->dados($ebiId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'EBI não encontrada.');
    }

    /**
     * Valida funcao, elegibilidade, vigencia e duplicidade de cada membro.
     * `$jaAtivos` sao os usuarios que ja ocupam vaga (para a duplicidade).
     *
     * @param  array<int, array{usuario_id: string, funcao: string}>  $membros
     * @param  array<int, string>  $jaAtivos
     * @return array<int, string> funcao normalizada por indice
     */
    private function validarMembros(IntervaloSessao $sessao, array $membros, array $jaAtivos): array
    {
        $funcoes = [];
        $vistos = array_fill_keys($jaAtivos, true);

        foreach ($membros as $indice => $membro) {
            $funcao = $this->funcaoValida($membro['funcao']);

            if (isset($vistos[$membro['usuario_id']])) {
                throw new ProblemException('membro_ja_escalado', 'Membro já escalado', 422, 'Esta pessoa já está na escala desta sessão.');
            }
            $vistos[$membro['usuario_id']] = true;

            $this->garantirElegivel($sessao, $membro['usuario_id'], $funcao);
            $funcoes[$indice] = $funcao;
        }

        return $funcoes;
    }

    private function garantirElegivel(IntervaloSessao $sessao, string $usuarioId, string $funcao): void
    {
        $papeis = null;

        foreach ($this->equipe->equipe($sessao->ebiId, $sessao->igrejaId) as $membro) {
            if ($membro['usuario_id'] === $usuarioId) {
                $papeis = $membro['papeis'];
                break;
            }
        }

        if ($papeis === null || array_intersect($papeis, self::PAPEIS_POR_FUNCAO[$funcao]) === []) {
            throw new ProblemException(
                'membro_inelegivel',
                'Membro inelegível',
                422,
                $papeis === null
                    ? 'Esta pessoa não pertence Ã  equipe deste EBI.'
                    : 'O papel desta pessoa não permite esta funÃ§Ã£o na escala.',
            );
        }

        $vigencias = $this->equipe->vigencias([$usuarioId], $sessao->ebiId, $sessao->igrejaId)[$usuarioId] ?? [];

        if (! $this->vigenteNaData($vigencias, $sessao->dataLocal, $funcao)) {
            throw new ProblemException('membro_inelegivel', 'Membro inelegível', 422, 'O vínculo desta pessoa não está vigente na data da sessão.');
        }
    }

    /**
     * @param  array<int, array{papel: string, inicio: string, fim: ?string}>  $vigencias
     */
    private function vigenteNaData(array $vigencias, string $dataLocal, string $funcao): bool
    {
        $aceitos = self::PAPEIS_POR_FUNCAO[$funcao];

        foreach ($vigencias as $vigencia) {
            if (! in_array($vigencia['papel'], $aceitos, true)) {
                continue;
            }

            $inicio = substr($vigencia['inicio'], 0, 10);
            $fim = $vigencia['fim'] === null ? null : substr($vigencia['fim'], 0, 10);

            if ($inicio <= $dataLocal && ($fim === null || $fim >= $dataLocal)) {
                return true;
            }
        }

        return false;
    }

    private function funcaoValida(string $funcao): string
    {
        if (! array_key_exists($funcao, self::PAPEIS_POR_FUNCAO)) {
            throw new ProblemException('membro_invalido', 'Membro inválido', 422, 'Função inválida na escala.');
        }

        return $funcao;
    }

    /** @return array<int, string> */
    private function usuariosAtivos(Escala $escala): array
    {
        return $escala->membros()->ativos()->pluck('usuario_id')->map(fn ($id): string => (string) $id)->all();
    }

    /** @return Collection<int, SolicitacaoTrocaEscala> */
    private function trocasPendentes(Escala $escala): Collection
    {
        return SolicitacaoTrocaEscala::query()
            ->whereIn('escala_membro_id', $escala->membros()->select('id'))
            ->where('status', SolicitacaoTrocaEscala::STATUS_PENDENTE)
            ->with('membro')
            ->get();
    }
}
