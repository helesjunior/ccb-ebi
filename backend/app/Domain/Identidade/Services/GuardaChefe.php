<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Models\VinculoAcesso;
use App\Support\ProblemException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Uma coordenadora chefe vigente por EBI (E07). Tres camadas, da mais clara a
 * mais segura: o escopo valido (so EBI), a checagem previa da vaga (erro legivel)
 * e a traducao da violacao do indice unico de `chefe_de_ebi_id` (a corrida entre
 * duas concessoes simultaneas).
 */
class GuardaChefe
{
    public const PAPEL = VinculoAcesso::PAPEL_CHEFE;

    public function garantirEscopo(string $papelCodigo, string $tipoEscopo): void
    {
        if ($papelCodigo === self::PAPEL && $tipoEscopo !== VinculoAcesso::TIPO_EBI) {
            throw new ProblemException(
                'escopo_invalido_para_papel',
                'Escopo inválido',
                422,
                'A coordenadora chefe só existe no escopo de EBI.',
            );
        }
    }

    public function garantirVaga(string $papelCodigo, string $escopoId, ?string $usuarioId = null): void
    {
        if (! $this->vagaOcupada($papelCodigo, $escopoId, $usuarioId)) {
            return;
        }

        throw $this->vagaOcupadaException();
    }

    public function vagaOcupada(string $papelCodigo, string $escopoId, ?string $usuarioId = null): bool
    {
        if ($papelCodigo !== self::PAPEL) {
            return false;
        }

        return VinculoAcesso::query()
            ->where('chefe_de_ebi_id', $escopoId)
            ->when($usuarioId !== null, fn ($q) => $q->where('usuario_id', '!=', $usuarioId))
            ->exists();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function traduzir(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'chefe_de_ebi_id')) {
                throw $this->vagaOcupadaException();
            }

            throw $e;
        }
    }

    private function vagaOcupadaException(): ProblemException
    {
        return new ProblemException(
            'chefe_ja_definida',
            'Vaga ocupada',
            422,
            'Este EBI já tem uma coordenadora chefe vigente.',
        );
    }
}
