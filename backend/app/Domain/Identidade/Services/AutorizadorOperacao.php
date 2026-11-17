<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Identidade\Support\Operacoes;

/**
 * RBAC+ABAC: a matriz decide *o que* o papel pode (operacao); o escopo
 * territorial decide *onde* (subarvore do no do vinculo). Cobre as operacoes
 * de E01 (estrutura organizacional), E03 (participantes) e E04 (agenda,
 * sessoes e presenca).
 */
class AutorizadorOperacao implements AutorizarOperacao
{
    public function __construct(private readonly ResolverEscopo $escopo) {}

    /**
     * Mapa papel => operacoes permitidas, derivado da matriz de autorizacao.
     *
     * @var array<string, array<int, string>>
     */
    private const MATRIZ = [
        'super_admin' => [
            Operacoes::ORGANIZACAO_LER, Operacoes::ORGANIZACAO_GERENCIAR,
            Operacoes::USUARIO_LER, Operacoes::USUARIO_GERENCIAR,
            Operacoes::CONVITE_GERENCIAR, Operacoes::ACESSO_GERENCIAR,
            Operacoes::CONSENTIMENTO_LER, Operacoes::AUDITORIA_LER,
            Operacoes::PARTICIPANTE_LER, Operacoes::PARTICIPANTE_CADASTRAR,
            Operacoes::PARTICIPANTE_CORRIGIR, Operacoes::PARTICIPANTE_MESCLAR,
            Operacoes::VINCULO_GERENCIAR, Operacoes::EXCECAO_LER,
            Operacoes::EXCECAO_DECIDIR, Operacoes::EVIDENCIA_LER,
            // E04: acompanha (le) sessoes e presenca de todo o territorio.
            Operacoes::SESSAO_LER, Operacoes::PRESENCA_LER,
        ],
        'adm_regional' => [
            Operacoes::ORGANIZACAO_LER, Operacoes::ORGANIZACAO_GERENCIAR,
            Operacoes::USUARIO_LER, Operacoes::USUARIO_GERENCIAR,
            Operacoes::CONVITE_GERENCIAR, Operacoes::ACESSO_GERENCIAR,
            Operacoes::CONSENTIMENTO_LER, Operacoes::AUDITORIA_LER,
            Operacoes::PARTICIPANTE_LER, Operacoes::EXCECAO_LER,
            // E04: acompanha (le) sessoes e presenca da regional.
            Operacoes::SESSAO_LER, Operacoes::PRESENCA_LER,
        ],
        'adm_setor' => [
            Operacoes::ORGANIZACAO_LER, Operacoes::ORGANIZACAO_GERENCIAR,
            Operacoes::USUARIO_LER, Operacoes::USUARIO_GERENCIAR,
            Operacoes::CONVITE_GERENCIAR, Operacoes::ACESSO_GERENCIAR,
            Operacoes::CONSENTIMENTO_LER, Operacoes::AUDITORIA_LER,
            Operacoes::PARTICIPANTE_LER, Operacoes::EXCECAO_LER,
            // E04: acompanha (le) sessoes e presenca do setor.
            Operacoes::SESSAO_LER, Operacoes::PRESENCA_LER,
        ],
        'adm_igreja' => [
            Operacoes::ORGANIZACAO_LER,
            Operacoes::USUARIO_LER, Operacoes::USUARIO_GERENCIAR,
            Operacoes::CONVITE_GERENCIAR, Operacoes::ACESSO_GERENCIAR,
            Operacoes::CONSENTIMENTO_LER,
            Operacoes::PARTICIPANTE_LER, Operacoes::PARTICIPANTE_CADASTRAR,
            Operacoes::EXCECAO_LER, Operacoes::EVIDENCIA_LER,
            // E04: gere a agenda e as sessoes da EBI; registra presenca fica com
            // coordenadora/auxiliar, mas pode estornar lancamentos.
            Operacoes::AGENDA_GERENCIAR, Operacoes::SESSAO_LER, Operacoes::SESSAO_GERENCIAR,
            Operacoes::PRESENCA_LER, Operacoes::PRESENCA_ESTORNAR,
        ],
        'coordenadora' => [
            Operacoes::ORGANIZACAO_LER, Operacoes::USUARIO_LER,
            Operacoes::PARTICIPANTE_LER, Operacoes::PARTICIPANTE_CADASTRAR,
            Operacoes::PARTICIPANTE_CORRIGIR, Operacoes::PARTICIPANTE_MESCLAR,
            Operacoes::VINCULO_GERENCIAR, Operacoes::EXCECAO_LER,
            Operacoes::EXCECAO_DECIDIR, Operacoes::EVIDENCIA_LER,
            // E04: gere a agenda/sessoes da EBI e conduz a lista de presenca.
            Operacoes::AGENDA_GERENCIAR, Operacoes::SESSAO_LER, Operacoes::SESSAO_GERENCIAR,
            Operacoes::PRESENCA_LER, Operacoes::PRESENCA_REGISTRAR, Operacoes::PRESENCA_ESTORNAR,
        ],
        'auxiliar' => [
            Operacoes::ORGANIZACAO_LER,
            Operacoes::PARTICIPANTE_LER, Operacoes::PARTICIPANTE_CADASTRAR,
            Operacoes::EXCECAO_LER,
            // E04: acompanha as sessoes e registra presenca; nao estorna nem gere.
            Operacoes::SESSAO_LER, Operacoes::PRESENCA_LER, Operacoes::PRESENCA_REGISTRAR,
        ],
        'ministerio_central' => [
            Operacoes::ORGANIZACAO_LER,
        ],
        'ministerio_regional' => [
            Operacoes::ORGANIZACAO_LER,
        ],
        'ministerio_setorial' => [
            Operacoes::ORGANIZACAO_LER,
        ],
        'ministerio_local' => [
            Operacoes::ORGANIZACAO_LER,
            Operacoes::PARTICIPANTE_LER, Operacoes::EXCECAO_LER,
            // E04: acompanha (le) sessoes e presenca da EBI da qual e membro.
            Operacoes::SESSAO_LER, Operacoes::PRESENCA_LER,
        ],
        'responsavel' => [
            // Cadastro do dependente (RF-02, FL-01): cria o participante e o
            // vinculo. A leitura/edicao depois e por propriedade (CA-04).
            Operacoes::PARTICIPANTE_CADASTRAR,
        ],
    ];

    public function permite(
        ContextoAtuacao $contexto,
        string $operacao,
        ?string $tipoRecurso = null,
        ?string $recursoId = null,
    ): bool {
        if (! in_array($operacao, self::MATRIZ[$contexto->papelCodigo] ?? [], true)) {
            return false;
        }

        // super_admin ignora o escopo territorial (bypass explicito e auditado).
        if ($contexto->papelCodigo === 'super_admin') {
            return true;
        }

        // Operacao sem recurso-alvo: basta a pertinencia do papel.
        if ($tipoRecurso === null || $recursoId === null) {
            return true;
        }

        return $this->escopo->cobre(
            $contexto->tipoEscopo,
            $contexto->escopoId,
            $tipoRecurso,
            $recursoId,
        );
    }
}
