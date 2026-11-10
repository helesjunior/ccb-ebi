<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Models\Usuario;
use App\Support\EvidenciaAceite;

/**
 * Cadastro de conta (self-service do Responsavel). Cria o usuario com e-mail
 * nao verificado e dispara a verificacao por e-mail (fila). Exige o aceite do
 * termo de cadastro vigente (CA-16), registrado na mesma transacao.
 */
interface RegistrarConta
{
    public function registrar(
        string $nome,
        string $email,
        string $senha,
        string $termoVersaoId,
        string $origem,
        EvidenciaAceite $evidencia,
    ): Usuario;
}
