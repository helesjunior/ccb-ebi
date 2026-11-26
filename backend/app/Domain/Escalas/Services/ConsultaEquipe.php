<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Contracts\ConsultarEquipe;
use App\Domain\Escalas\Contracts\EquipeRef;
use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\EquipeMembro;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;

/**
 * Leitura das equipes (E07, evolucao). A vigencia dos vinculos e lida do
 * Identidade na hora: quem perdeu o vinculo continua no quadro, mas aparece
 * com `sem_vinculo`.
 */
class ConsultaEquipe implements ConsultarEquipe
{
    public function __construct(private readonly ConsultarEquipeEbi $vinculos) {}

    public function referencia(string $equipeId): ?EquipeRef
    {
        $equipe = Equipe::query()->find($equipeId);

        return $equipe === null ? null : new EquipeRef(
            (string) $equipe->id,
            (string) $equipe->ebi_id,
            (string) $equipe->igreja_id,
            (string) $equipe->coordenadora_id,
        );
    }

    public function resumosDoEbi(string $ebiId, string $igrejaId): array
    {
        $equipes = Equipe::query()
            ->where('ebi_id', $ebiId)
            ->withCount('membros')
            ->get();

        if ($equipes->isEmpty()) {
            return [];
        }

        $ids = $equipes->map(fn (Equipe $e): string => (string) $e->coordenadora_id)->all();
        $nomes = $this->vinculos->nomes($ids);
        $vigentes = $this->vinculos->papeisVigentesHoje($ids, $ebiId, $igrejaId);

        $resumos = $equipes->map(fn (Equipe $e): array => [
            'id' => (string) $e->id,
            'ebi_id' => (string) $e->ebi_id,
            'coordenadora_id' => (string) $e->coordenadora_id,
            'coordenadora_nome' => $nomes[(string) $e->coordenadora_id] ?? '',
            'coordenadora_sem_vinculo' => ! in_array('coordenadora', $vigentes[(string) $e->coordenadora_id] ?? [], true),
            'quantidade_membros' => (int) $e->membros_count,
        ])->all();

        usort($resumos, fn (array $a, array $b): int => [$a['coordenadora_nome'], $a['id']] <=> [$b['coordenadora_nome'], $b['id']]);

        return $resumos;
    }

    public function detalhe(string $equipeId): ?array
    {
        $equipe = Equipe::query()->with('membros')->find($equipeId);
        if ($equipe === null) {
            return null;
        }

        $ebiId = (string) $equipe->ebi_id;
        $igrejaId = (string) $equipe->igreja_id;
        $coordenadoraId = (string) $equipe->coordenadora_id;

        /** @var array<int, EquipeMembro> $membros */
        $membros = $equipe->membros->sortBy(fn (EquipeMembro $m): string => (string) $m->usuario_id)->values()->all();
        $ids = array_merge([$coordenadoraId], array_map(fn (EquipeMembro $m): string => (string) $m->usuario_id, $membros));

        $nomes = $this->vinculos->nomes($ids);
        $vigentes = $this->vinculos->papeisVigentesHoje($ids, $ebiId, $igrejaId);

        $lista = array_map(fn (EquipeMembro $m): array => [
            'usuario_id' => (string) $m->usuario_id,
            'nome' => $nomes[(string) $m->usuario_id] ?? '',
            'sem_vinculo' => ($vigentes[(string) $m->usuario_id] ?? []) === [],
        ], $membros);

        usort($lista, fn (array $a, array $b): int => [$a['nome'], $a['usuario_id']] <=> [$b['nome'], $b['usuario_id']]);

        return [
            'id' => (string) $equipe->id,
            'ebi_id' => $ebiId,
            'igreja_id' => $igrejaId,
            'coordenadora_id' => $coordenadoraId,
            'coordenadora_nome' => $nomes[$coordenadoraId] ?? '',
            'coordenadora_sem_vinculo' => ! in_array('coordenadora', $vigentes[$coordenadoraId] ?? [], true),
            'membros' => $lista,
        ];
    }
}
