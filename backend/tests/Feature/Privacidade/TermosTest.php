<?php

use App\Domain\Privacidade\Contracts\GerirTermos;
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

it('encerra a versao anterior ao publicar e devolve a nova como vigente', function () {
    $gerir = app(GerirTermos::class);

    $anterior = $gerir->publicar('cadastro', 'consentimento', '2026-10', 'texto v1', now()->subDay());
    $nova = $gerir->publicar('cadastro', 'consentimento', '2026-11', 'texto v2', now());

    expect($gerir->vigente('cadastro')?->id)->toBe($nova->id)
        ->and($anterior->fresh()->encerrado_em)->not->toBeNull()
        ->and(TermoVersao::query()->where('finalidade', 'cadastro')->whereNull('encerrado_em')->count())->toBe(1);
});
