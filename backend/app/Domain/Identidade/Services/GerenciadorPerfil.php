<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Contracts\GerirPerfil;
use App\Domain\Identidade\Models\Usuario;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Auto-gestao do proprio perfil: nome e e-mail. O e-mail e normalizado e nao
 * pode colidir com outro usuario (422). Quando o e-mail muda, a verificacao e
 * zerada (reverifica). Toda alteracao e auditada (RN-10).
 */
class GerenciadorPerfil implements GerirPerfil
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function atualizar(Authenticatable $usuario, array $dados): object
    {
        $usuario = $this->comoUsuario($usuario);

        return DB::transaction(function () use ($usuario, $dados): Usuario {
            $camposAlterados = [];

            if (array_key_exists('nome', $dados)) {
                $usuario->nome = (string) $dados['nome'];
                $camposAlterados[] = 'nome';
            }

            if (array_key_exists('email', $dados)) {
                $emailNormalizado = Usuario::emailNormalizado((string) $dados['email']);

                if ($emailNormalizado !== $usuario->email_normalizado) {
                    $emUso = Usuario::query()
                        ->where('email_normalizado', $emailNormalizado)
                        ->whereKeyNot($usuario->getKey())
                        ->exists();

                    if ($emUso) {
                        throw new ProblemException('email_em_uso', 'E-mail em uso', 422, 'Este e-mail já está em uso.');
                    }

                    $usuario->email_normalizado = $emailNormalizado;
                    $usuario->email_verificado_em = null;
                    $camposAlterados[] = 'email';
                }
            }

            if ($camposAlterados !== []) {
                $usuario->save();

                $this->auditoria->registrar([
                    'acao' => 'alterar_perfil',
                    'recurso' => 'usuario',
                    'ator_id' => (string) $usuario->getKey(),
                    'origem' => 'api',
                    'escopo' => null,
                    'motivo' => null,
                    'metadados_minimizados' => [
                        'usuario_id' => (string) $usuario->getKey(),
                        'campos' => $camposAlterados,
                    ],
                    'ocorrido_em' => now(),
                ]);
            }

            return $usuario;
        });
    }

    private function comoUsuario(Authenticatable $usuario): Usuario
    {
        if (! $usuario instanceof Usuario) {
            throw new ProblemException('usuario_invalido', 'Acesso negado', 403, 'Usuário de identidade inválido.');
        }

        return $usuario;
    }
}
