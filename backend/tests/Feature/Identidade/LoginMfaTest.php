<?php

use App\Domain\Identidade\Models\Usuario;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

it('responsavel recebe token de sessao direto', function () {
    criarUsuarioComPapel('responsavel');

    $this->postJson('/api/v1/auth/login', [
        'email' => Usuario::query()->first()->email_normalizado,
        'password' => 'senha-bem-longa-123',
    ])
        ->assertStatus(201)
        ->assertJsonStructure(['token', 'usuario']);
});

it('equipe sem fator recebe desafio, nao sessao plena', function () {
    criarUsuarioComPapel('coordenadora');
    $u = Usuario::query()->first();

    $this->postJson('/api/v1/auth/login', [
        'email' => $u->email_normalizado,
        'password' => 'senha-bem-longa-123',
    ])
        ->assertStatus(200)
        ->assertJsonPath('mfa_required', true)
        ->assertJsonStructure(['token_desafio']);

    // O token de desafio tem escopo restrito: nao carrega a ability '*' de
    // sessao plena (a area operacional exige '*'; ver Task 11).
    $token = $u->tokens()->latest('id')->first();
    expect($token->can('mfa:pendente'))->toBeTrue()
        ->and($token->can('*'))->toBeFalse();
});

it('rejeita credenciais invalidas', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'x@y.z', 'password' => 'x'])
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthorized');
});
