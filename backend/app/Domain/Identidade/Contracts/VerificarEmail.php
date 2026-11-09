<?php

namespace App\Domain\Identidade\Contracts;

/**
 * Verificacao de e-mail: consome o token assinado e reenvia o link.
 * O reenvio nunca revela se a conta existe (resposta constante).
 */
interface VerificarEmail
{
    public function verificar(string $usuarioId): void;

    public function reenviar(string $email): void;
}
