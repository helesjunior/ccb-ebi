<?php

use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use Illuminate\Encryption\Encrypter;

it('monta um encrypter AES-256-GCM a partir da chave configurada', function () {
    // Chave válida injetada no teste para não depender do .env local (R2).
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);

    $enc = CofreChaveSensivel::encrypter();
    expect($enc)->toBeInstanceOf(Encrypter::class);

    // Encrypter nao expoe getCipher() nesta versao; o cipher e privado.
    $cipher = (new ReflectionProperty(Encrypter::class, 'cipher'))->getValue($enc);
    expect($cipher)->toBe('aes-256-gcm');

    $cifrado = $enc->encryptString('segredo infantil');
    expect($cifrado)->not->toContain('segredo infantil');
    expect($enc->decryptString($cifrado))->toBe('segredo infantil');
});

it('falha explicitamente quando a chave nao esta configurada', function () {
    config(['privacidade.chave' => null]);
    expect(fn () => CofreChaveSensivel::encrypter())
        ->toThrow(RuntimeException::class);
});

it('nao usa a APP_KEY como fallback', function () {
    config(['privacidade.chave' => null]);
    // app.key existe (APP_KEY), mas o cofre nao pode cair nela.
    expect(config('app.key'))->not->toBeNull();
    expect(fn () => CofreChaveSensivel::encrypter())->toThrow(RuntimeException::class);
});
