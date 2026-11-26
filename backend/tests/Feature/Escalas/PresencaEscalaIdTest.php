<?php

use Database\Seeders\PapelSeeder;

/**
 * A tela de presenca busca a escala pelo `escala_id` da sessao (E07, RF-07):
 * antes ela passava o id da sessao e recebia 404. Os helpers dos outros testes
 * de Escalas dependem dos papeis semeados no mesmo processo; aqui o arquivo se
 * basta (Pest pode rodar os arquivos em processos separados).
 */
beforeEach(fn () => $this->seed(PapelSeeder::class));

it('GET /sessions/{id} traz escala_id', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);

    expect($escala->id)->not->toBe($c['sessao']->id);

    $this->getJson("/api/v1/sessions/{$c['sessao']->id}", authEscala($c['coord']))
        ->assertOk()
        ->assertJsonPath('escala_id', $escala->id)
        ->assertJsonPath('id', $c['sessao']->id);
});

it('sessao sem escala traz escala_id null', function () {
    $c = cenarioEscala();

    $this->getJson("/api/v1/sessions/{$c['sessao']->id}", authEscala($c['coord']))
        ->assertOk()
        ->assertJsonPath('escala_id', null);
});
