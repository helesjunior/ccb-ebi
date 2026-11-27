<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Participantes\Contracts\GerirExcecaoEtaria;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

/**
 * Excecao inclusiva (FL-06, ADR-0012): solicitacao pelo responsavel e decisao
 * pela coordenadora. Toda decisao e auditada e preserva o historico (a excecao
 * nunca e apagada; vira revogada/expirada).
 */
class GerenciadorExcecao implements GerirExcecaoEtaria
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function solicitar(string $solicitanteId, string $participanteId, array $dados, ?string $escopo = null): ExcecaoEtaria
    {
        if (! Participante::query()->whereKey($participanteId)->exists()) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');
        }

        $excecao = ExcecaoEtaria::create([
            'participante_id' => $participanteId,
            'tipo_necessidade' => (string) $dados['tipo_necessidade'],
            'apoios_necessarios' => $dados['apoios_necessarios'] ?? null,
            'justificativa_funcional' => $dados['justificativa_funcional'] ?? null,
            'status' => ExcecaoEtaria::STATUS_SOLICITADA,
            'solicitado_por' => $solicitanteId,
        ]);

        $this->registrar('excecao.solicitada', $solicitanteId, $excecao, [], $escopo);

        return $excecao;
    }

    public function decidir(string $decidorId, string $excecaoId, string $decisao, array $dados = [], ?string $escopo = null): ExcecaoEtaria
    {
        $status = match ($decisao) {
            'aprovar' => ExcecaoEtaria::STATUS_APROVADA,
            'recusar' => ExcecaoEtaria::STATUS_RECUSADA,
            'suspender' => ExcecaoEtaria::STATUS_SUSPENSA,
            'revogar' => ExcecaoEtaria::STATUS_REVOGADA,
            default => throw new ProblemException('decisao_invalida', 'Dados inválidos', 422, 'Decisão de exceção inválida.'),
        };

        return DB::transaction(function () use ($decidorId, $excecaoId, $decisao, $status, $dados, $escopo): ExcecaoEtaria {
            $excecao = ExcecaoEtaria::query()->find($excecaoId)
                ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Exceção não encontrada.');

            $excecao->fill([
                'status' => $status,
                'decidido_por' => $decidorId,
                'decidido_em' => now(),
                'vigente_ate' => $dados['vigente_ate'] ?? $excecao->vigente_ate,
                'revisao_prevista_em' => $dados['revisao_prevista_em'] ?? $excecao->revisao_prevista_em,
                'responsavel_concordante_id' => $dados['responsavel_concordante_id'] ?? $excecao->responsavel_concordante_id,
                'motivo' => $dados['motivo'] ?? $excecao->motivo,
                'revogado_em' => $decisao === 'revogar' ? now() : $excecao->revogado_em,
            ])->save();

            $this->registrar('excecao.decidida', $decidorId, $excecao, ['decisao' => $decisao], $escopo);

            return $excecao;
        });
    }

    /**
     * @param  array<string, mixed>  $metadados
     */
    private function registrar(string $acao, string $atorId, ExcecaoEtaria $excecao, array $metadados = [], ?string $escopo = null): void
    {
        $this->auditoria->registrar([
            'acao' => $acao,
            'recurso' => 'excecao_etaria',
            'ator_id' => $atorId,
            'origem' => 'api',
            'escopo' => $escopo,
            'motivo' => null,
            'metadados_minimizados' => [
                'excecao_id' => (string) $excecao->id,
                'participante_id' => (string) $excecao->participante_id,
                ...$metadados,
            ],
            'ocorrido_em' => now(),
        ]);
    }
}
