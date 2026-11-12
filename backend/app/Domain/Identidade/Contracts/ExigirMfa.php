<?php

namespace App\Domain\Identidade\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Politica de MFA: decide se um perfil exige segundo fator e se ja possui um
 * fator validado na sessao. Definida aqui (Task 7) como a costura; a Task 8
 * implementa a versao real consultando `fator_mfa`.
 *
 * A superficie publica usa `Authenticatable` (fronteira modular): quem esta
 * fora do modulo nao enxerga o modelo `Usuario`.
 */
interface ExigirMfa
{
    /**
     * true quando o perfil do usuario exige segundo fator (todo papel que nao
     * seja `responsavel`).
     */
    public function exige(Authenticatable $usuario): bool;

    /**
     * true quando o usuario possui ao menos um fator MFA validado.
     */
    public function temFatorValidado(Authenticatable $usuario): bool;
}
