<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Contracts\GerirUsuario;
use App\Domain\Identidade\Models\Usuario;

/**
 * Atualiza dados da equipe (nome, status). Toda mudanca de status e auditada
 * (RN-10); suspender invalida as sessoes do usuario (forca re-login).
 */
class GerenciadorUsuario implements GerirUsuario
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function atualizar(ContextoAtuacao $contexto, string $id, array $dados): ?object
    {
        $usuario = Usuario::query()->find($id);

        if ($usuario === null) {
            return null;
        }

        $statusAnterior = $usuario->status;

        if (array_key_exists('nome', $dados)) {
            $usuario->nome = (string) $dados['nome'];
        }

        if (array_key_exists('status', $dados)) {
            $usuario->status = (string) $dados['status'];
        }

        $usuario->save();

        if (array_key_exists('status', $dados) && $statusAnterior !== $usuario->status) {
            if ($usuario->status === 'suspenso') {
                // Suspensao revoga as sessoes (tokens Sanctum) — RN-10 / RF-05.
                $usuario->tokens()->delete();
            }

            $this->auditoria->registrar([
                'acao' => 'alterar_status_usuario',
                'recurso' => 'usuario',
                'ator_id' => null,
                'origem' => 'api',
                'escopo' => $contexto->tipoEscopo.':'.$contexto->escopoId,
                'motivo' => null,
                'metadados_minimizados' => [
                    'usuario_id' => (string) $usuario->id,
                    'de' => $statusAnterior,
                    'para' => $usuario->status,
                ],
                'ocorrido_em' => now(),
            ]);
        }

        return $usuario;
    }
}
