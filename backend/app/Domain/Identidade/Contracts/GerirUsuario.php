<?php

namespace App\Domain\Identidade\Contracts;

use App\Domain\Identidade\Services\ContextoAtuacao;

/**
 * Gestao da equipe (RF-05): atualizacao de dados do usuario (nome, status —
 * suspender/reativar). Auditoria obrigatoria (RN-10).
 */
interface GerirUsuario
{
    /**
     * Atualiza nome e/ou status (ativo|suspenso). Devolve o usuario atualizado
     * ou null se nao existir.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(ContextoAtuacao $contexto, string $id, array $dados): ?object;
}
