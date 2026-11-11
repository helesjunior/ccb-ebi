<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Contracts\GerirMesclagem;
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
    $principal = participanteNaIgreja($this->igreja, ['nome' => 'Ana', 'data_nascimento' => '2015-03-10', 'nome_social' => null]);
    $duplicado = participanteNaIgreja($this->igreja, ['nome' => 'Ana Maria', 'data_nascimento' => '2015-03-10', 'nome_social' => 'Aninha']);

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
    $p = participanteNaIgreja($this->igreja);

    $this->postJson("/api/v1/participants/{$p->id}/merge", [
        'duplicado_id' => $p->id,
    ], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});

it('desfazer a mesclagem devolve os vinculos movidos ao duplicado (H4)', function () {
    $principal = participanteNaIgreja($this->igreja, ['data_nascimento' => '2015-03-10']);
    $duplicado = participanteNaIgreja($this->igreja, ['data_nascimento' => '2015-03-10']);

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

it('nao mescla cadastros fora do escopo territorial (M6)', function () {
    // Ambos os cadastros estao em outra igreja, fora da subarvore da
    // coordenadora (design §6.7: a igreja de referencia gateia a mesclagem).
    ['igreja' => $outra] = igrejaComEbi();

    $principal = participanteNaIgreja($outra, ['data_nascimento' => '2015-03-10']);
    $duplicado = participanteNaIgreja($outra, ['data_nascimento' => '2015-03-10']);

    $this->postJson("/api/v1/participants/{$principal->id}/merge", [
        'duplicado_id' => $duplicado->id,
    ], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();

    expect($duplicado->fresh()->status)->toBe('ativo');
});

it('desfazer mesclagem fora do escopo e negado (M6)', function () {
    ['igreja' => $outra] = igrejaComEbi();

    $principal = participanteNaIgreja($outra, ['data_nascimento' => '2015-03-10']);
    $duplicado = participanteNaIgreja($outra, ['data_nascimento' => '2015-03-10']);

    // Mesclagem direta no servico (sem passar pelo gate HTTP) para montar o
    // estado e testar so a negacao do desfazer.
    app(GerirMesclagem::class)
        ->mesclar($this->coordenadora->id, $principal->id, $duplicado->id);

    $this->postJson("/api/v1/participants/{$principal->id}/merge/undo", [], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('grava auditoria da mesclagem e da reversao', function () {
    $principal = participanteNaIgreja($this->igreja, ['data_nascimento' => '2015-03-10']);
    $duplicado = participanteNaIgreja($this->igreja, ['data_nascimento' => '2015-03-10']);

    $this->postJson("/api/v1/participants/{$principal->id}/merge", ['duplicado_id' => $duplicado->id], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->postJson("/api/v1/participants/{$principal->id}/merge/undo", [], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    expect(EventoAuditoria::query()->where('acao', 'participante.mesclagem_desfeita')->exists())->toBeTrue();
});
