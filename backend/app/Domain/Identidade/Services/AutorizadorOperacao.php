<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Identidade\Support\Operacoes;

/**
 * RBAC+ABAC: a matriz decide *o que* o papel pode (operacao); o escopo
 * territorial decide *onde* (subarvore do no do vinculo). Limita-se as
 * operacoes de E01 (estrutura organizacional e usuarios/vinculos).
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
            Operacoes::CONSENTIMENTO_LER,
        ],
        'adm_regional' => [
            Operacoes::ORGANIZACAO_LER, Operacoes::ORGANIZACAO_GERENCIAR,
            Operacoes::USUARIO_LER, Operacoes::USUARIO_GERENCIAR,
            Operacoes::CONVITE_GERENCIAR, Operacoes::ACESSO_GERENCIAR,
            Operacoes::CONSENTIMENTO_LER,
        ],
        'adm_setor' => [
            Operacoes::ORGANIZACAO_LER, Operacoes::ORGANIZACAO_GERENCIAR,
            Operacoes::USUARIO_LER, Operacoes::USUARIO_GERENCIAR,
            Operacoes::CONVITE_GERENCIAR, Operacoes::ACESSO_GERENCIAR,
            Operacoes::CONSENTIMENTO_LER,
        ],
        'adm_igreja' => [
            Operacoes::ORGANIZACAO_LER,
            Operacoes::USUARIO_LER, Operacoes::USUARIO_GERENCIAR,
            Operacoes::CONVITE_GERENCIAR, Operacoes::ACESSO_GERENCIAR,
            Operacoes::CONSENTIMENTO_LER,
        ],
        'coordenadora' => [
            Operacoes::ORGANIZACAO_LER, Operacoes::USUARIO_LER,
        ],
        'auxiliar' => [
            Operacoes::ORGANIZACAO_LER,
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
        ],
        'responsavel' => [],
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
