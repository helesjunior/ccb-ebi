<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Models\Ator;

interface AutenticarAtor
{
    /**
     * Autentica pelo e-mail e senha. Lança ProblemException (401) se falhar.
     */
    public function autenticar(string $email, string $senha): Ator;
}
