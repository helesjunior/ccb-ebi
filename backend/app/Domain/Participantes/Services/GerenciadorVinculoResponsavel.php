<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Participantes\Contracts\GerirVinculoResponsavel;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Participantes\Models\SolicitacaoVinculo;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

/**
 * Vinculos do responsavel (RF-03, CA-04, CA-16).
 *
 * Duas tabelas (ADR-0031): `responsavel_participante` guarda a situacao corrente
 * (o gate de acesso e `validacao = aprovada`); `solicitacao_vinculo` e o log
 * append-only de solicitacoes/contestacoes/revogacoes. Encerrar um vinculo
 * preenche `fim` e NAO apaga o log.
 */
class GerenciadorVinculoResponsavel implements GerirVinculoResponsavel
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function solicitar(string $usuarioId, string $participanteId, array $dados, ?string $escopo = null): ResponsavelParticipante
    {
        if (! Participante::query()->whereKey($participanteId)->exists()) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');
        }

        return DB::transaction(function () use ($usuarioId, $participanteId, $dados, $escopo): ResponsavelParticipante {
            $vinculo = ResponsavelParticipante::create([
                'usuario_id' => $usuarioId,
                'nome' => (string) $dados['nome'],
                'telefone' => $dados['telefone'] ?? null,
                'participante_id' => $participanteId,
                'tipo_relacao' => (string) ($dados['tipo_relacao'] ?? 'responsavel'),
                'permissoes' => [],
                'status' => 'ativo',
                'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
                'inicio' => now()->toDateString(),
            ]);

            SolicitacaoVinculo::create([
                'usuario_id' => $usuarioId,
                'participante_id' => $participanteId,
                'responsavel_participante_id' => $vinculo->id,
                'tipo' => SolicitacaoVinculo::TIPO_SOLICITACAO,
                'status' => SolicitacaoVinculo::STATUS_PENDENTE,
            ]);

            $this->registrar('vinculo.solicitado', $usuarioId, $vinculo, [
                'tipo' => SolicitacaoVinculo::TIPO_SOLICITACAO,
            ], $escopo);

            return $vinculo;
        });
    }

    public function decidir(string $coordenadoraId, string $vinculoId, string $decisao, ?string $motivo = null, ?string $escopo = null): ResponsavelParticipante
    {
        $mapa = [
            'aprovar' => ResponsavelParticipante::VALIDACAO_APROVADA,
            'recusar' => ResponsavelParticipante::VALIDACAO_RECUSADA,
            'pedir_documento' => ResponsavelParticipante::VALIDACAO_AGUARDANDO_DOCUMENTO,
        ];

        $encerra = ['revogar', 'contestar'];
        $encerraVinculo = in_array($decisao, $encerra, true);

        if (! isset($mapa[$decisao]) && ! $encerraVinculo) {
            throw new ProblemException('decisao_invalida', 'Dados inválidos', 422, 'Decisão de vínculo inválida.');
        }

        return DB::transaction(function () use ($coordenadoraId, $vinculoId, $decisao, $motivo, $mapa, $encerraVinculo, $escopo): ResponsavelParticipante {
            $vinculo = ResponsavelParticipante::query()->find($vinculoId)
                ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vínculo não encontrado.');

            // Segregacao de funcoes: quem abriu o pedido nao o decide.
            if ($coordenadoraId !== '' && $vinculo->usuario_id !== null && (string) $vinculo->usuario_id === $coordenadoraId) {
                throw new ProblemException(
                    'acesso_negado',
                    'Acesso negado',
                    403,
                    'A decisão deve ser feita por outro operador do seu território.',
                );
            }

            if ($encerraVinculo) {
                $vinculo->fill([
                    'validacao' => ResponsavelParticipante::VALIDACAO_RECUSADA,
                    'fim' => now()->toDateString(),
                ]);
            } else {
                $vinculo->fill([
                    'validacao' => $mapa[$decisao],
                    'conferido_em' => now(),
                    'conferido_por' => $coordenadoraId,
                ]);
            }

            $vinculo->save();

            $tipoLog = $encerraVinculo
                ? ($decisao === 'contestar' ? SolicitacaoVinculo::TIPO_CONTESTACAO : SolicitacaoVinculo::TIPO_REVOGACAO)
                : SolicitacaoVinculo::TIPO_SOLICITACAO;

            SolicitacaoVinculo::create([
                'usuario_id' => $coordenadoraId,
                'participante_id' => $vinculo->participante_id,
                'responsavel_participante_id' => $vinculo->id,
                'tipo' => $tipoLog,
                'status' => $encerraVinculo ? SolicitacaoVinculo::STATUS_RECUSADA : SolicitacaoVinculo::STATUS_APROVADA,
                'decidido_por' => $coordenadoraId,
                'decidido_em' => now(),
                'motivo' => $motivo,
            ]);

            $this->registrar('vinculo.decidido', $coordenadoraId, $vinculo, [
                'decisao' => $decisao,
            ], $escopo);

            return $vinculo;
        });
    }

    public function dependentesDe(string $usuarioId): array
    {
        return ResponsavelParticipante::query()
            ->where('usuario_id', $usuarioId)
            ->where('validacao', ResponsavelParticipante::VALIDACAO_APROVADA)
            ->whereNull('fim')
            ->with('participante')
            ->get()
            ->pluck('participante')
            ->filter()
            ->values()
            ->all();
    }

    public function encontrar(string $vinculoId): ?ResponsavelParticipante
    {
        return ResponsavelParticipante::query()->find($vinculoId);
    }

    /**
     * @param  array<string, mixed>  $metadados
     */
    private function registrar(string $acao, string $atorId, ResponsavelParticipante $vinculo, array $metadados, ?string $escopo): void
    {
        $this->auditoria->registrar([
            'acao' => $acao,
            'recurso' => 'vinculo_responsavel',
            'ator_id' => $atorId,
            'origem' => 'api',
            'escopo' => $escopo,
            'motivo' => null,
            'metadados_minimizados' => [
                'vinculo_id' => (string) $vinculo->id,
                'participante_id' => (string) $vinculo->participante_id,
                ...$metadados,
            ],
            'ocorrido_em' => now(),
        ]);
    }
}
