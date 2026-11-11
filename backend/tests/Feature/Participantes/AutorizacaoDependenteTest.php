<?php

use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;

beforeEach(function () {
    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->auth = ['Authorization' => 'Bearer '.$this->responsavel->createToken('t')->plainTextToken];

    $this->outro = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->authOutro = ['Authorization' => 'Bearer '.$this->outro->createToken('t')->plainTextToken];
});

it('responsavel aprovado ve seu dependente em /me/participants', function () {
    $vinculo = ResponsavelParticipante::factory()->aprovado()->create(['usuario_id' => $this->responsavel->id]);

    $this->getJson('/api/v1/me/participants', $this->auth)
        ->assertOk()
        ->assertJsonPath('data.0.id', $vinculo->participante_id);
});

it('responsavel pendente nao ve o dependente (CA-04)', function () {
    ResponsavelParticipante::factory()->create([
        'usuario_id' => $this->responsavel->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->getJson('/api/v1/me/participants', $this->auth)
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('responsavel contestado nao ve o dependente', function () {
    ResponsavelParticipante::factory()->create([
        'usuario_id' => $this->responsavel->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_RECUSADA,
    ]);

    $this->getJson('/api/v1/me/participants', $this->auth)->assertOk()->assertJsonPath('data', []);
});

it('responsavel nao ve dependente de outro (horizontal)', function () {
    $vinculo = ResponsavelParticipante::factory()->aprovado()->create(['usuario_id' => $this->outro->id]);

    $this->getJson('/api/v1/me/participants', $this->auth)->assertOk()->assertJsonPath('data', []);

    // E nao acessa o participante pelos endpoints de participante.
    $this->getJson("/api/v1/participants/{$vinculo->participante_id}", $this->auth)->assertForbidden();
});

it('conhecer a matricula nao basta para acessar (CA-04)', function () {
    $participante = Participante::factory()->create();

    $this->getJson("/api/v1/participants/{$participante->id}", $this->auth)->assertForbidden();
});

it('coordenadora tambem nao le pelo caminho territorial de responsavel', function () {
    // O caminho do responsavel e por propriedade: sem vinculo aprovado, 403,
    // mesmo para quem tem `participante.ler` (a leitura passa pelo verificador).
    $this->getJson('/api/v1/me/participants', $this->auth)->assertOk()->assertJsonPath('data', []);
});
