<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\ConsultarUsuarios;
use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Lista a equipe dentro do escopo de atuacao: somente usuarios com pelo menos
 * um vinculo vigente cujo no esteja coberto pelo no do contexto (o poder desce
 * a hierarquia; irmaos e niveis acima ficam de fora).
 */
class ConsultaUsuarios implements ConsultarUsuarios
{
    public function __construct(private readonly ResolverEscopo $escopo) {}

    public function listar(ContextoAtuacao $contexto, array $filtros, int $limit): CursorPaginator
    {
        $query = Usuario::query()->with('vinculosVigentes.papel');
        $ids = $this->idsNoEscopo($contexto);

        $query->whereIn('id', $ids);

        if (isset($filtros['status']) && $filtros['status'] !== '') {
            $query->where('status', $filtros['status']);
        }

        return $query->orderBy('id')->cursorPaginate($limit);
    }

    public function encontrar(ContextoAtuacao $contexto, string $id): ?object
    {
        $ids = $this->idsNoEscopo($contexto);

        return Usuario::query()->with('vinculosVigentes.papel')->whereIn('id', $ids)->find($id);
    }

    /**
     * IDs de usuarios com vinculo vigente coberto pelo contexto.
     *
     * @return array<int, string>
     */
    private function idsNoEscopo(ContextoAtuacao $contexto): array
    {
        return VinculoAcesso::query()
            ->vigente()
            ->get(['usuario_id', 'tipo_escopo', 'escopo_id'])
            ->filter(function (VinculoAcesso $v) use ($contexto): bool {
                try {
                    return $this->escopo->cobre(
                        $contexto->tipoEscopo,
                        $contexto->escopoId,
                        $v->tipo_escopo,
                        (string) $v->escopo_id,
                    );
                } catch (\RuntimeException) {
                    // No desconhecido na hierarquia: nega por seguranca.
                    return false;
                }
            })
            ->pluck('usuario_id')
            ->unique()
            ->values()
            ->all();
    }
}
