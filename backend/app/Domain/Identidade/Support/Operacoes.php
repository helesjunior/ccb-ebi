<?php

namespace App\Domain\Identidade\Support;

/**
 * Catalogo de operacoes criticas (autorizacao por operacao, CA-03). Os codigos
 * coincidem com as abilities de Gate usadas em `can:` nas rotas.
 */
final class Operacoes
{
    public const ORGANIZACAO_LER = 'organizacao.ler';

    public const ORGANIZACAO_GERENCIAR = 'organizacao.gerenciar';

    public const USUARIO_LER = 'usuario.ler';

    public const USUARIO_GERENCIAR = 'usuario.gerenciar';

    public const CONVITE_GERENCIAR = 'convite.gerenciar';

    public const ACESSO_GERENCIAR = 'acesso.gerenciar';

    public const CONSENTIMENTO_LER = 'consentimento.ler';

    public const AUDITORIA_LER = 'auditoria.ler';

    private function __construct() {}
}
