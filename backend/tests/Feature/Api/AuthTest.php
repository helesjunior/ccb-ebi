<?php

use Database\Seeders\UsuarioSeeder;

beforeEach(function () {
    $this->seed(UsuarioSeeder::class);
});

it('faz login e devolve token', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'coordenadora@ebi.test', 'password' => 'senha'])
        ->assertStatus(201)
        ->assertJsonStructure(['token', 'usuario' => ['id', 'nome', 'email', 'email_verificado', 'status']]);
});

it('responde 401 em problem+json para credenciais invalidas', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'x@y.z', 'password' => 'x'])
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'unauthorized');
});

it('protege /me sem token', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('devolve o ator com token valido', function () {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'coordenadora@ebi.test', 'password' => 'senha'])
        ->json('token');
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])
        ->assertOk()
        ->assertJsonPath('email', 'coordenadora@ebi.test');
});
