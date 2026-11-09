<?php

use App\Support\PaginacaoCursor;
use Illuminate\Http\Request;

it('usa o limit padrao quando ausente', function () {
    $r = Request::create('/x', 'GET');
    expect(PaginacaoCursor::limit($r))->toBe(PaginacaoCursor::LIMITE_PADRAO);
});

it('limita o limit a 100', function () {
    $r = Request::create('/x', 'GET', ['limit' => '5000']);
    expect(PaginacaoCursor::limit($r))->toBe(100);
});

it('respeita o limit dentro da faixa', function () {
    $r = Request::create('/x', 'GET', ['limit' => '30']);
    expect(PaginacaoCursor::limit($r))->toBe(30);
});

it('rejeita limit invalido e usa o padrao', function () {
    $r = Request::create('/x', 'GET', ['limit' => 'abc']);
    expect(PaginacaoCursor::limit($r))->toBe(PaginacaoCursor::LIMITE_PADRAO);
});
