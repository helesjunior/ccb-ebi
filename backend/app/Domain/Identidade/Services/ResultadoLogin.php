<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Models\Usuario;

/**
 * Resultado da autenticacao por credenciais. Quando `mfaPendente` e true, o
 * unico artefato emitido e o `tokenDesafio` (escopo `mfa:pendente`); a sessao
 * plena so sai apos o segundo fator.
 */
class ResultadoLogin
{
    public function __construct(
        public readonly Usuario $usuario,
        public readonly bool $mfaPendente,
        public readonly ?string $tokenDesafio = null,
    ) {}
}
