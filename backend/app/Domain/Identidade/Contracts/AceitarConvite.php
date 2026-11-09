<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Services\ContaCriada;
use App\Support\ProblemException;

/**
 * Aceite de convite sem login (RF-05): o token secreto do convite é a
 * credencial. Cria a conta já vinculada (papel+escopo do convite) e marca o
 * convite como aceito (uso único). O novo usuário nasce sem fator MFA.
 */
interface AceitarConvite
{
    /**
     * @throws ProblemException 410 quando o convite é inexistente, expirado, cancelado, já aceito ou o token não confere.
     */
    public function aceitar(string $conviteId, string $token, string $nome, string $senha): ContaCriada;
}
