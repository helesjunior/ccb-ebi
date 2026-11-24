<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Sessoes\Contracts\ConsultarIndicadoresPresenca;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;

class ConsultaIndicadoresPresenca implements ConsultarIndicadoresPresenca
{
    private const POR_PAGINA = 500;

    public function __construct(private readonly ConsultarOrganizacao $organizacao) {}

    public function totais(array $igrejaIds, array $ebiIds, string $inicio, string $fim, array $canonico = []): array
    {
        /** @var array<string, array{participantes_distintos: int, presencas: int, sessoes_realizadas: int, ids: array<string, true>}> $totais */
        $totais = [];
        foreach ($igrejaIds as $id) {
            $totais["igreja:{$id}"] = $this->vazio() + ['ids' => []];
        }
        foreach ($ebiIds as $id) {
            $totais["ebi:{$id}"] = $this->vazio() + ['ids' => []];
        }

        // Sessoes realizadas e presencas por sessao: a contagem de presenca e
        // refeita em PHP para deduplicar pelo id canonico.
        $presencasPorSessao = [];
        $this->cadaSessaoDoPeriodo($inicio, $fim, function (array $sessao) use (&$presencasPorSessao): void {
            $presencasPorSessao[$sessao['id']] = 0;
        });

        if ($presencasPorSessao !== []) {
            $this->cadaPresencaValida(array_keys($presencasPorSessao), function (array $linha) use (&$presencasPorSessao): void {
                $presencasPorSessao[$linha['sessao_id']] += 1;
            });
        }

        // Participantes distintos por sessao, pelo id canonico.
        $distintosPorSessao = [];
        $this->cadaPresencaValida(array_keys($presencasPorSessao), function (array $linha) use (&$distintosPorSessao, $canonico): void {
            $principal = $canonico[$linha['participante_id']] ?? $linha['participante_id'];
            $distintosPorSessao[$linha['sessao_id']][$principal] = true;
        });

        $this->cadaSessaoDoPeriodo($inicio, $fim, function (array $sessao) use (&$totais, $presencasPorSessao, $distintosPorSessao): void {
            $chaves = [];
            if ($sessao['igreja_id'] !== null && isset($totais["igreja:{$sessao['igreja_id']}"])) {
                $chaves[] = "igreja:{$sessao['igreja_id']}";
            }
            if ($sessao['ebi_id'] !== null && isset($totais["ebi:{$sessao['ebi_id']}"])) {
                $chaves[] = "ebi:{$sessao['ebi_id']}";
            }

            $nomes = array_keys($distintosPorSessao[$sessao['id']] ?? []);
            $quantasPresencas = $presencasPorSessao[$sessao['id']] ?? 0;
            foreach ($chaves as $chave) {
                $total = $totais[$chave];
                $total['participantes_distintos'] += count($nomes);
                $total['presencas'] += $quantasPresencas;
                $total['sessoes_realizadas'] += 1;
                foreach ($nomes as $nome) {
                    $total['ids'][$nome] = true;
                }
                $totais[$chave] = $total;
            }
        });

        return $totais;
    }

    public function fusos(array $igrejaIds): array
    {
        return $this->organizacao->fusosDasIgrejas($igrejaIds);
    }

    public function sessoesPrevistas(array $igrejaIds, array $ebiIds, string $inicio, string $fim, CarbonImmutable $geracao): array
    {
        if ($igrejaIds === [] && $ebiIds === []) {
            return [];
        }

        $igrejasAlvo = array_fill_keys(array_map('strval', $igrejaIds), true);
        $ebisAlvo = array_fill_keys(array_map('strval', $ebiIds), true);

        $fusos = $this->organizacao->fusosDasIgrejas(array_values(array_unique(
            Sessao::query()->whereNotNull('igreja_id')->distinct()->pluck('igreja_id')->map(fn ($id): string => (string) $id)->all(),
        )));

        $sessoes = [];

        Sessao::query()
            ->select(['sessao.id', 'sessao.igreja_id', 'sessao.ebi_id', 'sessao.inicio_previsto'])
            // A sessao so conta depois que deveria ter acontecido (M-06).
            ->whereIn('sessao.status', [Sessao::STATUS_PREVISTA, Sessao::STATUS_ABERTA, Sessao::STATUS_ENCERRADA])
            ->where('sessao.inicio_previsto', '<=', $geracao->utc())
            ->orderBy('sessao.inicio_previsto')
            ->chunkById(self::POR_PAGINA, function ($pagina) use (&$sessoes, $fusos, $igrejasAlvo, $ebisAlvo, $inicio, $fim): void {
                foreach ($pagina as $sessao) {
                    $fuso = $fusos[(string) $sessao->igreja_id] ?? null;
                    if ($fuso === null) {
                        continue;
                    }

                    $local = CarbonImmutable::parse($sessao->inicio_previsto, 'UTC')->setTimezone($fuso)->format('Y-m-d');
                    if ($local < $inicio || $local > $fim) {
                        continue;
                    }

                    $chaves = [];
                    if (isset($igrejasAlvo[(string) $sessao->igreja_id])) {
                        $chaves[] = 'igreja:'.$sessao->igreja_id;
                    }
                    if (isset($ebisAlvo[(string) $sessao->ebi_id])) {
                        $chaves[] = 'ebi:'.$sessao->ebi_id;
                    }

                    foreach ($chaves as $chave) {
                        $sessoes[] = ['id' => (string) $sessao->id, 'chave' => $chave, 'data_local' => $local];
                    }
                }
            });

        return $sessoes;
    }

    public function participantesComPresencaDesde(array $canonico, CarbonImmutable $desde, ?CarbonImmutable $ate = null): array
    {
        $ids = [];
        $this->cadaPresencaValida(null, function (array $linha) use (&$ids, $canonico): void {
            $ids[$canonico[$linha['participante_id']] ?? $linha['participante_id']] = true;
        }, $desde, $ate);

        return array_keys($ids);
    }

    public function igrejasComPresencaDesde(array $canonico, CarbonImmutable $desde, ?CarbonImmutable $ate = null): array
    {
        $porIgreja = [];
        $this->cadaPresencaValida(null, function (array $linha) use (&$porIgreja, $canonico): void {
            if ($linha['igreja_id'] === null) {
                return;
            }
            $porIgreja[$linha['igreja_id']][$canonico[$linha['participante_id']] ?? $linha['participante_id']] = true;
        }, $desde, $ate);

        return $porIgreja;
    }

    /**
     * Presenca valida por EBI: ebi_id => [id canonico => true]. A EBI da
     * sessao faz esse papel (o relatorio nao atribui participante a EBI por
     * igreja de referencia).
     *
     * @param  array<string, string>  $canonico
     * @return array<string, array<string, true>>
     */
    public function ebisComPresencaDesde(array $canonico, CarbonImmutable $desde, ?CarbonImmutable $ate = null): array
    {
        $porEbi = [];
        $this->cadaPresencaValida(null, function (array $linha) use (&$porEbi, $canonico): void {
            if ($linha['ebi_id'] === null) {
                return;
            }
            $porEbi[$linha['ebi_id']][$canonico[$linha['participante_id']] ?? $linha['participante_id']] = true;
        }, $desde, $ate);

        return $porEbi;
    }

    public function ebisComSessaoDesde(CarbonImmutable $desde, ?CarbonImmutable $ate = null): array
    {
        $consulta = fn () => Sessao::query()
            ->where('realizada', true)
            ->whereDate('inicio_previsto', '>=', $desde->toDateString());

        if ($ate !== null) {
            return $consulta()
                ->where('inicio_previsto', '<=', $ate->utc()->format('Y-m-d H:i:s'))
                ->whereNotNull('ebi_id')
                ->distinct()
                ->pluck('ebi_id')
                ->all();
        }

        return $consulta()
            ->whereNotNull('ebi_id')
            ->distinct()
            ->pluck('ebi_id')
            ->all();
    }

    /** @return array{participantes_distintos: int, presencas: int, sessoes_realizadas: int} */
    private function vazio(): array
    {
        return ['participantes_distintos' => 0, 'presencas' => 0, 'sessoes_realizadas' => 0];
    }

    /**
     * Percorre em paginas as sessoes realizadas cuja data local de inicio
     * cai no periodo [inicio, fim] (datas `Y-m-d` no fuso da igreja).
     *
     * O fuso vem do contrato de Organizacao: Sessoes nao le a tabela de
     * igrejas (ADR-0002). Igreja sem fuso conhecido fica de fora.
     *
     * @param  callable(array{id: string, igreja_id: ?string, ebi_id: ?string}): void  $callback
     */
    private function cadaSessaoDoPeriodo(string $inicio, string $fim, callable $callback): void
    {
        $fusos = $this->organizacao->fusosDasIgrejas(array_values(array_unique(
            Sessao::query()->whereNotNull('igreja_id')->distinct()->pluck('igreja_id')->all(),
        )));

        $ultimoId = '';
        do {
            $pagina = Sessao::query()
                ->select(['sessao.id', 'sessao.igreja_id', 'sessao.ebi_id', 'sessao.inicio_previsto'])
                ->where('sessao.status', Sessao::STATUS_ENCERRADA)
                ->where('sessao.realizada', true)
                ->where('sessao.id', '>', $ultimoId)
                ->orderBy('sessao.id')
                ->limit(self::POR_PAGINA)
                ->get();

            foreach ($pagina as $sessao) {
                $fuso = $fusos[$sessao->igreja_id] ?? null;
                if ($fuso === null) {
                    continue;
                }

                $local = CarbonImmutable::parse($sessao->inicio_previsto, 'UTC')
                    ->setTimezone($fuso)
                    ->format('Y-m-d');

                if ($local >= $inicio && $local <= $fim) {
                    $callback([
                        'id' => (string) $sessao->id,
                        'igreja_id' => $sessao->igreja_id,
                        'ebi_id' => $sessao->ebi_id,
                    ]);
                }
            }

            $ultimoId = (string) ($pagina->last()->id ?? '');
        } while ($pagina->count() === self::POR_PAGINA);
    }

    /**
     * Percorre em paginas as presencas validas, opcionalmente restritas a um
     * conjunto de sessoes e/ou a partir de uma data de inicio.
     *
     * @param  array<int, string>|null  $sessaoIds
     * @param  callable(array{sessao_id: string, participante_id: string, igreja_id: ?string, ebi_id: ?string}): void  $callback
     */
    private function cadaPresencaValida(?array $sessaoIds, callable $callback, ?CarbonImmutable $desde = null, ?CarbonImmutable $ate = null): void
    {
        if ($sessaoIds === []) {
            return;
        }

        $ultimoId = '';
        do {
            $consulta = Presenca::query()
                ->select(['presenca.id', 'presenca.sessao_id', 'presenca.participante_id', 'sessao.igreja_id', 'sessao.ebi_id'])
                ->join('sessao', 'sessao.id', '=', 'presenca.sessao_id')
                ->where('presenca.status', Presenca::STATUS_VALIDA)
                ->where('presenca.id', '>', $ultimoId)
                ->orderBy('presenca.id')
                ->limit(self::POR_PAGINA);

            if (is_array($sessaoIds)) {
                $consulta->whereIn('presenca.sessao_id', $sessaoIds);
            }
            if ($desde !== null) {
                $consulta->where('sessao.inicio_previsto', '>=', $desde->utc()->format('Y-m-d H:i:s'));
            }
            if ($ate !== null) {
                $consulta->where('sessao.inicio_previsto', '<=', $ate->utc()->format('Y-m-d H:i:s'));
            }

            // Join com sessao: igreja_id e ebi_id vem de la, fora do @property
            // do model Presenca. Lidos pelo atributo para nao depender dele.
            $pagina = $consulta->get();

            foreach ($pagina as $presenca) {
                $callback([
                    'sessao_id' => (string) $presenca->getAttribute('sessao_id'),
                    'participante_id' => (string) $presenca->getAttribute('participante_id'),
                    'igreja_id' => $presenca->getAttribute('igreja_id'),
                    'ebi_id' => $presenca->getAttribute('ebi_id'),
                ]);
            }

            $ultimoId = (string) ($pagina->last()->id ?? '');
        } while ($pagina->count() === self::POR_PAGINA);
    }
}
