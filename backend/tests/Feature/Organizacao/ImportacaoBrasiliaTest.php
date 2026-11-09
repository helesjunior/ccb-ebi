<?php

use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use App\Domain\Organizacao\Services\ImportadorBrasilia;

function csvTemp(string $conteudo): string
{
    $caminho = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($caminho, $conteudo);

    return $caminho;
}

$cabecalho = "regional_codigo,regional_nome,setor_codigo,setor_nome,igreja_codigo,igreja_nome,timezone\n";

it('importa duas vezes sem duplicar (idempotente por codigo)', function () use ($cabecalho) {
    $csv = csvTemp($cabecalho."01,Brasília,0110,Setor A,240003,Núcleo Bandeirante,America/Sao_Paulo\n");
    $importador = app(ImportadorBrasilia::class);

    $r1 = $importador->importar($csv);
    $r2 = $importador->importar($csv);

    expect($r1['criados'])->toBeGreaterThan(0)
        ->and($r2['criados'])->toBe(0)
        ->and(Igreja::where('codigo', '240003')->count())->toBe(1)
        ->and(Regional::where('codigo', '01')->count())->toBe(1)
        ->and(Setor::where('codigo', '0110')->count())->toBe(1);
});

it('atualiza dados existentes na segunda importacao', function () use ($cabecalho) {
    $importador = app(ImportadorBrasilia::class);
    $importador->importar(csvTemp($cabecalho."01,Brasília,0110,Setor A,240003,Núcleo Bandeirante,America/Sao_Paulo\n"));
    $r2 = $importador->importar(csvTemp($cabecalho."01,Brasília,0110,Setor A,240003,Núcleo Bandeirante Renomeada,America/Sao_Paulo\n"));

    expect($r2['atualizados'])->toBeGreaterThan(0)
        ->and(Igreja::where('codigo', '240003')->first()->nome)->toBe('Núcleo Bandeirante Renomeada');
});

it('falha em linha sem codigo de igreja', function () use ($cabecalho) {
    $csv = csvTemp($cabecalho."01,Brasília,0110,Setor A,,Sem Codigo,America/Sao_Paulo\n");
    expect(fn () => app(ImportadorBrasilia::class)->importar($csv))->toThrow(RuntimeException::class);
});
