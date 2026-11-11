<?php

namespace App\Support\Arquivo;

use RuntimeException;

/**
 * Cliente do ClamAV (clamd, TCP 3310). O pipeline passa o conteudo do arquivo e
 * recebe o veredito. Em `testing`, o container de teste troca esta classe por um
 * dublê (nao ha clamd no host de teste).
 *
 * Protocolo clamd INSTREAM: `zINSTREAM\0` seguido de blocos
 * `<uint32 tamanho><bytes>`, terminando com tamanho 0. A resposta `OK` (sem
 * `FOUND`) significa limpo.
 */
class ClienteAntivirus
{
    public function __construct(
        private readonly string $host = '',
        private readonly int $porta = 3310,
        private readonly int $timeout = 15,
    ) {}

    /** @return bool true = limpo, false = infectado. */
    public function scan(string $conteudo): bool
    {
        $host = $this->host !== '' ? $this->host : (string) config('services.clamav.host', env('CLAMAV_HOST', 'clamav'));

        $socket = @fsockopen($host, $this->porta, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            throw new RuntimeException("Não foi possível conectar ao antivírus em {$host}:{$this->porta}: {$errstr}");
        }

        stream_set_timeout($socket, $this->timeout);

        fwrite($socket, "zINSTREAM\0");

        foreach (str_split($conteudo, 8192) as $bloco) {
            fwrite($socket, pack('N', strlen($bloco)).$bloco);
        }

        fwrite($socket, pack('N', 0));

        $resposta = '';
        while (! feof($socket)) {
            $linha = fgets($socket, 4096);
            if ($linha === false) {
                break;
            }
            $resposta .= $linha;
        }

        fclose($socket);

        if (str_contains($resposta, 'FOUND')) {
            return false;
        }

        if (! str_contains($resposta, 'OK')) {
            throw new RuntimeException('Resposta inesperada do antivírus: '.trim($resposta));
        }

        return true;
    }
}
