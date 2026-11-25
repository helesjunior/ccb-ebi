<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Contracts\ConsultarVagaEscala;
use App\Domain\Escalas\Contracts\ProjecaoVaga;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\OfertaVagaEscala;
use App\Domain\Escalas\Models\VagaEscala;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\IntervaloSessao;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Leitura das vagas (decisao 8 e 9 da spec): o cartao publico nao mostra
 * nomes de quem se ofereceu nem quem foi substituido; o leitor com
 * `escala.ler` ve a contagem e quem foi escolhido; a dona e a supervisao veem
 * os nomes das ofertas. Os nomes sao buscados uma vez por resposta.
 */
class ConsultaVagaEscala implements ConsultarVagaEscala
{
    private const STATUS_SESSAO_ATIVOS = ['prevista', 'aberta'];

    public function __construct(
        private readonly ConsultarSessao $sessoes,
        private readonly ConsultarEquipeEbi $vinculos,
        private readonly ConsultarEbi $ebis,
        private readonly DetectorConflito $conflitos,
    ) {}

    public function doEbi(string $ebiId, ?string $inicio, ?string $fim, ?string $status, ProjecaoVaga $projecao): array
    {
        $vagas = $this->baseQuery($projecao, null)
            ->join('escala', 'escala.id', '=', 'vaga_escala.escala_id')
            ->join('sessao', 'sessao.id', '=', 'escala.sessao_id')
            ->where('escala.ebi_id', $ebiId)
            ->when($status !== null, fn ($q) => $q->where('vaga_escala.status', $status))
            ->when($inicio !== null, fn ($q) => $q->where('sessao.inicio_previsto', '>=', $inicio))
            ->when($fim !== null, fn ($q) => $q->where('sessao.inicio_previsto', '<', $fim))
            ->get();

        return $this->apresentar($vagas, $projecao, null);
    }

    public function daEscala(string $escalaId, ProjecaoVaga $projecao, ?string $leitorId): array
    {
        $sessaoId = (string) Escala::query()->whereKey($escalaId)->value('sessao_id');

        return $this->apresentar(
            $this->baseQuery($projecao, $leitorId)->where('vaga_escala.escala_id', $escalaId)->get(),
            $projecao,
            $leitorId,
            $sessaoId === '' ? null : $sessaoId,
        );
    }

    public function paraMim(string $usuarioId, array $escopos, string $inicio, string $fim): array
    {
        $ebiIds = array_values(array_unique(array_filter(array_column($escopos, 'ebi_id'))));
        $igrejaIds = array_values(array_unique(array_filter(array_column($escopos, 'igreja_id'))));

        if ($ebiIds === [] && $igrejaIds === []) {
            return [];
        }

        // O periodo vem em dias locais: um dia local pode cair em outro dia UTC.
        $de = CarbonImmutable::parse($inicio, 'America/Sao_Paulo')->startOfDay()->utc();
        $ate = CarbonImmutable::parse($fim, 'America/Sao_Paulo')->endOfDay()->addSecond()->utc();

        $vagas = $this->baseQuery(ProjecaoVaga::Publica, $usuarioId)
            ->join('escala', 'escala.id', '=', 'vaga_escala.escala_id')
            ->join('sessao', 'sessao.id', '=', 'escala.sessao_id')
            ->where(fn ($q) => $q
                ->whereIn('escala.ebi_id', $ebiIds)
                ->orWhereIn('escala.igreja_id', $igrejaIds))
            ->where('vaga_escala.status', VagaEscala::STATUS_ABERTA)
            ->whereIn('sessao.status', self::STATUS_SESSAO_ATIVOS)
            ->where('sessao.inicio_previsto', '>=', $de)
            ->where('sessao.inicio_previsto', '<', $ate)
            ->whereNotIn('vaga_escala.escala_id', EscalaMembro::query()
                ->where('escala_membro.usuario_id', $usuarioId)
                ->where('escala_membro.status', '!=', EscalaMembro::STATUS_SUBSTITUIDO)
                ->select('escala_membro.escala_id'))
            ->get();

        return $this->apresentar($vagas, ProjecaoVaga::Publica, $usuarioId);
    }

    public function uma(string $vagaId, ProjecaoVaga $projecao, ?string $leitorId): ?array
    {
        $vagas = $this->baseQuery($projecao, $leitorId)->where('vaga_escala.id', $vagaId)->get();

        return $this->apresentar($vagas, $projecao, $leitorId)[0] ?? null;
    }

    /**
     * @return Builder<VagaEscala>
     */
    private function baseQuery(ProjecaoVaga $projecao, ?string $leitorId): Builder
    {
        $query = VagaEscala::query()
            ->select('vaga_escala.*')
            ->with([
                'escala' => fn ($q) => $q->select('escala.id', 'escala.ebi_id', 'escala.equipe_id', 'escala.sessao_id'),
                'escala.equipe' => fn ($q) => $q->select('equipe.id', 'equipe.coordenadora_id'),
            ])
            ->orderBy('vaga_escala.created_at')
            ->orderBy('vaga_escala.id');

        if ($projecao === ProjecaoVaga::Publica) {
            $query->with(['ofertas' => fn ($q) => $q->where('usuario_id', $leitorId ?? '')]);
        }

        return $query;
    }

    /**
     * @param  iterable<int, VagaEscala>  $vagas
     * @return list<array<string, mixed>>
     */
    private function apresentar(iterable $vagas, ProjecaoVaga $projecao, ?string $leitorId, ?string $sessaoDaEscala = null): array
    {
        $lista = [];

        foreach ($vagas as $vaga) {
            $lista[] = $vaga;
        }

        $sessoesIds = $this->escalaIds($lista);

        if ($sessaoDaEscala !== null && ! in_array($sessaoDaEscala, $sessoesIds, true)) {
            $sessoesIds[] = $sessaoDaEscala;
        }

        if ($sessoesIds === []) {
            return [];
        }

        $sessoes = $this->sessoes->intervalos($sessoesIds);

        if ($lista === []) {
            return [];
        }
        $nomes = $this->nomes($lista, $projecao);
        $ebis = $this->ebisNomes($lista);
        $conflitos = $this->conflitosDosLeitores($lista, $projecao, $leitorId, $sessoes);

        return array_map(
            fn (VagaEscala $vaga): array => $this->card($vaga, $projecao, $leitorId, $sessoes, $nomes, $ebis, $conflitos),
            $lista,
        );
    }

    /**
     * @param  list<VagaEscala>  $vagas
     * @return list<string>
     */
    private function escalaIds(array $vagas): array
    {
        $ids = [];
        foreach ($vagas as $vaga) {
            $sessaoId = (string) $vaga->escala?->sessao_id;
            if ($sessaoId !== '') {
                $ids[$sessaoId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  list<VagaEscala>  $vagas
     * @return array<string, string>
     */
    private function ebisNomes(array $vagas): array
    {
        $nomes = [];
        foreach ($vagas as $vaga) {
            $ebiId = (string) $vaga->escala?->ebi_id;
            if ($ebiId !== '' && ! isset($nomes[$ebiId])) {
                // EBI removida no meio do caminho nao pode derrubar a leitura.
                $ebi = $this->ebis->dados($ebiId);
                $nomes[$ebiId] = $ebi === null ? '' : $ebi->nome;
            }
        }

        return $nomes;
    }

    /**
     * @param  list<VagaEscala>  $vagas
     * @return array<string, string>
     */
    private function nomes(array $vagas, ProjecaoVaga $projecao): array
    {
        if ($projecao === ProjecaoVaga::Publica) {
            return [];
        }

        $ids = [];
        foreach ($vagas as $vaga) {
            $ids[] = (string) $vaga->escala?->equipe?->coordenadora_id;
            $ids[] = (string) $vaga->substituido_usuario_id;

            if ($projecao === ProjecaoVaga::Gestao) {
                foreach ($vaga->ofertas as $oferta) {
                    $ids[] = (string) $oferta->usuario_id;
                }
            }

            foreach ($vaga->ofertas as $oferta) {
                if ($oferta->status === OfertaVagaEscala::STATUS_ACEITA) {
                    $ids[] = (string) $oferta->usuario_id;
                }
            }
        }

        return $this->vinculos->nomes(array_values(array_unique(array_filter($ids))));
    }

    /**
     * Conflitos do leitor no cartao publico e das ofertas nas demais
     * projecoes, calculados de uma vez por sessao.
     *
     * @param  list<VagaEscala>  $vagas
     * @param  array<string, IntervaloSessao>  $sessoes
     * @return array<string, array<string, array<int, IntervaloSessao>>>
     */
    private function conflitosDosLeitores(array $vagas, ProjecaoVaga $projecao, ?string $leitorId, array $sessoes): array
    {
        $porEscala = [];

        foreach ($vagas as $vaga) {
            $escalaId = (string) $vaga->escala_id;
            $intervalo = $sessoes[(string) $vaga->escala?->sessao_id] ?? null;

            if ($intervalo === null) {
                continue;
            }

            $usuarios = [];
            if ($projecao === ProjecaoVaga::Publica && $leitorId !== null) {
                $usuarios[] = $leitorId;
            }
            if ($projecao === ProjecaoVaga::Gestao) {
                foreach ($vaga->ofertas as $oferta) {
                    $usuarios[] = (string) $oferta->usuario_id;
                }
            }

            $usuarios = array_values(array_unique($usuarios));

            if ($usuarios !== []) {
                $porEscala[$escalaId] = $this->conflitos->conflitos($usuarios, $intervalo, $escalaId);
            }
        }

        return $porEscala;
    }

    /**
     * @param  array<string, IntervaloSessao>  $sessoes
     * @param  array<string, string>  $nomes
     * @param  array<string, string>  $ebis
     * @param  array<string, array<string, array<int, IntervaloSessao>>>  $conflitos
     * @return array<string, mixed>
     */
    private function card(VagaEscala $vaga, ProjecaoVaga $projecao, ?string $leitorId, array $sessoes, array $nomes, array $ebis, array $conflitos): array
    {
        $escala = $vaga->escala;
        $sessao = $sessoes[(string) $escala?->sessao_id] ?? null;
        $pendentes = $vaga->ofertas->where('status', OfertaVagaEscala::STATUS_PENDENTE);
        $aceita = $vaga->ofertas->firstWhere('status', OfertaVagaEscala::STATUS_ACEITA);
        $donaId = (string) $escala?->equipe?->coordenadora_id;
        $destaEscala = $conflitos[(string) $vaga->escala_id] ?? [];

        $card = [
            'id' => (string) $vaga->id,
            'escala_id' => (string) $vaga->escala_id,
            'sessao' => $sessao === null ? null : [
                'id' => $sessao->id,
                'inicio_previsto' => $sessao->inicioPrevisto->toIso8601String(),
                'fim_previsto' => $sessao->fimPrevisto->toIso8601String(),
            ],
            'ebi_id' => (string) $escala?->ebi_id,
            'ebi_nome' => $ebis[(string) $escala?->ebi_id] ?? '',
            'dona_nome' => $nomes[$donaId] ?? '',
            'observacao' => $vaga->observacao,
            'tipo' => $vaga->substitui_membro_id === null ? 'acrescimo' : 'substituicao',
            'status' => (string) $vaga->status,
            'expirada' => $vaga->estaAberta() && ($sessao === null || ! in_array($sessao->status, self::STATUS_SESSAO_ATIVOS, true)),
        ];

        if ($projecao === ProjecaoVaga::Publica) {
            $minha = $leitorId === null ? null : $vaga->ofertas->firstWhere('usuario_id', $leitorId);

            return $card + [
                'minha_oferta' => $minha === null ? null : ['id' => (string) $minha->id, 'status' => (string) $minha->status],
                'conflitos' => $this->conflitos->apresentar($leitorId === null ? [] : ($destaEscala[$leitorId] ?? []), true),
            ];
        }

        $card['ofertas_pendentes'] = $pendentes->count();
        $card['motivo_encerramento'] = $vaga->motivo_encerramento;

        if ($projecao === ProjecaoVaga::Leitor) {
            $substituido = $vaga->substituido_usuario_id === null ? null : (string) $vaga->substituido_usuario_id;
            $card['substituido_nome'] = $substituido === null ? null : ($nomes[$substituido] ?? '');
        }

        if ($projecao === ProjecaoVaga::Gestao) {
            $card['escolhido_nome'] = $aceita === null ? null : ($nomes[(string) $aceita->usuario_id] ?? '');
            $card['ofertas'] = $pendentes->map(function (OfertaVagaEscala $oferta) use ($nomes, $destaEscala): array {
                return [
                    'id' => (string) $oferta->id,
                    'usuario_id' => (string) $oferta->usuario_id,
                    'nome' => $nomes[(string) $oferta->usuario_id] ?? '',
                    'status' => (string) $oferta->status,
                    'conflitos' => $this->conflitos->apresentar($destaEscala[(string) $oferta->usuario_id] ?? [], true),
                ];
            })->values()->all();
        }

        return $card;
    }
}
