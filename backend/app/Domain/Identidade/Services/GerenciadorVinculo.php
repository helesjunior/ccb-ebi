<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Contracts\GerirVinculo;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

/**
 * Concessao/revogacao direta de vinculos de acesso. Preserva o historico via
 * vigencia `[inicio, fim)`; encerrar significa `fim = hoje`.
 */
class GerenciadorVinculo implements GerirVinculo
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function conceder(
        ContextoAtuacao $contexto,
        string $usuarioId,
        string $papelCodigo,
        string $tipoEscopo,
        string $escopoId,
    ): VinculoAcesso {
        $papel = Papel::query()->where('codigo', $papelCodigo)->first();

        if ($papel === null) {
            throw new ProblemException('papel_invalido', 'Concessão inválida', 422, 'Papel inexistente.');
        }

        $vinculo = DB::transaction(function () use ($usuarioId, $papel, $tipoEscopo, $escopoId): VinculoAcesso {
            // Encerra o vinculo vigente do mesmo par (preserva historico).
            VinculoAcesso::query()
                ->where('usuario_id', $usuarioId)
                ->where('papel_id', $papel->id)
                ->where('tipo_escopo', $tipoEscopo)
                ->where('escopo_id', $escopoId)
                ->whereNull('fim')
                ->update(['fim' => now()->toDateString()]);

            return VinculoAcesso::query()->create([
                'usuario_id' => $usuarioId,
                'papel_id' => $papel->id,
                'tipo_escopo' => $tipoEscopo,
                'escopo_id' => $escopoId,
                'inicio' => now()->toDateString(),
                'fim' => null,
                'status' => 'ativo',
            ]);
        });

        $this->auditoria->registrar([
            'acao' => 'acesso.concedido',
            'recurso' => 'vinculo_acesso',
            'ator_id' => null,
            'origem' => 'api',
            'escopo' => $contexto->tipoEscopo.':'.$contexto->escopoId,
            'motivo' => null,
            'metadados_minimizados' => [
                'vinculo_id' => (string) $vinculo->id,
                'usuario_id' => $usuarioId,
                'papel' => $papelCodigo,
                'escopo' => $tipoEscopo.':'.$escopoId,
            ],
            'ocorrido_em' => now(),
        ]);

        return $vinculo;
    }

    public function revogar(ContextoAtuacao $contexto, string $vinculoId): void
    {
        $vinculo = VinculoAcesso::query()->find($vinculoId);

        if ($vinculo === null || $vinculo->fim !== null) {
            return;
        }

        $vinculo->forceFill(['fim' => now()->toDateString(), 'status' => 'encerrado'])->save();

        $this->auditoria->registrar([
            'acao' => 'acesso.revogado',
            'recurso' => 'vinculo_acesso',
            'ator_id' => null,
            'origem' => 'api',
            'escopo' => $contexto->tipoEscopo.':'.$contexto->escopoId,
            'motivo' => null,
            'metadados_minimizados' => [
                'vinculo_id' => (string) $vinculo->id,
                'usuario_id' => (string) $vinculo->usuario_id,
            ],
            'ocorrido_em' => now(),
        ]);
    }
}
