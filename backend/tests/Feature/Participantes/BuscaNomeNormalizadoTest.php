<?php

use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();

    $usuario = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-18 13:00:00',
        'fim_previsto' => '2026-11-18 14:30:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
});

it('encontra nome acentuado buscando sem acento e sem caixa', function () {
    $participante = participanteNaIgreja($this->igreja, ['nome' => 'Ágata Lima']);
    $url = "/api/v1/sessions/{$this->sessao->id}/participants?finalidade=registro_presenca";

    $this->getJson($url.'&busca=agata', $this->auth)
        ->assertOk()
        ->assertJsonFragment(['id' => $participante->id]);

    $this->getJson($url.'&busca=ÁGATA', $this->auth)
        ->assertOk()
        ->assertJsonFragment(['id' => $participante->id]);
});

it('nao expoe a coluna derivada de busca', function () {
    $participante = participanteNaIgreja($this->igreja, ['nome' => 'Ágata Lima']);

    $resposta = $this->getJson("/api/v1/sessions/{$this->sessao->id}/participants?finalidade=registro_presenca&busca=agata", $this->auth)
        ->assertOk();

    expect($participante->toArray())->not->toHaveKey('nome_busca')
        ->and($resposta->getContent())->not->toContain('nome_busca');
});
