<?php

namespace App\Domain\Auditoria\Database\Factories;

use App\Domain\Auditoria\Models\EventoAuditoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoAuditoria>
 */
class EventoAuditoriaFactory extends Factory
{
    protected $model = EventoAuditoria::class;

    public function definition(): array
    {
        return [
            'sequencia' => 1,
            'hash_anterior' => null,
            'hash_encadeado' => hash('sha256', (string) fake()->unique()->uuid()),
            'ator_id' => '1',
            'acao' => 'probe',
            'recurso' => 'probe',
            'escopo' => null,
            'origem' => 'web',
            'motivo' => null,
            'metadados_minimizados' => null,
            'ocorrido_em' => now(),
        ];
    }
}
