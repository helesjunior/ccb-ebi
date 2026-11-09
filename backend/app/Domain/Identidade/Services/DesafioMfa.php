<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Models\Usuario;

/**
 * Emite o token de desafio de MFA: um token Sanctum com escopo restrito
 * (`mfa:pendente`) que NAO autoriza a area operacional. A sessao plena sai
 * depois da verificacao do segundo fator.
 */
class DesafioMfa
{
    public const ABILITY = 'mfa:pendente';

    public function emitir(Usuario $usuario): string
    {
        return $usuario->createToken('mfa-desafio', [self::ABILITY])->plainTextToken;
    }
}
