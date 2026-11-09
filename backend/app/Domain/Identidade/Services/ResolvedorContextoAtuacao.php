<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\ResolverContextoAtuacao;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class ResolvedorContextoAtuacao implements ResolverContextoAtuacao
{
    public function resolver(Authenticatable $usuario, ?string $header): ?ContextoAtuacao
    {
        if (! $usuario instanceof Usuario) {
            throw new ProblemException(
                'sem_vinculo',
                'Acesso negado',
                403,
                'Usuário de identidade inválido.',
            );
        }

        /** @var Collection<int, VinculoAcesso> $vinculos */
        $vinculos = $usuario->vinculosVigentes()->with('papel')->get();

        if ($vinculos->isEmpty()) {
            // Sem vinculo: sem contexto. A rota que exigir contexto recusa.
            return null;
        }

        if ($header === null || $header === '') {
            if ($vinculos->count() > 1) {
                throw new ProblemException(
                    'acting_scope_obrigatorio',
                    'Contexto de atuação obrigatório',
                    422,
                    'O usuário acumula papéis; informe X-Acting-Scope.',
                );
            }

            /** @var VinculoAcesso $unico */
            $unico = $vinculos->first();

            return $this->doVinculo($unico);
        }

        if (preg_match('/^papel=([a-z_]+);escopo=([a-z]+):(.+)$/', $header, $partes) !== 1) {
            throw new ProblemException(
                'acting_scope_invalido',
                'Contexto de atuação inválido',
                403,
                'Formato esperado: papel=<codigo>;escopo=<tipo>:<id>.',
            );
        }

        [, $papelCodigo, $tipoEscopo, $escopoId] = $partes;

        $encontrado = $vinculos->first(
            fn (VinculoAcesso $v): bool => $v->papel?->codigo === $papelCodigo
                && $v->tipo_escopo === $tipoEscopo
                && (string) $v->escopo_id === $escopoId,
        );

        if ($encontrado === null) {
            throw new ProblemException(
                'acting_scope_invalido',
                'Contexto de atuação inválido',
                403,
                'O contexto solicitado não corresponde a um vínculo vigente.',
            );
        }

        return new ContextoAtuacao($papelCodigo, $tipoEscopo, $escopoId);
    }

    private function doVinculo(VinculoAcesso $vinculo): ContextoAtuacao
    {
        /** @var Papel $papel */
        $papel = $vinculo->papel;

        return new ContextoAtuacao(
            $papel->codigo,
            $vinculo->tipo_escopo,
            (string) $vinculo->escopo_id,
        );
    }
}
