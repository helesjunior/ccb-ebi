<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\ResolverEscopo;

/**
 * Autoridade de um vinculo de acesso: combina a hierarquia de papeis (o poder
 * desce a hierarquia) com o escopo territorial (o alvo tem de estar na subarvore
 * do vinculo). Núcleo compartilhado por convites, gestao de usuarios e
 * concessoes de acesso — evita tres implementacoes divergentes.
 */
class AutoridadeDePapel
{
    /**
     * Autoridade por papel (maior = mais poder). Um ator so age sobre papeis de
     * autoridade menor ou igual a sua.
     *
     * @var array<string, int>
     */
    private const AUTORIDADE = [
        'super_admin' => 100,
        'adm_regional' => 80,
        'adm_setor' => 60,
        'adm_igreja' => 40,
        'coordenadora' => 30,
        'ministerio_central' => 20,
        'ministerio_regional' => 20,
        'ministerio_setorial' => 20,
        'ministerio_local' => 20,
        'auxiliar' => 10,
        'responsavel' => 0,
    ];

    public function __construct(private readonly ResolverEscopo $escopo) {}

    /**
     * O ator pode atribuir/gerir o papel `alvo`? Falso para papel desconhecido.
     */
    public function podeSobrePapel(ContextoAtuacao $ator, string $papelAlvo): bool
    {
        $autoridadeAtor = self::AUTORIDADE[$ator->papelCodigo] ?? -1;
        $autoridadeAlvo = self::AUTORIDADE[$papelAlvo] ?? -1;

        return $autoridadeAlvo >= 0 && $autoridadeAlvo <= $autoridadeAtor;
    }

    /**
     * O escopo-alvo esta dentro da subarvore do ator?
     */
    public function cobre(ContextoAtuacao $ator, string $tipoEscopo, string $escopoId): bool
    {
        // super_admin ignora o territorio (matriz): poder sem restricao de escopo.
        if ($ator->papelCodigo === 'super_admin') {
            return true;
        }

        try {
            return $this->escopo->cobre(
                $ator->tipoEscopo,
                $ator->escopoId,
                $tipoEscopo,
                $escopoId,
            );
        } catch (\RuntimeException) {
            return false;
        }
    }
}
