<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Contracts\GerirPerfil;
use App\Domain\Identidade\Models\SessaoLogin;
use App\Domain\Identidade\Models\Usuario;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

    public function alterarSenha(Authenticatable $usuario, string $senhaAtual, string $novaSenha): void
    {
        $usuario = $this->comoUsuario($usuario);

        if (! Hash::check($senhaAtual, $usuario->getAuthPassword())) {
            throw new ProblemException('senha_atual_invalida', 'Senha inválida', 422, 'A senha atual não confere.');
        }

        $tokenAtual = $usuario->currentAccessToken()->getKey();

        DB::transaction(function () use ($usuario, $novaSenha, $tokenAtual): void {
            $usuario->forceFill(['senha' => Hash::make($novaSenha)])->save();

            // Preserva a sessao atual e revoga as demais (RF-05). Os tokens de
            // desafio de MFA tambem caem: foram emitidos sob a senha antiga.
            $usuario->tokens()
                ->when($tokenAtual !== null, fn ($q) => $q->whereKeyNot($tokenAtual))
                ->delete();

            SessaoLogin::query()
                ->where('usuario_id', $usuario->id)
                ->whereNull('revogada_em')
                ->when($tokenAtual !== null, fn ($q) => $q->where('token_id', '!=', $tokenAtual))
                ->update(['revogada_em' => now()]);

            $this->auditoria->registrar([
                'acao' => 'alterar_senha',
                'recurso' => 'usuario',
                'ator_id' => (string) $usuario->getKey(),
                'origem' => 'api',
                'escopo' => null,
                'motivo' => null,
                'metadados_minimizados' => [
                    'usuario_id' => (string) $usuario->getKey(),
                ],
                'ocorrido_em' => now(),
            ]);
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
