<?php

use App\Domain\Identidade\Models\Ator;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Setor;

beforeEach(function () {
    $this->ator = Ator::factory()->create(['papel' => 'coordenadora']);
    $this->token = $this->ator->createToken('t')->plainTextToken;
    $this->auth = ['Authorization' => "Bearer {$this->token}"];
});

it('cria igreja autenticado e devolve 201', function () {
    $this->postJson('/api/v1/churches', [
        'codigo' => '240003', 'nome' => 'Núcleo Bandeirante', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo',
    ], $this->auth)
        ->assertStatus(201)
        ->assertJsonPath('codigo', '240003')
        ->assertJsonPath('setor', null);
});

it('rejeita escrita sem token', function () {
    $this->postJson('/api/v1/churches', ['codigo' => '1', 'nome' => 'x', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo'])
        ->assertStatus(401);
});

it('rejeita escrita com papel sem permissao', function () {
    $ator = Ator::factory()->create(['papel' => 'responsavel']);
    $token = $ator->createToken('t')->plainTextToken;
    $this->postJson('/api/v1/churches', ['codigo' => '1', 'nome' => 'x', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo'], ['Authorization' => "Bearer $token"])
        ->assertStatus(403);
});

it('lista igrejas paginado', function () {
    Igreja::factory()->count(3)->create();
    $this->getJson('/api/v1/churches', $this->auth)
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'codigo', 'nome', 'timezone', 'status', 'setor']], 'meta' => ['proximo_cursor']]);
});

it('transfere igreja e devolve o setor novo na leitura', function () {
    $igreja = Igreja::factory()->create();
    $setor = Setor::factory()->create();
    $this->postJson("/api/v1/churches/{$igreja->id}/transfers", ['sector_id' => $setor->id, 'date' => now()->toDateString()], $this->auth)
        ->assertStatus(201);
    $this->getJson("/api/v1/churches/{$igreja->id}", $this->auth)
        ->assertOk()->assertJsonPath('setor.id', $setor->id);
});

it('atualiza igreja', function () {
    $igreja = Igreja::factory()->create();
    $this->patchJson("/api/v1/churches/{$igreja->id}", ['nome' => 'Novo Nome'], $this->auth)
        ->assertOk()
        ->assertJsonPath('nome', 'Novo Nome');
});

it('replay de POST /churches com a mesma Idempotency-Key devolve 201 e nao duplica', function () {
    $payload = ['codigo' => '240003', 'nome' => 'Núcleo Bandeirante', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo'];
    $headers = $this->auth + ['Idempotency-Key' => 'teste-replay-'.uniqid()];

    $this->postJson('/api/v1/churches', $payload, $headers)->assertStatus(201);
    $this->postJson('/api/v1/churches', $payload, $headers)->assertStatus(201);

    expect(Igreja::where('codigo', '240003')->count())->toBe(1);
});
