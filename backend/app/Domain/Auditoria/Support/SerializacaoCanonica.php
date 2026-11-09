<?php

namespace App\Domain\Auditoria\Support;

use DateTimeInterface;

/**
 * Serializa um evento de auditoria de forma determinística, para que o
 * hash encadeado seja reproduzível independentemente da ordem das chaves.
 */
final class SerializacaoCanonica
{
    /**
     * @param  array<string, mixed>  $evento
     */
    public static function normalizar(array $evento): string
    {
        $normalizado = self::normalizarValor($evento);

        return json_encode($normalizado, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function normalizarValor(mixed $valor): mixed
    {
        if ($valor instanceof DateTimeInterface) {
            $utc = new \DateTimeImmutable($valor->format('Y-m-d H:i:s.u'), $valor->getTimezone());

            return $utc->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }

        if (is_array($valor)) {
            ksort($valor);

            return array_map(self::normalizarValor(...), $valor);
        }

        return $valor;
    }
}
