<?php

namespace App\Support\Relatorios;

/**
 * CSV do relatorio de presenca (decisao 10 da spec): uma linha para o no e uma
 * por filho direto, com os mesmos numeros e a mesma supressao do JSON.
 */
class ExportadorCsv
{
    /** Indicadores na ordem das colunas. */
    public const INDICADORES = [
        'participantes_distintos',
        'presencas',
        'sessoes_realizadas',
        'media_por_sessao',
        'participantes_cadastrados',
        'participantes_ativos',
        'igrejas_com_ebi_ativo',
    ];

    /** Prefixos que uma planilha executaria como formula. */
    private const FORMULA = ['=', '+', '-', '@'];

    /**
     * @param  array<string, mixed>  $relatorio  payload de `ConsultarRelatorio::presenca`
     */
    public function gerar(array $relatorio): string
    {
        $cabecalho = array_merge(
            ['nivel', 'id', 'nome'],
            self::INDICADORES,
            ['periodo_inicio', 'periodo_fim', 'fuso', 'gerado_em', 'criterios'],
        );

        $linhas = [$cabecalho];
        foreach ([$relatorio['no'] + ['totais' => $relatorio['totais']], ...$relatorio['filhos']] as $no) {
            $linhas[] = array_merge(
                [$no['nivel'], $no['id'], $no['nome']],
                array_map(fn (string $indicador): string => $this->celula($no['totais'][$indicador]), self::INDICADORES),
                [
                    $relatorio['periodo']['inicio'],
                    $relatorio['periodo']['fim'],
                    $relatorio['fuso'],
                    $relatorio['gerado_em'],
                    $relatorio['criterios'],
                ],
            );
        }

        return implode("\n", array_map(fn (array $linha): string => $this->linha($linha), $linhas))."\n";
    }

    /**
     * Nome base do arquivo. Nunca leva nome de igreja nem de pessoa.
     */
    public function nomeDoArquivo(array $relatorio): string
    {
        return sprintf(
            'relatorio-presenca-%s-%s_%s.csv',
            $relatorio['no']['nivel'],
            $relatorio['periodo']['inicio'],
            $relatorio['periodo']['fim'],
        );
    }

    /**
     * @param  array{valor: int|float|null, suprimido: bool, motivo: ?string}  $indicador
     */
    private function celula(array $indicador): string
    {
        if ($indicador['motivo'] === 'nao_se_aplica') {
            return '';
        }

        if ($indicador['suprimido']) {
            return $indicador['motivo'] === 'limiar' ? '< 5' : 'Não divulgado';
        }

        $valor = $indicador['valor'];

        return is_float($valor) ? number_format($valor, 1, '.', '') : (string) $valor;
    }

    /**
     * @param  array<int, string>  $valores
     */
    private function linha(array $valores): string
    {
        $saida = fopen('php://temp', 'r+');
        fputcsv($saida, array_map(fn (string $valor): string => $this->neutralizar($valor), $valores), escape: '');
        rewind($saida);
        $linha = (string) stream_get_contents($saida);
        fclose($saida);

        return rtrim($linha, "\n");
    }

    private function neutralizar(string $valor): string
    {
        return $valor !== '' && in_array($valor[0], self::FORMULA, true) ? "'".$valor : $valor;
    }
}
