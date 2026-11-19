<?php

namespace App\Domain\Relatorios\Services;

use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Participantes\Contracts\ConsultarIndicadoresParticipante;
use App\Domain\Relatorios\Contracts\ConsultarRelatorio;
use App\Domain\Sessoes\Contracts\ConsultarIndicadoresPresenca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RelatorioPresenca implements ConsultarRelatorio
{
    public const LIMIAR = 5;

    /**
     * Indicadores escondidos pelo limiar, na ordem em que aparecem na resposta.
     * `sessoes_realizadas` e `igrejas_com_ebi_ativo` nunca somem: a contagem de
     * sessoes nao identifica pessoa, e M-09 e o indicador de cobertura.
     */
    private const SUPRIMIVEIS = [
        'participantes_distintos',
        'presencas',
        'media_por_sessao',
        'participantes_cadastrados',
        'participantes_ativos',
    ];

    /** Indicadores que a cascata do filho suprimido alcanca. */
    private const CASCATA = [
        'participantes_distintos',
        'presencas',
        'media_por_sessao',
        'participantes_cadastrados',
        'participantes_ativos',
    ];

    private const TIPOS = ['central', 'regional', 'setor', 'igreja', 'ebi'];

    public function __construct(
        private readonly ConsultarHierarquia $hierarquia,
        private readonly ConsultarOrganizacao $organizacao,
        private readonly ConsultarIndicadoresPresenca $presenca,
        private readonly ConsultarIndicadoresParticipante $participantes,
    ) {}

    public function presenca(string $tipo, string $id, string $inicio, string $fim, bool $suprimir): array
    {
        $geracao = CarbonImmutable::now('UTC');
        $canonico = $this->participantes->mapaCanonico();
        $iguais = $this->mesmoFuso($tipo, $this->fusosDoNo($tipo, $id));

        $buffer = [];
        $raiz = $this->resultadosDoNo($tipo, $id, $inicio, $fim, $suprimir, $canonico, $geracao, $buffer);

        $no = $this->filhosBrutos($tipo, $id);
        $filhos = [];
        foreach ($raiz['filhos'] as $indice => $totais) {
            $filho = $no[$indice];
            $filhos[] = [
                'nivel' => $filho['tipo'],
                'id' => $filho['id'],
                'nome' => $filho['nome'],
                'totais' => $totais,
            ];
        }

        $fuso = $iguais;
        $nome = $this->nomes($tipo, [$id])[$id] ?? $id;

        return [
            'no' => ['nivel' => $tipo, 'id' => $id, 'nome' => $nome],
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'fuso' => $fuso,
            'gerado_em' => $geracao->toIso8601String(),
            'criterios' => $this->criterios(),
            'totais' => $raiz['totais'],
            'filhos' => $filhos,
        ];
    }

    /**
     * Calcula o no e os filhos diretos (de baixo para cima). Os totais de cada
     * no saem sempre da subarvore dele, nunca da soma dos filhos.
     *
     * @param  array<string, string>  $canonico
     * @param  array<string, array{situacao: string, totais: array<string, array{valor: int|float|null, suprimido: bool, motivo: ?string}>}>  $buffer
     * @return array{situacao: string, totais: array<string, array{valor: int|float|null, suprimido: bool, motivo: ?string}>, filhos: array<int, array<string, array{valor: int|float|null, suprimido: bool, motivo: ?string}>>}
     */
    private function resultadosDoNo(
        string $tipo,
        string $id,
        string $inicio,
        string $fim,
        bool $suprimir,
        array $canonico,
        CarbonImmutable $geracao,
        array &$buffer,
    ): array {
        $chave = "{$tipo}:{$id}";
        if (isset($buffer[$chave])) {
            return $buffer[$chave];
        }

        $crus = $this->medir($tipo, $id, $inicio, $fim, $canonico, $geracao);

        $filhos = [];
        $algumFilhoSuprimido = false;
        foreach ($this->filhosBrutos($tipo, $id) as $filho) {
            $resultado = $this->resultadosDoNo($filho['tipo'], $filho['id'], $inicio, $fim, $suprimir, $canonico, $geracao, $buffer);
            $filhos[] = $resultado['totais'];
            if ($resultado['situacao'] === 'suprimido') {
                $algumFilhoSuprimido = true;
            }
        }

        $totais = $this->avaliar($crus, $suprimir, $algumFilhoSuprimido);
        $situacao = $this->situacao($totais);

        $buffer[$chave] = ['situacao' => $situacao, 'totais' => $totais, 'filhos' => $filhos];

        return $buffer[$chave];
    }

    /**
     * Valores crus do no no periodo, numa consulta agregada sobre a subarvore.
     *
     * @param  array<string, string>  $canonico
     * @return array<string, int|float|null>
     */
    private function medir(string $tipo, string $id, string $inicio, string $fim, array $canonico, CarbonImmutable $geracao): array
    {
        $igrejas = $this->igrejasDo($tipo, $id);
        $ebis = $tipo === 'ebi' ? [$id] : [];

        $totais = $this->presenca->totais($igrejas, $ebis, $inicio, $fim, $canonico);
        $presencas = 0;
        $sessoes = 0;
        $distintos = [];
        foreach ($totais as $parcial) {
            $presencas += $parcial['presencas'];
            $sessoes += $parcial['sessoes_realizadas'];
            foreach (array_keys($parcial['ids']) as $participante) {
                $distintos[$participante] = true;
            }
        }

        $crus = [
            // M-02 vem sempre das presencas: quem veio em qualquer igreja ou
            // EBI da subarvore conta, mesmo com referencia em outra igreja
            // (a atribuicao por referencia vale so para M-01 e ativos).
            'participantes_distintos' => count($distintos),
            'presencas' => $presencas,
            'sessoes_realizadas' => $sessoes,
            'media_por_sessao' => $sessoes > 0 ? round($presencas / $sessoes, 1) : null,
        ];

        // M-01 e ativos: so por igreja de referencia; no EBI nao se aplica.
        $crus['participantes_cadastrados'] = null;
        $crus['participantes_ativos'] = null;
        $crus['igrejas_com_ebi_ativo'] = null;

        if ($tipo !== 'ebi') {
            $cadastrados = $this->participantes->cadastradosPorIgreja();
            // A presenca que torna alguem ativo vale em qualquer igreja (spec,
            // decisao 5); o filtro por igreja de referencia fica so na contagem.
            $presentes = $this->presenca->participantesComPresencaDesde(
                $canonico,
                $geracao->subMonths(12),
                $geracao,
            );
            $idsAtivos = $this->participantes->idsAtivos($canonico, $geracao->subDays(90), $presentes);
            $igrejaDe = $this->participantes->igrejaPorParticipante($canonico, $idsAtivos);

            $cadastradosNaSubarvore = 0;
            $ativosNaSubarvore = 0;
            foreach ($igrejas as $igreja) {
                $cadastradosNaSubarvore += $cadastrados[$igreja] ?? 0;
            }
            foreach ($idsAtivos as $ativo) {
                $igreja = $igrejaDe[$ativo] ?? null;
                // Ativo de igreja fora da subarvore nao entra na conta do no.
                if ($igreja !== null && in_array($igreja, $igrejas, true)) {
                    $ativosNaSubarvore++;
                }
            }

            $crus['participantes_cadastrados'] = $cadastradosNaSubarvore;
            $crus['participantes_ativos'] = $ativosNaSubarvore;

            // M-09: igrejas da subarvore com ao menos um EBI ativo (sessao
            // realizada nos ultimos 90 dias). Nunca e suprimido.
            $ativos = array_flip($this->presenca->ebisComSessaoDesde($geracao->subDays(90), $geracao));
            $igrejasComEbiAtivo = [];
            foreach ($this->organizacao->igrejaDosEbis($this->hierarquia->ebisDaSubarvore($tipo, $id)) as $ebi => $igreja) {
                if (isset($ativos[$ebi])) {
                    $igrejasComEbiAtivo[$igreja] = true;
                }
            }
            $crus['igrejas_com_ebi_ativo'] = count($igrejasComEbiAtivo);
        }

        return $crus;
    }

    /**
     * Aplica a supressao (ADR-0020 + decisao 6a) sobre os valores crus.
     *
     * @param  array<string, int|float|null>  $crus
     * @return array<string, array{valor: int|float|null, suprimido: bool, motivo: ?string}>
     */
    private function avaliar(array $crus, bool $suprimir, bool $algumFilhoSuprimido): array
    {
        $totais = [];
        foreach ($crus as $indicador => $valor) {
            // M-09 conta igrejas, nao pessoas, e fica fora da supressao em todos
            // os niveis: nao passa pelo limiar nem pela cascata (decisao 5).
            if ($indicador === 'igrejas_com_ebi_ativo' && $valor !== null) {
                $totais[$indicador] = ['valor' => $valor, 'suprimido' => false, 'motivo' => null];

                continue;
            }

            // A contagem de sessoes conta sessoes, nao pessoas; o valor de um no
            // nao identifica quem veio nem quantos vieram (decisao 5).
            if ($indicador === 'sessoes_realizadas' && $valor !== null) {
                $totais[$indicador] = ['valor' => $valor, 'suprimido' => false, 'motivo' => null];

                continue;
            }

            if ($indicador === 'media_por_sessao' && $valor === null) {
                $totais[$indicador] = ['valor' => null, 'suprimido' => false, 'motivo' => null];

                continue;
            }

            if ($valor === null) {
                $totais[$indicador] = ['valor' => null, 'suprimido' => false, 'motivo' => 'nao_se_aplica'];

                continue;
            }

            if (! $suprimir) {
                $totais[$indicador] = ['valor' => $valor, 'suprimido' => false, 'motivo' => null];

                continue;
            }

            if ($indicador === 'media_por_sessao') {
                $total = ['valor' => $valor, 'suprimido' => false, 'motivo' => null];
            } elseif ($valor >= 1 && $valor < self::LIMIAR) {
                $total = ['valor' => null, 'suprimido' => true, 'motivo' => 'limiar'];
            } else {
                $total = ['valor' => $valor, 'suprimido' => false, 'motivo' => null];
            }

            $totais[$indicador] = $total;
        }

        if ($suprimir && $algumFilhoSuprimido) {
            foreach ($totais as $indicador => $total) {
                // Nao se aplica (null) e media sem sessao (null) nunca viram
                // cascata: nao ha valor a esconder.
                if (! in_array($indicador, self::CASCATA, true) || $crus[$indicador] === null) {
                    continue;
                }
                $totais[$indicador] = ['valor' => null, 'suprimido' => true, 'motivo' => 'filho_suprimido'];
            }
        }

        // A media herda a supressao de M-03 (a media e presencas/sessoes do
        // mesmo periodo; sem presenca escondida, ela segue visivel).
        if (in_array($totais['presencas']['motivo'], ['limiar', 'filho_suprimido'], true)) {
            $totais['media_por_sessao'] = [
                'valor' => null,
                'suprimido' => true,
                'motivo' => $totais['presencas']['motivo'],
            ];
        }

        return $totais;
    }

    /**
     * Situacao do no para a cascata: suprimido quando algum indicador
     * suprimivel foi escondido (limiar ou cascata).
     *
     * @param  array<string, array{valor: int|float|null, suprimido: bool, motivo: ?string}>  $totais
     */
    private function situacao(array $totais): string
    {
        foreach (self::SUPRIMIVEIS as $indicador) {
            if ($totais[$indicador]['suprimido']) {
                return 'suprimido';
            }
        }

        return 'visivel';
    }

    /**
     * Filhos diretos no formato de `ConsultarHierarquia::filhos`, vazio para EBI.
     *
     * @return array<int, array{tipo: string, id: string, nome: string}>
     */
    private function filhosBrutos(string $tipo, string $id): array
    {
        return $tipo === 'ebi' ? [] : $this->hierarquia->filhos($tipo, $id);
    }

    /** @return array<int, string> */
    private function igrejasDo(string $tipo, string $id): array
    {
        return $this->hierarquia->igrejasDaSubarvore($tipo, $id);
    }

    /**
     * Fuso declarado no relatorio: o da subarvore quando uniforme. Com fusos
     * diferentes (inclusive no EBI, cujo periodo depende do fuso da igreja),
     * declara "fuso de cada igreja" em vez de escolher um deles.
     *
     * @param  array<string, string>  $fusos
     */
    private function mesmoFuso(string $tipo, array $fusos): string
    {
        $unicos = array_unique(array_values($fusos));
        if (count($unicos) === 1) {
            return $unicos[0];
        }

        return $unicos === [] ? 'America/Sao_Paulo' : 'fuso de cada igreja';
    }

    /**
     * Fusos das igrejas que o no abrange. O EBI usa o fuso da igreja dele:
     * e ele que recorta a data local das sessoes do periodo.
     *
     * @return array<string, string>
     */
    private function fusosDoNo(string $tipo, string $id): array
    {
        if ($tipo !== 'ebi') {
            return $this->presenca->fusos($this->igrejasDo($tipo, $id));
        }

        $igreja = $this->organizacao->igrejaDosEbis([$id])[$id] ?? null;

        return $igreja === null ? [] : $this->presenca->fusos([$igreja]);
    }

    /**
     * Nomes de exibicao do no (igreja/regional/setor/central/EBI).
     *
     * @param  array<int, string>  $ids
     * @return array<string, string>
     */
    private function nomes(string $tipo, array $ids): array
    {
        return $this->organizacao->nomes($tipo, $ids);
    }

    private function criterios(): string
    {
        return Str::limit(implode(' ', [
            'Participantes sem igreja de referencia nao entram em cadastrados nem em ativos, e so contam na igreja de referencia.',
            'Presencas com status valida; sessoes encerradas com realizada; participantes com status ativo.',
            'Participantes distintos e presencas somam as igrejas da unidade; sessoes realizadas e media usam as sessoes da unidade; tudo no periodo escolhido.',
            'Cadastrados, ativos (presenca valida nos ultimos 12 meses ou cadastro nos ultimos 90 dias) e igrejas com EBI ativo (sessao realizada nos ultimos 90 dias) contam pela igreja de referencia, com janela ate a data de geracao.',
            'No EBI, cadastrados, ativos e igrejas com EBI ativo nao se aplicam: o EBI e medido por participantes, presencas, sessoes e media.',
            'Valores entre 1 e 4 sao suprimidos para quem atua acima da igreja; um filho suprimido suprime os indicadores de pessoas e de sessao do pai.',
            'Sessoes realizadas e igrejas com EBI ativo nunca sao suprimidas.',
            'A data de cada sessao usa o fuso da igreja dele.',
        ]), 2000, '');
    }
}
