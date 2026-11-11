<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Evidencia minima de uma manifestacao de consentimento: de onde veio o
 * pedido (ip e user agent) e qual texto foi aceito (hash da versao).
 */
readonly class EvidenciaAceite
{
    public function __construct(
        public string $ip,
        public string $userAgent,
        public ?string $termoHash = null,
        public ?string $arquivoId = null,
    ) {}

    public static function deRequest(Request $r, string $termoHash, ?string $arquivoId = null): self
    {
        return new self(
            ip: (string) $r->ip(),
            userAgent: (string) $r->userAgent(),
            termoHash: $termoHash,
            arquivoId: $arquivoId,
        );
    }
}
