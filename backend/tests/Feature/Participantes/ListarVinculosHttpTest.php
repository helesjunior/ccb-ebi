<?php

use App\Domain\Participantes\Models\ResponsavelParticipante;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->authResp = ['Authorization' => 'Bearer '.$this->responsavel->createToken('t')->plainTextToken];
});

it('devolve os pedidos do territorio com nome e matricula do participante', function () {
    $p = participanteNaIgreja($this->igreja, ['nome' => 'Ana Clara']);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id, 'nome' => 'Mae da Ana', 'validacao' => 'pendente',
    ]);

    $this->getJson('/api/v1/guardian-requests?finalidade=triage', $this->auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $vinculo->id)
        ->assertJsonPath('data.0.participante.nome', 'Ana Clara')
        ->assertJsonPath('data.0.participante.matricula', $p->matricula);
});

it('exige finalidade declarada', function () {
    $this->getJson('/api/v1/guardian-requests', $this->auth)->assertStatus(422);
});

it('responsavel ve apenas os proprios pedidos', function () {
    $p = participanteNaIgreja($this->igreja);
    $meu = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id, 'usuario_id' => $this->responsavel->id,
    ]);
    ResponsavelParticipante::factory()->create(['participante_id' => $p->id]);

    $this->getJson('/api/v1/guardian-requests?validacao=todos&finalidade=acompanhar', $this->authResp)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $meu->id);
});

it('aceita o filtro de validacao', function () {
    $p = participanteNaIgreja($this->igreja);
    ResponsavelParticipante::factory()->create(['participante_id' => $p->id, 'validacao' => 'pendente']);
    $aprovado = ResponsavelParticipante::factory()->aprovado()->create(['participante_id' => $p->id]);

    $this->getJson('/api/v1/guardian-requests?validacao=aprovada&finalidade=triage', $this->auth)
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $aprovado->id);
});

it('rejeita validacao desconhecida', function () {
    $this->getJson('/api/v1/guardian-requests?validacao=xpto&finalidade=triage', $this->auth)
        ->assertStatus(422);
});
