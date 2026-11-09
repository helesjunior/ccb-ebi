<?php

use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Database\UniqueConstraintViolationException;

it('cria a hierarquia com ULID e codigo unico de igreja', function () {
    $central = CentralNacional::create(['nome' => 'Central (Brás)']);
    $regional = Regional::create(['central_nacional_id' => $central->id, 'nome' => 'Brasília', 'codigo' => '01', 'status' => 'ativo']);
    $setor = Setor::create(['regional_id' => $regional->id, 'nome' => 'Setor A', 'codigo' => '0110', 'status' => 'ativo']);
    $igreja = Igreja::create(['codigo' => '240003', 'nome' => 'Núcleo Bandeirante', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo']);
    $ebi = Ebi::create(['igreja_id' => $igreja->id, 'nome' => 'EBI Núcleo Bandeirante', 'status' => 'ativo']);

    expect(strlen($igreja->id))->toBe(26)
        ->and($igreja->codigo)->toBe('240003')
        ->and($ebi->participantes_por_auxiliar)->toBe(4)
        ->and($igreja->vinculacoes()->count())->toBe(0);
});

it('impede codigo de igreja duplicado', function () {
    Igreja::create(['codigo' => '240003', 'nome' => 'A', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo']);
    expect(fn () => Igreja::create(['codigo' => '240003', 'nome' => 'B', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo']))
        ->toThrow(UniqueConstraintViolationException::class);
});
