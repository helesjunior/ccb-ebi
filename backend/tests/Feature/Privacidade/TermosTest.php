<?php

use App\Domain\Privacidade\Models\TermoVersao;

it('registra uma versao de termo vigente por finalidade', function () {
    $termo = TermoVersao::factory()->create([
        'finalidade' => 'cadastro',
        'versao' => '2026-11',
        'vigente_em' => now()->subDay(),
    ]);

    expect($termo->id)->not->toBeNull();
    expect(TermoVersao::query()->vigente('cadastro')->value('id'))->toBe($termo->id);
});

it('nao retorna termo vigente quando a vigencia e futura ou encerrada', function () {
    TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->addDay()]);
    // Versao distinta: (finalidade, versao) e unico na tabela.
    TermoVersao::factory()->encerrado()->create(['finalidade' => 'cadastro', 'versao' => '2026-10']);

    expect(TermoVersao::query()->vigente('cadastro')->first())->toBeNull();
});
