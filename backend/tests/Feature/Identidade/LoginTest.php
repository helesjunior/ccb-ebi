<?php

use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Support\ProblemException;
use Database\Seeders\AtorSeeder;

beforeEach(function () {
    $this->seed(AtorSeeder::class);
});

it('autentica o ator semeado', function () {
    $ator = app(AutenticarAtor::class)->autenticar('coordenadora@ebi.test', 'senha');
    expect($ator)->not->toBeNull();
});

it('rejeita credenciais invalidas sem criar sessao', function () {
    expect(fn () => app(AutenticarAtor::class)->autenticar('ninguem@ebi.test', 'x'))
        ->toThrow(ProblemException::class);
});
