<?php

namespace App\Domain\Identidade\Contracts;

use App\Support\ProblemException;

/**
 * Recuperacao e redefinicao de senha (RF-03), sem enumeracao de contas: a
 * solicitacao sempre conclui com sucesso, exista ou nao o e-mail.
 */
interface RecuperarSenha
{
    /**
     * Solicita a redefinicao. Sempre conclui; envia o e-mail pela fila apenas
     * quando o usuario existe.
     */
    public function solicitar(string $email): void;

    /**
     * Redefine a senha com token valido. Invalida as sessoes (tokens Sanctum).
     *
     * @throws ProblemException 422 quando o token e invalido ou expirado.
     */
    public function redefinir(string $email, string $token, string $novaSenha): void;
}
