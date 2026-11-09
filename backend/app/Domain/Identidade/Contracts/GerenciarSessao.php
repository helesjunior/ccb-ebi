<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Models\SessaoLogin;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Sessoes por dispositivo (RF-05): abertura no login, listagem e revogacao
 * pelo proprio usuario. A expiracao depende do perfil.
 */
interface GerenciarSessao
{
    /**
     * Abre uma sessao para o dispositivo informado, aplicando a expiracao do
     * perfil (12 h para responsavel; 8 h para os demais).
     */
    public function abrir(Usuario $usuario, string $dispositivo): SessaoLogin;

    /**
     * Sessoes vigentes (nao revogadas e nao expiradas) do usuario.
     *
     * @return array<int, array{id: string, dispositivo: string, criada_em: string, expira_em: string}>
     */
    public function listar(Authenticatable $usuario): array;

    /**
     * Revoga a sessao do usuario (idempotente; ignorada se nao for dele).
     */
    public function revogar(Authenticatable $usuario, string $id): void;
}
