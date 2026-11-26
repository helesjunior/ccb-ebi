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

    public function papeisVigentesHoje(array $usuarioIds, string $ebiId, string $igrejaId): array
    {
        if ($usuarioIds === []) {
            return [];
        }

        // `fim` nulo e a autorizacao atual: quem foi revogado hoje ja nao oferece
        // nem e escolhido. Ja ter comecado e a elegibilidade na data da equipe
        // ou da sessao (futuro ainda nao conta); a data da sessao em si e outra
        // pergunta (`AvaliadorEquipe::vigenteEm`).
        $hoje = now()->toDateString();

        $vinculos = $this->recorte($ebiId, $igrejaId)
            ->whereIn('usuario_id', $usuarioIds)
            ->whereNull('fim')
            ->where('inicio', '<=', $hoje)
            ->with('papel')
            ->get();

        $porUsuario = [];

        foreach ($vinculos as $vinculo) {
            $porUsuario[(string) $vinculo->usuario_id][(string) $vinculo->papel?->codigo] = true;
        }

        return array_map(
            fn (array $papeis): array => array_map('strval', array_keys($papeis)),
            $porUsuario,
        );
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

    public function escoposDeEquipeVigentes(string $usuarioId): array
    {
        return array_values(array_map(
            fn (VinculoAcesso $vinculo): array => [
                'ebi_id' => $vinculo->tipo_escopo === 'ebi' ? (string) $vinculo->escopo_id : null,
                'igreja_id' => $vinculo->tipo_escopo === 'igreja' ? (string) $vinculo->escopo_id : '',
            ],
            $this->vinculosDeEquipeVigentes($usuarioId),
        ));
    }

    /**
     * @return array<int, VinculoAcesso>
     */
    private function vinculosDeEquipeVigentes(string $usuarioId): array
    {
        $papeis = Papel::query()->whereIn('codigo', self::PAPEIS_EQUIPE)->select('id');

        return VinculoAcesso::query()
            ->where('usuario_id', $usuarioId)
            ->whereNull('fim')
            ->whereIn('papel_id', $papeis)
            ->get()
            // Papel na igreja vale para todas as EBIs dela; a vaga traz o EBI e
            // a igreja da escala, entao o filtro aceita os dois escopos.
            ->unique(fn (VinculoAcesso $v): string => $v->tipo_escopo.':'.$v->escopo_id)
            ->values()
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
