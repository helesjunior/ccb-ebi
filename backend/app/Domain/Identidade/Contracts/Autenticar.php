<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Services\ResultadoLogin;
use App\Support\ProblemException;

/**
 * Autentica por e-mail e senha e decide se a sessao plena pode ser emitida ou
 * se o perfil exige um segundo fator antes (CA-15 / RN de MFA).
 */
interface Autenticar
{
    /**
     * @throws ProblemException 401 quando as credenciais falham.
     */
    public function executar(string $email, string $senha): ResultadoLogin;
}
