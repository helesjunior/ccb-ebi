<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Participantes\Contracts\GerirMesclagem;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

/**
 * Mesclagem reversivel (CA-27, ADR-0007).
 *
 * Mesclar funde o duplicado no principal (preenche campos ausentes, move o
 * vinculo de responsavel, marca o duplicado como `mesclado` e grava um snapshot
 * do estado anterior na coluna `snapshot_mesclagem` do principal). Desfazer
 * restaura o snapshot e devolve o duplicado a `ativo`.
 *
 * Escopo (ADR-0027): conferido no controller por
 * `AutorizacaoParticipante::exigirLigados` (igreja de referencia, ou presenca
 * valida quando nao ha igreja; a chefe precisa de um dos dois cadastros).
 */
class GerenciadorMesclagem implements GerirMesclagem
{
    public function __construct(
        private readonly RegistrarEvento $auditoria,
        private readonly DetectorDuplicidade $detector,
    ) {}

    public function detectar(array $dados): array
    {
        return $this->detector->detectar($dados);
    }

    public function mesclar(string $atorId, string $principalId, string $duplicadoId): Participante
    {
        if ($principalId === $duplicadoId) {
            throw new ProblemException('mesclagem_invalida', 'Dados inválidos', 422, 'Não é possível mesclar um cadastro com ele mesmo.');
        }

        return DB::transaction(function () use ($atorId, $principalId, $duplicadoId): Participante {
            $principal = $this->buscar($principalId, 'Participante principal não encontrado.');
            $duplicado = $this->buscar($duplicadoId, 'Participante duplicado não encontrado.');

            if ($duplicado->status === 'mesclado') {
                throw new ProblemException('ja_mesclado', 'Conflito', 409, 'O cadastro duplicado já foi mesclado.');
            }

            // Snapshot do estado anterior (reversao, ADR-0007): campos do
            // principal + id/status do duplicado + ids dos vinculos movidos
            // (para devolve-los ao duplicado ao desfazer).
            $vinculosMovidos = $duplicado->responsaveis()->pluck('id')->all();

            $snapshot = [
                'principal' => [
                    'nome' => $principal->nome,
                    'nome_social' => $principal->nome_social,
                    'data_nascimento' => $principal->data_nascimento->toDateString(),
                    'igreja_referencia_id' => $principal->igreja_referencia_id,
                ],
                'duplicado' => [
                    'id' => $duplicado->id,
                    'status' => $duplicado->status,
                ],
                'vinculos_movidos' => $vinculosMovidos,
            ];

            // Funde campos ausentes no principal.
            $principal->nome_social = $principal->nome_social ?? $duplicado->nome_social;
            $principal->igreja_referencia_id = $principal->igreja_referencia_id ?? $duplicado->igreja_referencia_id;
            $principal->snapshot_mesclagem = $snapshot;
            $principal->save();

            // Move vinculos de responsavel do duplicado para o principal.
            $duplicado->responsaveis()->update(['participante_id' => $principal->id]);

            $duplicado->status = 'mesclado';
            $duplicado->save();

            $this->registrar('participante.mesclado', $atorId, $principal, [
                'duplicado_id' => (string) $duplicado->id,
            ]);

            return $principal;
        });
    }

    public function desfazer(string $atorId, string $principalId): Participante
    {
        return DB::transaction(function () use ($atorId, $principalId): Participante {
            $principal = $this->buscar($principalId, 'Participante não encontrado.');
            $snapshot = $principal->snapshot_mesclagem;

            if (! is_array($snapshot) || ! isset($snapshot['principal'], $snapshot['duplicado']['id'])) {
                throw new ProblemException('sem_mesclagem', 'Conflito', 409, 'Não há mesclagem para desfazer.');
            }

            $duplicado = Participante::query()->find($snapshot['duplicado']['id']);

            // Restaura os campos do principal.
            $principal->fill($snapshot['principal']);
            $principal->snapshot_mesclagem = null;
            $principal->save();

            // Devolve o duplicado ao estado anterior, repoe seus vinculos e
            // restaura os campos do principal.
            if ($duplicado instanceof Participante) {
                $duplicado->status = $snapshot['duplicado']['status'] ?? 'ativo';
                $duplicado->save();

                $vinculosMovidos = is_array($snapshot['vinculos_movidos'] ?? null)
                    ? $snapshot['vinculos_movidos']
                    : [];

                if ($vinculosMovidos !== []) {
                    ResponsavelParticipante::query()
                        ->whereIn('id', $vinculosMovidos)
                        ->update(['participante_id' => $duplicado->id]);
                }
            }

            $this->registrar('participante.mesclagem_desfeita', $atorId, $principal, [
                'duplicado_id' => (string) $snapshot['duplicado']['id'],
            ]);

            return $principal;
        });
    }

    private function buscar(string $id, string $mensagem): Participante
    {
        return Participante::query()->find($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, $mensagem);
    }

    /**
     * @param  array<string, mixed>  $metadados
     */
    private function registrar(string $acao, string $atorId, Participante $principal, array $metadados): void
    {
        $this->auditoria->registrar([
            'acao' => $acao,
            'recurso' => 'participante',
            'ator_id' => $atorId,
            'origem' => 'api',
            'escopo' => null,
            'motivo' => null,
            'metadados_minimizados' => [
                'participante_id' => (string) $principal->id,
                ...$metadados,
            ],
            'ocorrido_em' => now(),
        ]);
    }
}
