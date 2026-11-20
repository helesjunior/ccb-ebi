<?php

namespace App\Domain\Organizacao\Services;

use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use App\Support\RecursoNaoEncontradoException;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Percorre a hierarquia Central -> Regional -> Setor -> Igreja -> EBI usando
 * os modelos do proprio modulo e devolve o mapa `tipo => id` do no e de seus
 * ascendentes. A ligacao igreja->setor vem da vinculacao vigente (`fim IS NULL`).
 */
class ConsultaHierarquia implements ConsultarHierarquia
{
    public function ancestrais(string $tipo, string $id): array
    {
        return match ($tipo) {
            'central' => $this->central($id),
            'regional' => $this->regional($id),
            'setor' => $this->setor($id),
            'igreja' => $this->igreja($id),
            'ebi' => $this->ebi($id),
            default => throw new RuntimeException("Tipo de escopo desconhecido: {$tipo}"),
        };
    }

    public function centralId(): ?string
    {
        return CentralNacional::query()->value('id');
    }

    public function ebiDaigreja(string $igrejaId): ?string
    {
        return Ebi::query()->where('igreja_id', $igrejaId)->value('id');
    }

    public function garantirCentralId(): string
    {
        $central = CentralNacional::query()->firstOrCreate(['nome' => 'Central (Brás)']);

        return $central->id;
    }

    public function filhos(string $tipo, string $id): array
    {
        return match ($tipo) {
            'central' => $this->mapear(Regional::query()->where('central_nacional_id', $id)->orderBy('nome'), 'regional'),
            'regional' => $this->mapear(Setor::query()->where('regional_id', $id)->orderBy('nome'), 'setor'),
            'setor' => $this->mapear(
                Igreja::query()->whereIn('id', $this->igrejasDoSetor($id))->orderBy('nome'),
                'igreja',
            ),
            'igreja' => $this->mapear(Ebi::query()->where('igreja_id', $id)->orderBy('nome'), 'ebi'),
            'ebi' => [],
            default => throw new RuntimeException("Tipo de escopo desconhecido: {$tipo}"),
        };
    }

    /** @return array<int, string> */
    public function igrejasDaSubarvore(string $tipo, string $id): array
    {
        return match ($tipo) {
            'central' => $this->igrejasDaCentral($id),
            'regional' => $this->igrejasDaRegional($id),
            'setor' => $this->igrejasDoSetor($id),
            'igreja' => Igreja::query()->whereKey($id)->pluck('id')->all(),
            'ebi' => [],
            default => throw new RuntimeException("Tipo de escopo desconhecido: {$tipo}"),
        };
    }

    /** @return array<int, string> */
    public function ebisDaSubarvore(string $tipo, string $id): array
    {
        if ($tipo === 'ebi') {
            return Ebi::query()->whereKey($id)->pluck('id')->all();
        }

        return Ebi::query()->whereIn('igreja_id', $this->igrejasDaSubarvore($tipo, $id))->pluck('id')->all();
    }

    /** @return array<int, string> */
    private function igrejasDaCentral(string $centralId): array
    {
        return IgrejaVinculacao::query()
            ->whereNull('fim')
            ->whereIn('setor_id', Setor::query()
                ->whereIn('regional_id', Regional::query()
                    ->where('central_nacional_id', $centralId)
                    ->select('id'))
                ->select('id'))
            ->pluck('igreja_id')
            ->all();
    }

    /** @return array<int, string> */
    private function igrejasDaRegional(string $regionalId): array
    {
        return IgrejaVinculacao::query()
            ->whereNull('fim')
            ->whereIn('setor_id', Setor::query()->where('regional_id', $regionalId)->select('id'))
            ->pluck('igreja_id')
            ->all();
    }

    /** @return array<int, string> */
    private function igrejasDoSetor(string $setorId): array
    {
        return IgrejaVinculacao::query()
            ->where('setor_id', $setorId)
            ->whereNull('fim')
            ->pluck('igreja_id')
            ->all();
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $consulta
     * @return array<int, array{tipo: string, id: string, nome: string}>
     */
    private function mapear($consulta, string $tipo): array
    {
        return $consulta->get()
            ->map(fn ($no): array => ['tipo' => $tipo, 'id' => (string) $no->id, 'nome' => (string) $no->nome])
            ->all();
    }

    /** @return array<string, string> */
    private function central(string $id): array
    {
        $central = CentralNacional::query()->find($id);

        if ($central === null) {
            throw new RecursoNaoEncontradoException("Central nao encontrada: {$id}");
        }

        return ['central' => $central->id];
    }

    /** @return array<string, string> */
    private function regional(string $id): array
    {
        $regional = Regional::query()->find($id);

        if ($regional === null) {
            throw new RecursoNaoEncontradoException("Regional nao encontrada: {$id}");
        }

        return ['regional' => $regional->id, 'central' => $regional->central_nacional_id];
    }

    /** @return array<string, string> */
    private function setor(string $id): array
    {
        $setor = Setor::query()->with('regional')->find($id);

        if ($setor === null || $setor->regional === null) {
            throw new RecursoNaoEncontradoException("Setor nao encontrado: {$id}");
        }

        return [
            'setor' => $setor->id,
            'regional' => $setor->regional->id,
            'central' => $setor->regional->central_nacional_id,
        ];
    }

    /** @return array<string, string> */
    private function igreja(string $id): array
    {
        $igreja = Igreja::query()->with('vinculacaoVigente.setor.regional')->find($id);

        if ($igreja === null) {
            throw new RecursoNaoEncontradoException("Igreja nao encontrada: {$id}");
        }

        $ancestrais = ['igreja' => $igreja->id];

        $setor = $igreja->vinculacaoVigente?->setor;
        if ($setor !== null) {
            $ancestrais['setor'] = $setor->id;

            if ($setor->regional !== null) {
                $ancestrais['regional'] = $setor->regional->id;
                $ancestrais['central'] = $setor->regional->central_nacional_id;
            }
        }

        return $ancestrais;
    }

    /** @return array<string, string> */
    private function ebi(string $id): array
    {
        $ebi = Ebi::query()->find($id);

        if ($ebi === null) {
            throw new RecursoNaoEncontradoException("EBI nao encontrada: {$id}");
        }

        return ['ebi' => $ebi->id] + $this->igreja($ebi->igreja_id);
    }
}
