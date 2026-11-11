<?php

namespace App\Domain\DadosSensiveis\Support;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Chave de criptografia dos dados sensíveis, isolada da APP_KEY (ADR-0006).
 * Só este módulo lê a chave. Sem fallback: chave ausente/inválida é erro.
 */
final class CofreChaveSensivel
{
    public static function encrypter(): Encrypter
    {
        $chave = config('privacidade.chave');

        if (! is_string($chave) || $chave === '') {
            throw new RuntimeException('Chave de dados sensíveis ausente (SENSITIVE_DATA_KEY).');
        }

        if (! str_starts_with($chave, 'base64:')) {
            throw new RuntimeException('Chave de dados sensíveis inválida: prefixo "base64:" ausente.');
        }

        $binaria = base64_decode(substr($chave, 7), true);
        if ($binaria === false || strlen($binaria) !== 32) {
            throw new RuntimeException('Chave de dados sensíveis inválida: esperado base64 de 32 bytes.');
        }

        return new Encrypter($binaria, 'aes-256-gcm');
    }
}
