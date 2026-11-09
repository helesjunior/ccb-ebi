<?php

namespace App\Domain\Organizacao\Services;

use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa a hierarquia de Brasilia a partir de um CSV.
 *
 * Idempotente por `codigo`: rodar o mesmo arquivo duas vezes nao duplica.
 * A chave natural de deduplicacao e o codigo oficial da CCB.
 */
class ImportadorBrasilia
{
    /**
     * @return array{criados: int, atualizados: int, ignorados: int}
     */
    public function importar(string $caminho): array
    {
        if (! is_file($caminho)) {
            throw new RuntimeException("Arquivo CSV nao encontrado: {$caminho}");
        }

        $handle = fopen($caminho, 'r');

        if ($handle === false) {
            throw new RuntimeException("Nao foi possivel abrir o CSV: {$caminho}");
        }

        /** @var list<string> $cabecalho */
        $cabecalho = fgetcsv($handle, escape: '\\') ?: [];
        $qtdColunas = count($cabecalho);

        $obrigatorias = ['regional_codigo', 'regional_nome', 'setor_codigo', 'setor_nome', 'igreja_codigo', 'igreja_nome'];
        $faltantes = array_diff($obrigatorias, $cabecalho);

        if ($faltantes !== []) {
            fclose($handle);

            throw new RuntimeException('Cabecalho do CSV invalido; colunas obrigatorias ausentes: '.implode(', ', $faltantes).'.');
        }

        $criados = 0;
        $atualizados = 0;
        $ignorados = 0;
        $linha = 1;

        // Transacao unica: uma linha invalida aborta a importacao inteira, sem
        // gravar parcialmente (a operacao e tudo-ou-nada).
        try {
            DB::transaction(function () use ($handle, $cabecalho, $qtdColunas, &$criados, &$atualizados, &$ignorados, &$linha): void {
                while (($dados = fgetcsv($handle, escape: '\\')) !== false) {
                    $linha++;

                    if ($dados === [null]) {
                        continue;
                    }

                    /** @var array<string, string|null> $registro */
                    $registro = array_combine(
                        $cabecalho,
                        array_pad(array_map(fn ($v) => is_string($v) ? $v : null, $dados), $qtdColunas, null),
                    );

                    $igrejaCodigo = trim((string) ($registro['igreja_codigo'] ?? ''));

                    if ($igrejaCodigo === '') {
                        throw new RuntimeException("Linha {$linha}: coluna 'igreja_codigo' obrigatoria.");
                    }

                    $regionalCodigo = trim((string) ($registro['regional_codigo'] ?? ''));
                    $setorCodigo = trim((string) ($registro['setor_codigo'] ?? ''));

                    if ($regionalCodigo === '' || $setorCodigo === '') {
                        throw new RuntimeException("Linha {$linha}: 'regional_codigo' e 'setor_codigo' obrigatorios.");
                    }

                    $central = CentralNacional::query()->firstOrCreate(['nome' => 'Central (Brás)']);

                    $regional = Regional::query()->where('codigo', $regionalCodigo)->first();
                    if ($regional === null) {
                        $regional = Regional::query()->create([
                            'central_nacional_id' => $central->id,
                            'nome' => trim((string) $registro['regional_nome']),
                            'codigo' => $regionalCodigo,
                            'status' => 'ativo',
                        ]);
                        $criados++;
                    } else {
                        $regional->fill(['nome' => trim((string) $registro['regional_nome'])])->save();
                        $atualizados++;
                    }

                    $setor = Setor::query()->where('codigo', $setorCodigo)->first();
                    if ($setor === null) {
                        $setor = Setor::query()->create([
                            'regional_id' => $regional->id,
                            'nome' => trim((string) $registro['setor_nome']),
                            'codigo' => $setorCodigo,
                            'status' => 'ativo',
                        ]);
                        $criados++;
                    } else {
                        $setor->fill([
                            'regional_id' => $regional->id,
                            'nome' => trim((string) $registro['setor_nome']),
                        ])->save();
                        $atualizados++;
                    }

                    $timezone = trim((string) ($registro['timezone'] ?? '')) ?: 'America/Sao_Paulo';

                    $igreja = Igreja::query()->where('codigo', $igrejaCodigo)->first();
                    if ($igreja === null) {
                        $igreja = Igreja::query()->create([
                            'codigo' => $igrejaCodigo,
                            'nome' => trim((string) $registro['igreja_nome']),
                            'timezone' => $timezone,
                            'status' => 'ativo',
                        ]);
                        $criados++;
                    } else {
                        $igreja->fill([
                            'nome' => trim((string) $registro['igreja_nome']),
                            'timezone' => $timezone,
                        ])->save();
                        $atualizados++;
                    }

                    // Garante o vinculo vigente da igreja com o setor da linha,
                    // sem duplicar quando ja for o vinculo atual.
                    $vigente = $igreja->vinculacaoVigente()->first();

                    if ($vigente === null) {
                        $igreja->vinculacoes()->create([
                            'setor_id' => $setor->id,
                            'inicio' => now()->toDateString(),
                            'fim' => null,
                        ]);
                    } elseif ($vigente->setor_id !== $setor->id) {
                        $vigente->fim = Carbon::now();
                        $vigente->save();
                        $igreja->vinculacoes()->create([
                            'setor_id' => $setor->id,
                            'inicio' => now()->toDateString(),
                            'fim' => null,
                        ]);
                    } else {
                        $ignorados++;
                    }
                }
            });
        } finally {
            fclose($handle);
        }

        return [
            'criados' => $criados,
            'atualizados' => $atualizados,
            'ignorados' => $ignorados,
        ];
    }
}
