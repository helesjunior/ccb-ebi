<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\DadosSensiveis\Contracts\ExpurgarRetencao;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;

beforeEach(function () {
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);
    config(['privacidade.retencao.ativa' => false]);
});

it('com retencao desligada nao apaga nada (relatorio)', function () {
    $d = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'conteudo_cifrado' => 'cifrado',
    ]);

    $r = app(ExpurgarRetencao::class)->executar();

    expect($r->executado)->toBeFalse();
    expect(DadoSensivelParticipante::query()->find($d->id)->conteudo_cifrado)->toBe('cifrado');
});

it('com retencao ligada expurga vencidos e audita', function () {
    $d = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'conteudo_cifrado' => 'cifrado',
        'referencia_segura' => null,
    ]);

    $r = app(ExpurgarRetencao::class)->executar(true);

    expect($r->executado)->toBeTrue();
    expect($r->expurgados)->toBe(1);

    $fresh = DadoSensivelParticipante::query()->find($d->id);
    expect($fresh->expurgado_em)->not->toBeNull();
    expect($fresh->conteudo_cifrado)->toBeNull();

    expect(EventoAuditoria::query()->where('acao', 'retencao.expurgar')->count())->toBe(1);
});

it('eh idempotente: rodar duas vezes nao reaudita o expurgado', function () {
    DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'conteudo_cifrado' => 'cifrado',
    ]);

    app(ExpurgarRetencao::class)->executar(true);
    app(ExpurgarRetencao::class)->executar(true);

    expect(EventoAuditoria::query()->where('acao', 'retencao.expurgar')->count())->toBe(1);
});
