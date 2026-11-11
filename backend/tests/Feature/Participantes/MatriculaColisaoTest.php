<?php

use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Services\GeradorMatricula;
use Illuminate\Database\QueryException;

it('faz retry quando a matricula gerada colide (CA-25)', function () {
    $existente = Participante::factory()->create(['matricula' => 'ABCD2345X']);

    // Primeira chamada repete a existente (colisao); as seguintes geram nova.
    $this->mock(GeradorMatricula::class, function ($mock) {
        $mock->shouldReceive('gerar')
            ->andReturn('ABCD2345X', 'EFGH6789Y');
    });

    $novo = Participante::criarComMatricula([
        'nome' => 'Novo Participante',
        'data_nascimento' => '2016-01-01',
        'origem_cadastro' => 'responsavel',
    ]);

    expect($novo->matricula)->toBe('EFGH6789Y');
    expect($novo->id)->not->toBe($existente->id);
});

it('propaga erro que nao seja de matricula', function () {
    expect(fn () => Participante::criarComMatricula([
        'nome' => 'Sem Nascimento',
        'origem_cadastro' => 'responsavel',
    ]))->toThrow(QueryException::class);
});
