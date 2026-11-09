<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\Autenticar;
use App\Domain\Identidade\Contracts\ExigirMfa;
use App\Domain\Identidade\Models\Usuario;
use App\Support\ProblemException;
use Illuminate\Support\Facades\Hash;

/**
 * Login real por e-mail e senha. Se o perfil exige segundo fator e ainda nao
 * ha fator validado, devolve apenas o token de desafio; caso contrario libera
 * a sessao plena.
 */
class Autenticador implements Autenticar
{
    public function __construct(
        private readonly ExigirMfa $exigirMfa,
        private readonly DesafioMfa $desafioMfa,
    ) {}

    public function executar(string $email, string $senha): ResultadoLogin
    {
        $usuario = Usuario::query()
            ->where('email_normalizado', Usuario::emailNormalizado($email))
            ->first();

        if ($usuario === null || $usuario->senha === null || ! Hash::check($senha, (string) $usuario->senha)) {
            throw new ProblemException('unauthorized', 'Não autenticado', 401, 'Credenciais inválidas');
        }

        // CA-15: todo perfil que exige MFA so conclui o login apos o segundo
        // fator. O desafio cobre dois casos: usuario com fator (valida em
        // /auth/mfa/verify) e usuario sem fator (inscreve o TOTP primeiro).
        $precisaSegundoFator = $this->exigirMfa->exige($usuario);

        if ($precisaSegundoFator) {
            return new ResultadoLogin(
                $usuario,
                mfaPendente: true,
                tokenDesafio: $this->desafioMfa->emitir($usuario),
                mfaInscricaoPendente: ! $this->exigirMfa->temFatorValidado($usuario),
            );
        }

        return new ResultadoLogin($usuario, mfaPendente: false);
    }
}
