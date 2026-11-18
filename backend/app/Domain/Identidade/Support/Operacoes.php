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

    // E03 — Participantes e responsáveis (RF-02, RF-03).
    public const PARTICIPANTE_LER = 'participante.ler';

    public const PARTICIPANTE_CADASTRAR = 'participante.cadastrar';

    public const PARTICIPANTE_CORRIGIR = 'participante.corrigir';

    public const PARTICIPANTE_MESCLAR = 'participante.mesclar';

    public const VINCULO_GERENCIAR = 'vinculo.gerenciar';

    public const EXCECAO_LER = 'excecao.ler';

    public const EXCECAO_DECIDIR = 'excecao.decidir';

    public const EVIDENCIA_LER = 'evidencia.ler';

    // E04 — Agenda, sessões e presença (RF-06, RF-08).
    public const AGENDA_GERENCIAR = 'agenda.gerenciar';

    public const SESSAO_LER = 'sessao.ler';

    public const SESSAO_GERENCIAR = 'sessao.gerenciar';

    public const PRESENCA_LER = 'presenca.ler';

    public const PRESENCA_REGISTRAR = 'presenca.registrar';

    public const PRESENCA_ESTORNAR = 'presenca.estornar';

    private function __construct() {}
}
