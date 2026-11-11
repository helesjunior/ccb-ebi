<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();

    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
});

it('detecta possivel duplicidade por nome normalizado e data', function () {
    participanteNaIgreja($this->igreja, ['nome' => 'José  da Silva', 'data_nascimento' => '2015-03-10']);

    $this->getJson('/api/v1/participants?q='.urlencode('jose da silva').'&finalidade=deduplicacao', $this->auth)
        ->assertOk()
        ->assertJsonPath('data.0.nome', 'José  da Silva');
});

it('mescla e desfaz preservando o estado anterior', function () {
    $principal = Participante::factory()->create(['nome' => 'Ana', 'data_nascimento' => '2015-03-10', 'nome_social' => null]);
    $duplicado = Participante::factory()->create(['nome' => 'Ana Maria', 'data_nascimento' => '2015-03-10', 'nome_social' => 'Aninha']);

    $this->postJson("/api/v1/participants/{$principal->id}/merge", [
        'duplicado_id' => $duplicado->id,
    ], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('id', $principal->id);

    $principal->refresh();
    $duplicado->refresh();

    // O nome social ausente no principal e herdado do duplicado.
    expect($principal->nome_social)->toBe('Aninha');
    // O duplicado e encerrado/arquivado.
    expect($duplicado->status)->toBe('mesclado');
    expect(EventoAuditoria::query()->where('acao', 'participante.mesclado')->exists())->toBeTrue();

    // Desfaz: restaura o estado anterior.
    $this->postJson("/api/v1/participants/{$principal->id}/merge/undo", [], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk();

    expect($principal->fresh()->nome_social)->toBeNull();
    expect($duplicado->fresh()->status)->toBe('ativo');
});

it('nao mescla consigo mesmo', function () {
    $p = Participante::factory()->create();

    $this->postJson("/api/v1/participants/{$p->id}/merge", [
        'duplicado_id' => $p->id,
    ], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});

it('desfazer a mesclagem devolve os vinculos movidos ao duplicado (H4)', function () {
    $principal = Participante::factory()->create(['data_nascimento' => '2015-03-10']);
    $duplicado = Participante::factory()->create(['data_nascimento' => '2015-03-10']);

    $vinculo = ResponsavelParticipante::factory()->aprovado()->create([
        'participante_id' => $duplicado->id,
    ]);

    $this->postJson("/api/v1/participants/{$principal->id}/merge", ['duplicado_id' => $duplicado->id], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    // Durante a mesclagem, o vinculo migra para o principal.
    expect($vinculo->fresh()->participante_id)->toBe($principal->id);

    $this->postJson("/api/v1/participants/{$principal->id}/merge/undo", [], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    // Ao desfazer, o vinculo volta ao duplicado.
    expect($vinculo->fresh()->participante_id)->toBe($duplicado->id);
});

it('grava auditoria da mesclagem e da reversao', function () {
    $principal = Participante::factory()->create(['data_nascimento' => '2015-03-10']);
    $duplicado = Participante::factory()->create(['data_nascimento' => '2015-03-10']);

    $this->postJson("/api/v1/participants/{$principal->id}/merge", ['duplicado_id' => $duplicado->id], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->postJson("/api/v1/participants/{$principal->id}/merge/undo", [], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    expect(EventoAuditoria::query()->where('acao', 'participante.mesclagem_desfeita')->exists())->toBeTrue();
});
