<?php

namespace App\Domain\Auditoria\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Auditoria\Support\SerializacaoCanonica;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegistradorAuditoria implements RegistrarEvento
{
    public function registrar(array $evento): EventoAuditoria
    {
        return DB::transaction(function () use ($evento): EventoAuditoria {
            // shortcut: lockForUpdate serializa leitores concorrentes quando já
            // há eventos (o caso de operação). O primeiro evento concorrente ainda
            // pode colidir em `sequencia` (unique) e falhar — o lock não trava uma
            // tabela vazia. Sem fork silencioso da cadeia; resolver com advisory
            // lock quando houver escrita de negócio concorrente (E02+).
            $ultimo = EventoAuditoria::query()
                ->orderByDesc('sequencia')
                ->lockForUpdate()
                ->first();

            $sequencia = ($ultimo->sequencia ?? 0) + 1;
            $hashAnterior = $ultimo?->hash_encadeado;

            $payload = [
                'sequencia' => $sequencia,
                'hash_anterior' => $hashAnterior,
                'ator_id' => $evento['ator_id'] ?? null,
                'acao' => $evento['acao'],
                'recurso' => $evento['recurso'],
                'escopo' => $evento['escopo'] ?? null,
                'origem' => $evento['origem'],
                'motivo' => $evento['motivo'] ?? null,
                'metadados_minimizados' => $evento['metadados_minimizados'] ?? null,
                'ocorrido_em' => $evento['ocorrido_em'] ?? now(),
            ];

            $hash = $this->calcularHash($hashAnterior, $sequencia, $payload);

            return EventoAuditoria::query()->create([...$payload, 'hash_encadeado' => $hash]);
        });
    }

    public function verificarIntegridade(): void
    {
        $anterior = null;

        foreach (EventoAuditoria::query()->orderBy('sequencia')->cursor() as $evento) {
            $payload = [
                'sequencia' => $evento->sequencia,
                'hash_anterior' => $anterior,
                'ator_id' => $evento->ator_id,
                'acao' => $evento->acao,
                'recurso' => $evento->recurso,
                'escopo' => $evento->escopo,
                'origem' => $evento->origem,
                'motivo' => $evento->motivo,
                'metadados_minimizados' => $evento->metadados_minimizados,
                'ocorrido_em' => $evento->ocorrido_em,
            ];

            $esperado = $this->calcularHash($anterior, $evento->sequencia, $payload);

            if (! hash_equals($esperado, $evento->hash_encadeado)) {
                throw new RuntimeException("Cadeia de auditoria violada no evento {$evento->id}.");
            }

            $anterior = $evento->hash_encadeado;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function calcularHash(?string $hashAnterior, int $sequencia, array $payload): string
    {
        return hash('sha256', $hashAnterior.'|'.$sequencia.'|'.SerializacaoCanonica::normalizar($payload));
    }
}
