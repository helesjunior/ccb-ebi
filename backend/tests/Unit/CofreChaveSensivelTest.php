<?php

use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

it('monta um encrypter AES-256-GCM a partir da chave configurada', function () {
    // Chave válida injetada no teste para não depender do .env local (R2).
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);

    $enc = CofreChaveSensivel::encrypter();
    expect($enc)->toBeInstanceOf(Encrypter::class);

    $cifrado = $enc->encryptString('segredo infantil');
    expect($cifrado)->not->toContain('segredo infantil');
    expect($enc->decryptString($cifrado))->toBe('segredo infantil');
});

it('rejeita ciphertext adulterado (AEAD/GCM tamper-evident)', function () {
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);

    $enc = CofreChaveSensivel::encrypter();
    $cifrado = $enc->encryptString('segredo infantil');

    // Adultera um byte no meio do payload cifrado.
    $pos = intdiv(strlen($cifrado), 2);
    $adulterado = substr($cifrado, 0, $pos).chr(ord($cifrado[$pos]) ^ 0xFF).substr($cifrado, $pos + 1);

    expect(fn () => $enc->decryptString($adulterado))
        ->toThrow(DecryptException::class);
});

it('falha quando o prefixo base64: esta ausente', function () {
    config(['privacidade.chave' => base64_encode(random_bytes(32))]);
    expect(fn () => CofreChaveSensivel::encrypter())
        ->toThrow(RuntimeException::class);
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
