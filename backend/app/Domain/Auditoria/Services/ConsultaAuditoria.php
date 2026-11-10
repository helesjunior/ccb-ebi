<?php

namespace App\Domain\Auditoria\Services;

use App\Domain\Auditoria\Contracts\ConsultarAuditoria;
use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Contracts\ResolverEscopo;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Consulta a trilha append-only com os filtros operacionais e o recorte
 * territorial. O `escopo` e textual (`tipo:id`), entao o conjunto visivel e
 * materializado a partir dos valores distintos presentes nos candidatos e
 * revalidado via `ResolverEscopo` (subarvore do no do vinculo).
 */
class ConsultaAuditoria implements ConsultarAuditoria
{
    public function __construct(private readonly ResolverEscopo $escopo) {}

    public function listar(
        string $papelCodigo,
        string $tipoEscopoVinculo,
        string $escopoIdVinculo,
        array $filtros,
        int $limit,
    ): CursorPaginator {
        $query = EventoAuditoria::query();

        $this->aplicarFiltros($query, $filtros);
        $this->aplicarRecorteTerritorial($query, $papelCodigo, $tipoEscopoVinculo, $escopoIdVinculo);

        return $query->orderBy('id')->cursorPaginate($limit);
    }

    /**
     * @param  Builder<EventoAuditoria>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        $recurso = $filtros['recurso'] ?? null;
        if (is_string($recurso) && $recurso !== '') {
            $query->where('recurso', $recurso);
        }

        $ator = $filtros['ator_id'] ?? null;
        if (is_string($ator) && $ator !== '') {
            $query->where('ator_id', $ator);
        }

        $de = $this->instante($filtros['de'] ?? null, false);
        if ($de !== null) {
            $query->where('ocorrido_em', '>=', $de);
        }

        $ate = $this->instante($filtros['ate'] ?? null, true);
        if ($ate !== null) {
            $query->where('ocorrido_em', '<=', $ate);
        }
    }

    /**
     * `super_admin` enxerga tudo. Os demais papeis so enxergam eventos cujo
     * escopo textual esteja coberto pela subarvore do vinculo; eventos sem
     * escopo (ou com tipo desconhecido) nunca aparecem.
     *
     * @param  Builder<EventoAuditoria>  $query
     */
    private function aplicarRecorteTerritorial(
        Builder $query,
        string $papelCodigo,
        string $tipoEscopoVinculo,
        string $escopoIdVinculo,
    ): void {
        if ($papelCodigo === 'super_admin') {
            return;
        }

        $textos = (clone $query)
            ->whereNotNull('escopo')
            ->distinct()
            ->pluck('escopo');

        $visiveis = [];

        foreach ($textos as $texto) {
            if (! is_string($texto) || $texto === '') {
                continue;
            }

            $partes = explode(':', $texto, 2);

            if (count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
                continue;
            }

            try {
                if ($this->escopo->cobre($tipoEscopoVinculo, $escopoIdVinculo, $partes[0], $partes[1])) {
                    $visiveis[] = $texto;
                }
            } catch (RuntimeException) {
                // No desconhecido na hierarquia: nega por seguranca.
            }
        }

        $query->whereIn('escopo', $visiveis);
    }

    /**
     * Normaliza o instante informado para o formato aceito pela coluna. Datas
     * puras no limite superior cobrem o dia inteiro. Entradas invalidas sao
     * ignoradas em vez de estourar a consulta.
     */
    private function instante(mixed $valor, bool $fimDoDia): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        if ($fimDoDia && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
            $valor .= ' 23:59:59';
        }

        $timestamp = strtotime($valor);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
