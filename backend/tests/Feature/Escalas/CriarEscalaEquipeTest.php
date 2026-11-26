<?php

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use Database\Seeders\PapelSeeder;

/**
 * Criar escala com `equipe_id` (Tarefa 2). Nesta tarefa basta a presenca: a
 * regra de dona e a montagem pela equipe chegam depois.
 */
beforeEach(fn () => $this->seed(PapelSeeder::class));

it('criar escala com equipe do EBI grava equipe_id', function () {
    $c = cenarioEscala();
    $equipe = $c['equipe'];

    $resposta = $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'equipe_id' => $equipe->id,
        'estimativa_participantes' => 8,
        'membros' => [
            ['usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA],
        ],
    ], authEscala($c['coord']));

    $resposta->assertStatus(201)
        ->assertJsonPath('escala.equipe_id', $equipe->id);

    $escala = Escala::query()->where('sessao_id', $c['sessao']->id)->firstOrFail();

    expect((string) $escala->equipe_id)->toBe((string) $equipe->id);
});
