<?php

namespace App\Domain\Participantes\Services;

/**
 * Gera a matricula nacional do participante (RF-02.1, CA-25).
 *
 * 8 caracteres aleatorios em base32 Crockford (40 bits) + 1 digito
 * verificador, no total 9. Nunca derivada de sequencia, data ou dado pessoal;
 * a unicidade e garantida pelo indice `unique` da tabela (a gravacao faz
 * retry em colisao, no model).
 *
 * Crockford exclui I, L, O e U para evitar ambiguidade visual/fonetica.
 */
class GeradorMatricula
{
    public const ALFABETO = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const BASE = 32;

    private const COMPRIMENTO_BASE = 8;

    /** Gera uma matricula completa (8 simbolos + digito verificador). */
    public function gerar(): string
    {
        $base = '';

        for ($i = 0; $i < self::COMPRIMENTO_BASE; $i++) {
            $base .= self::ALFABETO[random_int(0, self::BASE - 1)];
        }

        return $this->comDigito($base);
    }

    /** Anexa o digito verificador a uma base de 8 simbolos Crockford. */
    public function comDigito(string $base): string
    {
        return $base.$this->digito($base);
    }

    /** Verifica se a matricula tem 9 simbolos Crockford e o DV confere. */
    public function valida(string $matricula): bool
    {
        if (strlen($matricula) !== self::COMPRIMENTO_BASE + 1) {
            return false;
        }

        $base = substr($matricula, 0, self::COMPRIMENTO_BASE);

        if (! preg_match('/^['.preg_quote(self::ALFABETO, '/').']{'.self::COMPRIMENTO_BASE.'}$/', $base)) {
            return false;
        }

        return $matricula === $this->comDigito($base);
    }

    /**
     * Digito verificador: o valor base-32 da matricula modulo 31 (primo, menor
     * que o tamanho do alfabeto, entao o resultado e sempre um indice valido).
     * Deterministico e simetrico com `valida()`.
     */
    private function digito(string $base): string
    {
        $valor = 0;

        for ($i = 0, $len = strlen($base); $i < $len; $i++) {
            $indice = strpos(self::ALFABETO, $base[$i]);

            if ($indice === false) {
                // Simbolo fora do alfabeto: digito insensivel (valida() recusa
                // pela regex antes de chegar aqui em uso normal).
                $indice = 0;
            }

            $valor = $valor * self::BASE + $indice;
        }

        return self::ALFABETO[$valor % 31];
    }
}
