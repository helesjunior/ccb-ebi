<?php

namespace App\Support;

/**
 * Lê um segredo de arquivo segundo a convenção twelve-factor: se existir a
 * variável de ambiente `<CHAVE>_FILE`, lê o valor do arquivo apontado por ela;
 * caso contrário, cai no valor da própria variável de ambiente.
 *
 * Este helper é consumido apenas pelos arquivos de configuração, onde o uso de
 * `env()` é o comportamento correto e esperado pelo Laravel.
 */
final class EnvArquivo
{
    public static function obter(string $chave): ?string
    {
        // Este helper só é consumido por arquivos de config, onde env() é permitido.
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig (uso restrito a config) */
        $arquivo = env("{$chave}_FILE");

        if (is_string($arquivo) && $arquivo !== '' && is_file($arquivo)) {
            $conteudo = file_get_contents($arquivo);

            if ($conteudo !== false) {
                return trim($conteudo);
            }
        }

        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig (uso restrito a config) */
        $valor = env($chave);

        return $valor === null ? null : (string) $valor;
    }
}
