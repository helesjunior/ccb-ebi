<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Contracts\ConsultarEscala;
use App\Domain\Escalas\Contracts\EscalaRef;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\IntervaloSessao;
use App\Support\ProblemException;

/**
 * Leitura da escala (RF-07): detalhe para a tela e lista das sessoes do EBI
 * com a situacao de cada escala. Tudo o que depende de vigencia vem do
 * `AvaliadorEquipe`, a mesma conta usada pela aprovacao.
 */
class ConsultaEscala implements ConsultarEscala
{
    public const SITUACAO_CANCELADA = 'cancelada';

    public const SITUACAO_ABAIXO_DO_MINIMO = 'abaixo_do_minimo';

    public const SITUACAO_AGUARDANDO_APROVACAO = 'aguardando_aprovacao';

    public const SITUACAO_APROVADA = 'aprovada';

    public const SITUACAO_SEM_ESCALA = 'sem_escala';

    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly ConsultarEquipeEbi $equipe,
        private readonly AvaliadorEquipe $avaliador,
    ) {}

    public function detalhe(string $escalaId, ?string $leitorId): ?array
    {
        $escala = Escala::query()->find($escalaId);

        return $escala === null ? null : $this->detalheDaEscala($escala, $leitorId);
    }

    public function referencia(string $escalaId): ?EscalaRef
    {
        $escala = Escala::query()->find($escalaId);

        return $escala === null ? null : $this->ref($escala);
    }

    public function referenciaDoMembro(string $membroId): ?EscalaRef
    {
        $escala = EscalaMembro::query()->whereKey($membroId)->first()?->escala;

        return $escala === null ? null : $this->ref($escala);
    }

    /** @return array<string, mixed> */
    public function detalheDaEscala(Escala $escala, ?string $leitorId): array
    {
        $sessao = $this->sessao($escala);
        $membros = $escala->membros()->orderBy('created_at')->orderBy('id')->get();
        $semVinculo = $this->avaliador->semVinculo($escala, $sessao);
        $nomes = $this->equipe->nomes($membros->pluck('usuario_id')->map(fn ($id): string => (string) $id)->unique()->values()->all());

        return [
            'id' => (string) $escala->id,
            'sessao_id' => (string) $escala->sessao_id,
            'ebi_id' => (string) $escala->ebi_id,
            'igreja_id' => (string) $escala->igreja_id,
            'status' => $this->statusDaEscala($escala, $sessao),
            'situacao' => $this->situacao($escala, $sessao),
            'estimativa_participantes' => (int) $escala->estimativa_participantes,
            'participantes_por_auxiliar' => (int) $escala->participantes_por_auxiliar,
            'aprovado_por' => $escala->aprovado_por === null ? null : (string) $escala->aprovado_por,
            'aprovado_em' => $escala->aprovado_em?->toIso8601String(),
            'sessao' => [
                'inicio_previsto' => $sessao->inicioPrevisto->toIso8601String(),
                'fim_previsto' => $sessao->fimPrevisto->toIso8601String(),
                'status' => $sessao->status,
            ],
            'equipe_minima' => $this->avaliador->equipeMinima($escala, $sessao),
            'membros' => $membros->map(fn (EscalaMembro $membro): array => [
                'id' => (string) $membro->id,
                'usuario_id' => (string) $membro->usuario_id,
                'nome' => $nomes[(string) $membro->usuario_id] ?? '',
                'funcao' => (string) $membro->funcao,
                'status' => (string) $membro->status,
                'confirmado_em' => $membro->confirmado_em?->toIso8601String(),
                'presente' => $membro->presente,
                'substitui_membro_id' => $membro->substitui_membro_id === null ? null : (string) $membro->substitui_membro_id,
                'sem_vinculo' => isset($semVinculo[(string) $membro->id]),
                'conflitos' => [],
            ])->all(),
            // Preenchido pela Task 12 (presenca da equipe).
            'presenca_equipe_pendente' => false,
        ];
    }

    public function situacao(Escala $escala, IntervaloSessao $sessao): string
    {
        if ($sessao->status === 'cancelada') {
            return self::SITUACAO_CANCELADA;
        }

        if ($escala->status === Escala::STATUS_APROVADA) {
            return self::SITUACAO_APROVADA;
        }

        return $this->avaliador->equipeMinima($escala, $sessao)['atingida']
            ? self::SITUACAO_AGUARDANDO_APROVACAO
            : self::SITUACAO_ABAIXO_DO_MINIMO;
    }

    public function listarDoEbi(string $ebiId, string $inicio, string $fim): array
    {
        $sessoes = $this->sessoes->listar($ebiId, $inicio, $fim);
        $ids = array_map(fn ($sessao): string => $sessao->id, $sessoes);
        $intervalos = $this->sessoes->intervalos($ids);
        $escalas = Escala::query()->whereIn('sessao_id', $ids)->with('membros')->get()->keyBy('sessao_id');

        return array_map(function ($sessao) use ($escalas, $intervalos): array {
            $escala = $escalas->get($sessao->id);
            $intervalo = $intervalos[$sessao->id];

            return [
                'sessao_id' => $sessao->id,
                'inicio_previsto' => $sessao->inicioPrevisto,
                'fim_previsto' => $sessao->fimPrevisto,
                'sessao_status' => $sessao->status,
                'escala_id' => $escala === null ? null : (string) $escala->id,
                'situacao' => $escala === null ? self::SITUACAO_SEM_ESCALA : $this->situacao($escala, $intervalo),
                'confirmados' => $escala === null ? 0 : $this->confirmados($escala, $intervalo),
                'membros' => $escala === null ? 0 : $escala->membros->filter(fn (EscalaMembro $m): bool => $m->status !== EscalaMembro::STATUS_SUBSTITUIDO)->count(),
            ];
        }, $sessoes);
    }

    /**
     * Status gravado da escala, ou `cancelada` quando a sessao foi cancelada
     * depois da aprovacao.
     */
    private function statusDaEscala(Escala $escala, IntervaloSessao $sessao): string
    {
        return $sessao->status === 'cancelada' ? self::SITUACAO_CANCELADA : (string) $escala->status;
    }

    private function confirmados(Escala $escala, IntervaloSessao $sessao): int
    {
        return array_sum($this->avaliador->contagem($escala, $sessao, true));
    }

    private function sessao(Escala $escala): IntervaloSessao
    {
        return $this->sessoes->intervalos([(string) $escala->sessao_id])[(string) $escala->sessao_id]
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Sessão não encontrada.');
    }

    private function ref(Escala $escala): EscalaRef
    {
        return new EscalaRef((string) $escala->id, (string) $escala->ebi_id, (string) $escala->sessao_id);
    }
}
