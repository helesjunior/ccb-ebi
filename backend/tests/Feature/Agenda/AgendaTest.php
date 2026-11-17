<?php

use App\Domain\Agenda\Models\AgendaEbi;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
});

it('cria faixa e recusa sobreposicao no mesmo dia', function () {
    $dados = [
        'dia_semana' => 1,
        'hora_inicio' => '10:00',
        'hora_fim' => '11:30',
        'inicio_vigencia' => '2026-11-02',
    ];

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda", $dados, $this->auth)
        ->assertCreated()
        ->assertJsonPath('dia_semana', 1);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda", [...$dados, 'hora_inicio' => '11:00'], $this->auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'agenda_sobreposta');

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda", [...$dados, 'dia_semana' => 2], $this->auth)
        ->assertCreated();
});

it('edita faixa futura e recusa faixa vigente', function () {
    $futura = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'inicio_vigencia' => '2027-01-04',
        'fim_vigencia' => null,
    ]);

    $this->patchJson("/api/v1/agenda/{$futura->id}", ['hora_fim' => '12:00'], $this->auth)
        ->assertOk()
        ->assertJsonPath('hora_fim', '12:00');

    $vigente = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'dia_semana' => 3,
        'inicio_vigencia' => now()->subWeek()->toDateString(),
    ]);

    $this->patchJson("/api/v1/agenda/{$vigente->id}", ['hora_fim' => '12:00'], $this->auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'agenda_vigente');
});

it('delete encerra a vigencia e nao apaga a faixa', function () {
    $faixa = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'inicio_vigencia' => now()->subMonth()->toDateString(),
    ]);

    $this->deleteJson("/api/v1/agenda/{$faixa->id}", [], $this->auth)->assertNoContent();

    $faixa->refresh();
    expect($faixa->fim_vigencia?->toDateString())->toBe(now()->subDay()->toDateString());
});

it('auxiliar nao altera a agenda', function () {
    $aux = criarUsuarioComPapel('auxiliar', 'ebi', $this->ebi->id);
    $auth = [
        'Authorization' => 'Bearer '.$aux->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=auxiliar;escopo=ebi:{$this->ebi->id}",
    ];

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda", [
        'dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fim' => '11:30', 'inicio_vigencia' => '2026-11-02',
    ], $auth)->assertForbidden();
});
