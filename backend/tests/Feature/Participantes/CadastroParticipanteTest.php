<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\Participante;

beforeEach(function () {
    $this->usuario = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->auth = ['Authorization' => 'Bearer '.$this->usuario->createToken('t')->plainTextToken];
});

it('responsavel cadastra participante e recebe matricula', function () {
    $resposta = $this->postJson('/api/v1/participants', [
        'nome' => 'Ana Souza',
        'data_nascimento' => '2016-05-10',
    ], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonStructure(['id', 'matricula', 'nome', 'origem_cadastro'])
        ->assertJsonPath('nome', 'Ana Souza')
        ->assertJsonPath('origem_cadastro', 'responsavel')
        ->json();

    expect($resposta['matricula'])->toMatch('/^[0-9A-HJKMNP-TV-Z]{9}$/');
    expect(Participante::query()->where('id', $resposta['id'])->exists())->toBeTrue();
});

it('registra o aceite opcional da igreja de referencia', function () {
    $resposta = $this->postJson('/api/v1/participants', [
        'nome' => 'Bia Lima',
        'data_nascimento' => '2015-01-20',
        'igreja_referencia_id' => (string) Str::ulid(),
    ], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->json();

    expect($resposta['igreja_referencia_id'])->not->toBeNull();
});

it('grava auditoria do cadastro', function () {
    $this->postJson('/api/v1/participants', [
        'nome' => 'Caio Rocha',
        'data_nascimento' => '2014-03-03',
    ], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    expect(EventoAuditoria::query()->where('acao', 'participante.cadastrado')->exists())->toBeTrue();
});

it('rejeita data de nascimento futura', function () {
    $this->postJson('/api/v1/participants', [
        'nome' => 'Duda',
        'data_nascimento' => now()->addDay()->toDateString(),
    ], $this->auth + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('aceita escrita sem idempotency key (recurso opcional)', function () {
    $this->postJson('/api/v1/participants', [
        'nome' => 'Eva',
        'data_nascimento' => '2016-05-10',
    ], $this->auth)->assertCreated();
});

it('replay com a mesma Idempotency-Key nao duplica', function () {
    $chave = (string) Str::uuid();
    $corpo = ['nome' => 'Fabi', 'data_nascimento' => '2016-05-10'];

    $a = $this->postJson('/api/v1/participants', $corpo, $this->auth + ['Idempotency-Key' => $chave])->assertCreated()->json();
    $b = $this->postJson('/api/v1/participants', $corpo, $this->auth + ['Idempotency-Key' => $chave])->assertCreated()->json();

    expect($b['id'])->toBe($a['id']);
    expect(Participante::query()->where('nome', 'Fabi')->count())->toBe(1);
});
