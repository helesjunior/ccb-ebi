<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;

beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->token = $this->usuario->createToken('t')->plainTextToken;
    $this->auth = ['Authorization' => "Bearer {$this->token}"];
});

it('cria regional e lista seus setores', function () {
    $regional = $this->postJson('/api/v1/regions', ['nome' => 'Brasília', 'codigo' => '01', 'status' => 'ativo'], $this->auth)
        ->assertStatus(201)->json();

    $this->getJson("/api/v1/regions/{$regional['id']}/sectors", $this->auth)
        ->assertOk()->assertJsonStructure(['data', 'meta' => ['proximo_cursor']]);
});

it('cria setor vinculado a regional', function () {
    $regional = Regional::factory()->create();
    $this->postJson('/api/v1/sectors', ['regional_id' => $regional->id, 'nome' => 'Setor A', 'codigo' => '0110', 'status' => 'ativo'], $this->auth)
        ->assertStatus(201)
        ->assertJsonPath('regional_id', $regional->id);
});

it('cria ebi vinculado a igreja', function () {
    $igreja = Igreja::factory()->create();
    $this->postJson('/api/v1/ebis', ['igreja_id' => $igreja->id, 'nome' => 'EBI', 'status' => 'ativo'], $this->auth)
        ->assertStatus(201)->assertJsonPath('igreja_id', $igreja->id);
});

it('lista ebis paginado', function () {
    $this->postJson('/api/v1/ebis', ['igreja_id' => Igreja::factory()->create()->id, 'nome' => 'EBI', 'status' => 'ativo'], $this->auth)->assertStatus(201);
    $this->getJson('/api/v1/ebis', $this->auth)->assertOk()->assertJsonStructure(['data', 'meta' => ['proximo_cursor']]);
});

it('expoe a central nacional', function () {
    $this->getJson('/api/v1/central', $this->auth)->assertOk();
});

// A verificacao de autorizacao por operacao (negar escrita a papel sem
// permissao) e restaurada na task da matriz; aqui a identidade ainda nao tem
// papeis vinculados. Ver tests/Feature/Identidade/AutorizacaoOperacaoTest.php.
