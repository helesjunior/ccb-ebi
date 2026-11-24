<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use Illuminate\Database\Eloquent\Builder;

/**
 * Equipe do EBI a partir dos vinculos de acesso: papel de equipe e escopo no
 * EBI ou na igreja dele (o EBI e filho da igreja; quem tem papel na igreja
 * atende todas as EBIs dela).
 */
class ConsultaEquipeEbi implements ConsultarEquipeEbi
{
    public function equipe(string $ebiId, string $igrejaId): array
    {
        $vinculos = $this->recorte($ebiId, $igrejaId)
            ->whereNull('fim')
            ->with(['usuario', 'papel'])
            ->get();

        $porUsuario = [];

        foreach ($vinculos as $vinculo) {
            $id = (string) $vinculo->usuario_id;
            $porUsuario[$id] ??= [
                'usuario_id' => $id,
                'nome' => (string) $vinculo->usuario?->nome,
                'papeis' => [],
            ];
            $porUsuario[$id]['papeis'][] = (string) $vinculo->papel?->codigo;
        }

        foreach ($porUsuario as &$membro) {
            $membro['papeis'] = array_values(array_unique($membro['papeis']));
            sort($membro['papeis']);
        }
        unset($membro);

        usort($porUsuario, fn (array $a, array $b): int => [$a['nome'], $a['usuario_id']] <=> [$b['nome'], $b['usuario_id']]);

        return $porUsuario;
    }

    public function vigencias(array $usuarioIds, string $ebiId, string $igrejaId): array
    {
        if ($usuarioIds === []) {
            return [];
        }

        $vinculos = $this->recorte($ebiId, $igrejaId)
            ->whereIn('usuario_id', $usuarioIds)
            ->with('papel')
            ->orderBy('inicio')
            ->get();

        $porUsuario = [];

        foreach ($vinculos as $vinculo) {
            $porUsuario[(string) $vinculo->usuario_id][] = [
                'papel' => (string) $vinculo->papel?->codigo,
                'inicio' => $vinculo->inicio->toDateString(),
                'fim' => $vinculo->fim?->toDateString(),
            ];
        }

        return $porUsuario;
    }

    public function chefeVigente(string $ebiId): ?string
    {
        $usuarioId = VinculoAcesso::query()
            ->where('chefe_de_ebi_id', $ebiId)
            ->value('usuario_id');

        return $usuarioId === null ? null : (string) $usuarioId;
    }

    public function nomes(array $usuarioIds): array
    {
        if ($usuarioIds === []) {
            return [];
        }

        return Usuario::query()
            ->whereIn('id', $usuarioIds)
            ->pluck('nome', 'id')
            ->map(fn ($nome): string => (string) $nome)
            ->all();
    }

    /**
     * @return Builder<VinculoAcesso>
     */
    private function recorte(string $ebiId, string $igrejaId): Builder
    {
        $papeis = Papel::query()->whereIn('codigo', self::PAPEIS_EQUIPE)->select('id');

        // EBI e igreja vêm de `EscopoIgreja`, mas são strings para nao acoplar
        // os modulos; o import fica no topo por clareza.
        return VinculoAcesso::query()
            ->whereIn('papel_id', $papeis)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $e) => $e->where('tipo_escopo', 'ebi')->where('escopo_id', $ebiId))
                ->orWhere(fn (Builder $i) => $i->where('tipo_escopo', 'igreja')->where('escopo_id', $igrejaId)));
    }
}
