<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Models\Usuario;

/**
 * Cadastro de conta (self-service do Responsavel). Cria o usuario com e-mail
 * nao verificado e dispara a verificacao por e-mail (fila).
 */
interface RegistrarConta
{
    public function registrar(string $nome, string $email, string $senha): Usuario;
}
