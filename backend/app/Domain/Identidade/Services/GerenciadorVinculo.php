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
 *
 * Autorizacao por recurso: o ator so concede (papel, escopo) dentro da sua
 * autoridade e subarvore, e so encerra vinculos que ele cobre — mesma regra dos
 * convites, evitando escalada vertical via /access-grants.
 */
class GerenciadorVinculo implements GerirVinculo
{
    public function __construct(
        private readonly RegistrarEvento $auditoria,
        private readonly AutoridadeDePapel $autoridade,
        private readonly GuardaChefe $guarda,
    ) {}

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

        if (! $this->autoridade->podeSobrePapel($contexto, $papelCodigo)
            || ! $this->autoridade->cobre($contexto, $tipoEscopo, $escopoId)) {
            throw new ProblemException('vinculo_fora_autoridade', 'Acesso negado', 403, 'Concessão fora da sua autoridade.');
        }

        $this->guarda->garantirEscopo($papelCodigo, $tipoEscopo);
        $this->guarda->garantirVaga($papelCodigo, $escopoId, $usuarioId);

        $vinculo = $this->guarda->traduzir(fn (): VinculoAcesso => DB::transaction(function () use ($usuarioId, $papel, $tipoEscopo, $escopoId): VinculoAcesso {
            // Encerra o vinculo vigente do mesmo par (preserva historico). Uma a
            // uma: o `saving` do model libera a vaga da chefe.
            VinculoAcesso::query()
                ->where('usuario_id', $usuarioId)
                ->where('papel_id', $papel->id)
                ->where('tipo_escopo', $tipoEscopo)
                ->where('escopo_id', $escopoId)
                ->whereNull('fim')
                ->get()
                ->each(fn (VinculoAcesso $v) => $v->forceFill([
                    'fim' => now()->toDateString(),
                    'status' => 'encerrado',
                ])->save());

            return VinculoAcesso::query()->create([
                'usuario_id' => $usuarioId,
                'papel_id' => $papel->id,
                'tipo_escopo' => $tipoEscopo,
                'escopo_id' => $escopoId,
                'inicio' => now()->toDateString(),
                'fim' => null,
                'status' => 'ativo',
            ]);
        }));

        $this->auditoria->registrar([
            'acao' => 'acesso.concedido',
            'recurso' => 'vinculo_acesso',
            'ator_id' => $contexto->usuarioId,
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

        if (! $this->autoridade->podeSobrePapel($contexto, (string) $vinculo->papel?->codigo)
            || ! $this->autoridade->cobre($contexto, (string) $vinculo->tipo_escopo, (string) $vinculo->escopo_id)) {
            throw new ProblemException('vinculo_fora_autoridade', 'Acesso negado', 403, 'Vínculo fora da sua autoridade.');
        }

        $vinculo->forceFill(['fim' => now()->toDateString(), 'status' => 'encerrado'])->save();

        $this->auditoria->registrar([
            'acao' => 'acesso.revogado',
            'recurso' => 'vinculo_acesso',
            'ator_id' => $contexto->usuarioId,
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
