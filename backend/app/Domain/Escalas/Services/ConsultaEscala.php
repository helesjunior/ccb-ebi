<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Contracts\ConsultarEscala;
use App\Domain\Escalas\Contracts\EscalaRef;
use App\Domain\Escalas\Contracts\EscalaVisao;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\IntervaloSessao;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;

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
        private readonly DetectorConflito $conflitos,
    ) {}

    public function detalhe(string $escalaId, EscalaVisao $visao): ?array
    {
        $escala = Escala::query()->find($escalaId);

        return $escala === null ? null : $this->detalheDaEscala($escala, $visao);
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

    public function referenciaDaTroca(string $trocaId): ?EscalaRef
    {
        $membroId = (string) SolicitacaoTrocaEscala::query()->whereKey($trocaId)->value('escala_membro_id');

        return $membroId === '' ? null : $this->referenciaDoMembro($membroId);
    }

    public function ebisEmConflito(string $escalaId): array
    {
        $escala = Escala::query()->find($escalaId);

        return $escala === null ? [] : $this->conflitos->ebisDosConflitos($this->mapaDeConflitos($escala));
    }

    /**
     * Conflitos por usuario dos membros ativos da escala.
     *
     * @return array<string, array<int, IntervaloSessao>>
     */
    private function mapaDeConflitos(Escala $escala): array
    {
        $usuarioIds = $escala->membros()
            ->where('status', '!=', EscalaMembro::STATUS_SUBSTITUIDO)
            ->pluck('usuario_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()->values()->all();

        return $this->conflitos->conflitos($usuarioIds, $this->sessao($escala), (string) $escala->id);
    }

    /** @return array<string, mixed> */
    public function detalheDaEscala(Escala $escala, EscalaVisao $visao): array
    {
        $sessao = $this->sessao($escala);
        $membros = $escala->membros()->orderBy('created_at')->orderBy('id')->get();
        $semVinculo = $this->avaliador->semVinculo($escala, $sessao);
        $usuarioIds = $membros->pluck('usuario_id')->map(fn ($id): string => (string) $id)->unique()->values()->all();
        $nomes = $this->equipe->nomes($usuarioIds);
        $conflitos = $this->conflitos->conflitos($usuarioIds, $sessao, (string) $escala->id);

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
                'conflitos' => $this->conflitos->apresentar(
                    $conflitos[(string) $membro->usuario_id] ?? [],
                    $this->nomesDosConflitosLivres($conflitos[(string) $membro->usuario_id] ?? [], $membro, $visao),
                ),
            ])->all(),
            // Preenchido pela Task 12 (presenca da equipe).
            'presenca_equipe_pendente' => false,
        ];
    }

    /**
     * Nomes do conflito aparecem quando a leitura e interna, quando o conflito
     * e da propria pessoa ou quando o leitor alcanca o EBI da outra sessao.
     *
     * @param  array<int, IntervaloSessao>  $doUsuario
     */
    private function nomesDosConflitosLivres(array $doUsuario, EscalaMembro $membro, EscalaVisao $visao): bool
    {
        if ($visao->visaoAmpla || (string) $membro->usuario_id === $visao->leitorId) {
            return true;
        }

        foreach ($doUsuario as $outra) {
            if (in_array($outra->ebiId, $visao->ebisVisiveis, true)) {
                return true;
            }
        }

        return false;
    }

    public function minhas(string $usuarioId, string $inicio, string $fim): array
    {
        $vagas = EscalaMembro::query()
            ->where('usuario_id', $usuarioId)
            ->where('status', '!=', EscalaMembro::STATUS_SUBSTITUIDO)
            ->with('escala')
            ->get();

        /** @var array<int, string> $sessaoIds */
        $sessaoIds = [];
        /** @var array<int, EscalaMembro> $noPeriodo */
        $noPeriodo = [];

        foreach ($vagas as $vaga) {
            $escala = $vaga->escala;
            if ($escala instanceof Escala) {
                $sessaoIds[] = (string) $escala->sessao_id;
                $noPeriodo[] = $vaga;
            }
        }

        $intervalos = $this->sessoes->intervalos(array_values(array_unique($sessaoIds)));

        // O periodo e comparado com o horario previsto da sessao (util, em UTC).
        $de = CarbonImmutable::parse($inicio, 'UTC');
        $ate = CarbonImmutable::parse($fim, 'UTC');

        $linhas = [];

        foreach ($noPeriodo as $vaga) {
            $escala = $vaga->escala;
            if (! $escala instanceof Escala) {
                continue;
            }

            $sessao = $intervalos[(string) $escala->sessao_id] ?? null;

            if ($sessao === null
                || $sessao->inicioPrevisto->lessThan($de)
                || ! $sessao->inicioPrevisto->lessThan($ate)) {
                continue;
            }

            // Vaga da propria pessoa: o conflito aparece sempre com os nomes.
            $conflitos = $this->conflitos->conflitos([$usuarioId], $sessao, (string) $escala->id)[$usuarioId] ?? [];

            $linhas[] = [
                'escala_id' => (string) $escala->id,
                'membro_id' => (string) $vaga->id,
                'sessao_id' => $sessao->id,
                'inicio_previsto' => $sessao->inicioPrevisto->toIso8601String(),
                'fim_previsto' => $sessao->fimPrevisto->toIso8601String(),
                'sessao_status' => $sessao->status,
                'escala_status' => $this->statusDaEscala($escala, $sessao),
                'funcao' => (string) $vaga->funcao,
                'status' => (string) $vaga->status,
                'presente' => $vaga->presente,
                'conflitos' => $this->conflitos->apresentar($conflitos, true),
                '_ordem' => $sessao->inicioPrevisto->getTimestamp(),
            ];
        }

        usort($linhas, fn (array $a, array $b): int => $a['_ordem'] <=> $b['_ordem']);

        return array_map(function (array $linha): array {
            unset($linha['_ordem']);

            return $linha;
        }, $linhas);
    }

    public function ehMembroVigente(string $escalaId, string $usuarioId): bool
    {
        $escala = Escala::query()->find($escalaId);

        if ($escala === null) {
            return false;
        }

        $semVinculo = $this->avaliador->semVinculo($escala, $this->sessao($escala));
        $vagas = $escala->membros()->ativos()->where('usuario_id', $usuarioId)->get(['id']);

        foreach ($vagas as $vaga) {
            if (! isset($semVinculo[(string) $vaga->id])) {
                return true;
            }
        }

        return false;
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
        return new EscalaRef((string) $escala->id, (string) $escala->ebi_id, (string) $escala->sessao_id, (string) $escala->igreja_id);
    }
}
