<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Domain\Identidade\Models\Usuario;
use App\Support\ProblemException;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticacao provisoria da fundacao: valida e-mail e senha contra o usuario.
 * O login real (MFA, e-mail verificado) entra na etapa de identidade.
 */
class AutenticadorFicticio implements AutenticarAtor
{
    public function autenticar(string $email, string $senha): Usuario
    {
        $usuario = Usuario::query()
            ->where('email_normalizado', Usuario::emailNormalizado($email))
            ->first();

        if ($usuario === null || ! Hash::check($senha, (string) $usuario->senha)) {
            throw new ProblemException('unauthorized', 'Não autenticado', 401, 'Credenciais inválidas');
        }

        return $usuario;
    }
}
