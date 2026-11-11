<?php

use App\Domain\Auditoria\Models\EventoAuditoria;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();

    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
});

it('leitura identificavel sem finalidade e recusada', function () {
    $participante = participanteNaIgreja($this->igreja);

    $this->getJson("/api/v1/participants/{$participante->id}", $this->auth)->assertStatus(422);
    $this->getJson('/api/v1/participants?q=Ana', $this->auth)->assertStatus(422);
});

it('leitura identificavel com finalidade registra auditoria', function () {
    $participante = participanteNaIgreja($this->igreja);

    $this->getJson("/api/v1/participants/{$participante->id}?finalidade=confirmacao_vinculo", $this->auth)
        ->assertOk();

    $evento = EventoAuditoria::query()->where('acao', 'participante.lido')->first();

    expect($evento)->not->toBeNull();
    expect($evento->motivo)->toBe('confirmacao_vinculo');
});
