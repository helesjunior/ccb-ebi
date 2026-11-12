<?php

namespace App\Domain\Identidade\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Auto-gestao do proprio perfil (nome/e-mail) — RF-05. Auditoria obrigatoria
 * (RN-10). Diferente de GerirUsuario, nao usa contexto territorial: o alvo e
 * sempre o proprio ator autenticado.
 */
interface GerirPerfil
{
    /**
     * Atualiza nome e/ou e-mail do proprio usuario. E-mail duplicado lanca
     * ProblemException 422 (code `email_em_uso`). Devolve o usuario atualizado.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(Authenticatable $usuario, array $dados): object;

    /**
     * Troca a senha do proprio usuario. Senha atual incorreta lanca
     * ProblemException 422 (code `senha_atual_invalida`). Preserva o token
     * atual e revoga os demais (RF-05).
     */
    public function alterarSenha(Authenticatable $usuario, string $senhaAtual, string $novaSenha): void;
}
