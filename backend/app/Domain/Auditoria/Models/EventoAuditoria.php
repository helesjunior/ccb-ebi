<?php

namespace App\Domain\Auditoria\Models;

use App\Domain\Auditoria\Database\Factories\EventoAuditoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EventoAuditoria extends Model
{
    /** @use HasFactory<EventoAuditoriaFactory> */
    use HasFactory;

    protected $table = 'evento_auditoria';

    protected $guarded = [];

    protected $casts = [
        'metadados_minimizados' => 'array',
        'ocorrido_em' => 'datetime',
    ];

    protected static function newFactory(): EventoAuditoriaFactory
    {
        return EventoAuditoriaFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Auditoria é append-only: update proibido.'));
        static::deleting(fn () => throw new RuntimeException('Auditoria é append-only: delete proibido.'));
    }
}
