<?php

use App\Domain\Participantes\Services\GeradorMatricula;

it('gera matricula de 9 chars no alfabeto Crockford', function () {
    $m = (new GeradorMatricula)->gerar();

    // Crockford base32: 0-9 e A-Z sem I, L, O, U.
    expect($m)->toMatch('/^[0-9A-HJKMNP-TV-Z]{9}$/');
});

it('gera matriculas distintas em sequencia', function () {
    $g = new GeradorMatricula;

    $m = collect(range(1, 200))->map(fn () => $g->gerar());

    expect($m->unique()->count())->toBe(200);
});

it('o digito verificador valida e uma alteracao invalida', function () {
    $g = new GeradorMatricula;
    $base = 'ABCD2345';
    $completa = $g->comDigito($base);

    expect($g->valida($completa))->toBeTrue();
    expect($g->valida('Z'.$base))->toBeFalse();
    expect($g->valida($base))->toBeFalse(); // 8 chars, sem DV
});

it('a matricula nao deriva de entrada (nao e previsivel)', function () {
    $g = new GeradorMatricula;

    $primeira = $g->gerar();
    $segunda = $g->gerar();

    expect($primeira)->not->toBe($segunda);
});
