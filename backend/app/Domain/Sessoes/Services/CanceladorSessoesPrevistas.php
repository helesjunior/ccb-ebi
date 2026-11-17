<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Sessoes\Contracts\CancelarSessoesPrevistas;
use App\Domain\Sessoes\Models\Sessao;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

class CanceladorSessoesPrevistas implements CancelarSessoesPrevistas
{
    public function __construct(
        private readonly ConsultarEbi $ebis,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function naData(string $ebiId, string $dataIso, string $motivo, string $autorId): int
    {
        $ebi = $this->ebis->dados($ebiId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);

        return DB::transaction(function () use ($ebiId, $dataIso, $motivo, $autorId, $ebi): int {
            $canceladas = 0;
            $sessoes = Sessao::query()->where('ebi_id', $ebiId)->where('status', Sessao::STATUS_PREVISTA)->lockForUpdate()->get();
            foreach ($sessoes as $sessao) {
                if ($sessao->inicio_previsto->copy()->setTimezone($ebi->timezone)->toDateString() !== $dataIso) {
                    continue;
                }

                $sessao->update([
                    'status' => Sessao::STATUS_CANCELADA,
                    'motivo_cancelamento' => "agenda: {$motivo}",
                    'cancelada_por' => $autorId,
                ]);
                $this->auditoria->registrar([
                    'acao' => 'sessao.cancelada',
                    'recurso' => 'sessao',
                    'ator_id' => $autorId,
                    'origem' => 'api',
                    'motivo' => "agenda: {$motivo}",
                    'escopo' => "ebi:{$ebiId}",
                    'metadados_minimizados' => ['sessao_id' => (string) $sessao->id],
                ]);
                $canceladas++;
            }

            return $canceladas;
        });
    }
}
