<?php

use App\Domain\DadosSensiveis\Contracts\ArmazenarDadoSensivel;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use App\Support\ProblemException;

beforeEach(function () {
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);
});

it('cifra o conteudo em repouso (texto em claro nao aparece na coluna)', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'CID F90.0', null, 'excecao_etaria', 'consentimento', 'coordenador', null,
    );

    expect($d->conteudo_cifrado)->not->toContain('CID F90.0');
    expect(CofreChaveSensivel::encrypter()->decryptString($d->conteudo_cifrado))->toBe('CID F90.0');
});

it('aceita referencia segura sem cifrar conteudo', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', '', 's3://laudos/abc.pdf', 'excecao_etaria', 'consentimento', 'coordenador', null,
    );

    expect($d->conteudo_cifrado)->toBeNull();
    expect($d->referencia_segura)->toBe('s3://laudos/abc.pdf');
});

it('rejeita conteudo e referencia segura juntos', function () {
    expect(fn () => app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'texto', 's3://x', 'f', 'b', 'c', null,
    ))->toThrow(ProblemException::class);
});

it('rejeita conteudo e referencia segura ambos vazios', function () {
    expect(fn () => app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', '', null, 'f', 'b', 'c', null,
    ))->toThrow(ProblemException::class);
});

it('nao expoe o conteudo cifrado ao serializar o modelo', function () {
    $d = DadoSensivelParticipante::factory()->create();
    expect($d->toArray())->not->toHaveKey('conteudo_cifrado');
});
