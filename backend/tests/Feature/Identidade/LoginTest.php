<?php

use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Support\ProblemException;
use Database\Seeders\UsuarioSeeder;

beforeEach(function () {
    $this->seed(UsuarioSeeder::class);
});

it('autentica o usuario semeado', function () {
    $usuario = app(AutenticarAtor::class)->autenticar('coordenadora@ebi.test', 'senha');
    expect($usuario)->not->toBeNull();
});

it('rejeita credenciais invalidas sem criar sessao', function () {
    expect(fn () => app(AutenticarAtor::class)->autenticar('ninguem@ebi.test', 'x'))
        ->toThrow(ProblemException::class);
});
