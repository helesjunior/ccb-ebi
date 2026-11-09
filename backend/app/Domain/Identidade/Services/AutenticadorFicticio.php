<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Domain\Identidade\Models\Ator;
use App\Support\ProblemException;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticação fictícia da Etapa 1: valida contra o ator semeado.
 * Substituída pelo login real em etapa posterior.
 */
class AutenticadorFicticio implements AutenticarAtor
{
    public function autenticar(string $email, string $senha): Ator
    {
        $ator = Ator::query()->where('email', $email)->first();

        if ($ator === null || ! Hash::check($senha, $ator->senha)) {
            throw new ProblemException('unauthorized', 'Credenciais inválidas', 401);
        }

        return $ator;
    }
}
