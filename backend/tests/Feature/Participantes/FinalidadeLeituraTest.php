<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\Participante;

beforeEach(function () {
    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', (string) Str::ulid());
    $this->auth = ['Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken];
});

it('leitura identificavel sem finalidade e recusada', function () {
    $participante = Participante::factory()->create();

    $this->getJson("/api/v1/participants/{$participante->id}", $this->auth)->assertStatus(422);
    $this->getJson('/api/v1/participants?q=Ana', $this->auth)->assertStatus(422);
});

it('leitura identificavel com finalidade registra auditoria', function () {
    $participante = Participante::factory()->create();

    $this->getJson("/api/v1/participants/{$participante->id}?finalidade=confirmacao_vinculo", $this->auth)
        ->assertOk();

    $evento = EventoAuditoria::query()->where('acao', 'participante.lido')->first();

    expect($evento)->not->toBeNull();
    expect($evento->motivo)->toBe('confirmacao_vinculo');
});
