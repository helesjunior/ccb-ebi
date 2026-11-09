<?php

use App\Domain\Organizacao\Models\CentralNacional;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->central = CentralNacional::factory()->create();
});

it('devolve o usuario e seus escopos de atuacao', function () {
    $u = criarUsuarioComPapel('coordenadora', 'central', $this->central->id);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];

    $this->getJson('/api/v1/me', $auth)
        ->assertOk()->assertJsonStructure(['id', 'nome', 'email', 'papeis'])
        ->assertJsonPath('papeis.0', 'coordenadora');

    $this->getJson('/api/v1/me/scopes', $auth)
        ->assertOk()->assertJsonPath('data.0.tipo_escopo', 'central')
        ->assertJsonPath('data.0.papel', 'coordenadora')
        ->assertJsonPath('data.0.escopo_id', $this->central->id);
});
