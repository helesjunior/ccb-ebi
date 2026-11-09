<?php

namespace App\Domain\Identidade\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Sessoes por dispositivo (RF-05): abertura no login, listagem e revogacao
 * pelo proprio usuario. A expiracao depende do perfil. A sessao e ligada ao
 * token Sanctum correspondente, de modo que revogar invalida o token.
 */
interface GerenciarSessao
{
    /**
     * Abre uma sessao para o dispositivo informado, aplicando a expiracao do
     * perfil (12 h para responsavel; 8 h para os demais), cria o token Sanctum
     * de sessao plena vinculado e devolve o token em claro.
     *
     * Recebe `Authenticatable` (nao o modelo) para que `app/Http` nao precise
     * importar o modelo do modulo (ADR-0002).
     */
    public function abrir(Authenticatable $usuario, string $dispositivo): string;

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
