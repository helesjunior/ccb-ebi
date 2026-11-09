<?php

namespace App\Domain\Identidade\Support;

/**
 * TOTP (RFC 6238) sobre HOTP (RFC 4226): HMAC-SHA1, 6 digitos, passo de 30s.
 * Implementacao sem dependencia externa, a partir de um segredo em base32.
 */
final class Totp
{
    private const DIGITOS = 6;

    private const PASSO = 30;

    public static function codigoAtual(string $segredoBase32, ?int $timestamp = null): string
    {
        $contador = intdiv($timestamp ?? time(), self::PASSO);

        return self::hotp(self::decodificarBase32($segredoBase32), $contador);
    }

    public static function validar(string $segredoBase32, string $codigo, ?int $timestamp = null, int $janela = 1): bool
    {
        $agora = $timestamp ?? time();

        for ($deslocamento = -$janela; $deslocamento <= $janela; $deslocamento++) {
            $esperado = self::codigoAtual($segredoBase32, $agora + ($deslocamento * self::PASSO));

            if (hash_equals($esperado, $codigo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gera um segredo aleatorio codificado em base32 (sem padding), adequado
     * para `otpauth://` (20 bytes = 160 bits, recomendacao RFC 4226).
     *
     * @param  int<1, max>  $bytes
     */
    public static function gerarSegredo(int $bytes = 20): string
    {
        return self::codificarBase32(random_bytes($bytes));
    }

    private static function hotp(string $chave, int $contador): string
    {
        $binario = pack('N*', 0).pack('N*', $contador);
        $hash = hash_hmac('sha1', $binario, $chave, true);

        $deslocamento = ord($hash[19]) & 0x0F;
        $codigo = (
            ((ord($hash[$deslocamento]) & 0x7F) << 24)
            | ((ord($hash[$deslocamento + 1]) & 0xFF) << 16)
            | ((ord($hash[$deslocamento + 2]) & 0xFF) << 8)
            | (ord($hash[$deslocamento + 3]) & 0xFF)
        ) % (10 ** self::DIGITOS);

        return str_pad((string) $codigo, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    private static function decodificarBase32(string $base32): string
    {
        $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper(rtrim($base32, '='));

        $bits = '';
        foreach (str_split($base32) as $caractere) {
            $valor = strpos($alfabeto, $caractere);

            if ($valor === false) {
                continue;
            }

            $bits .= str_pad(decbin($valor), 5, '0', STR_PAD_LEFT);
        }

        $resultado = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $resultado .= chr((int) bindec($byte));
            }
        }

        return $resultado;
    }

    private static function codificarBase32(string $binario): string
    {
        $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        $bits = '';
        foreach (str_split($binario) as $caractere) {
            $bits .= str_pad(decbin(ord($caractere)), 8, '0', STR_PAD_LEFT);
        }

        $resultado = '';
        foreach (str_split($bits, 5) as $grupo) {
            $grupo = str_pad($grupo, 5, '0', STR_PAD_RIGHT);
            $resultado .= $alfabeto[(int) bindec($grupo)];
        }

        return $resultado;
    }
}
