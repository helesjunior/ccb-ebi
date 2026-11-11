<?php

namespace App\Domain\Participantes\Models;

use App\Domain\Participantes\Database\Factories\ExcecaoEtariaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Excecao inclusiva (FL-06, ADR-0012). Sem limite de idade; vale ate ser
 * revogada ou expirar (`vigente_ate`). Nao muda o calculo de elegibilidade —
 * o bloqueio de <3 anos (CA-19) prevalece sobre a excecao.
 */
class ExcecaoEtaria extends Model
{
    /** @use HasFactory<ExcecaoEtariaFactory> */
    use HasFactory;

    use HasUlids;

    public const STATUS_SOLICITADA = 'solicitada';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_RECUSADA = 'recusada';

    public const STATUS_SUSPENSA = 'suspensa';

    public const STATUS_REVOGADA = 'revogada';

    public const STATUS_EXPIRADA = 'expirada';

    protected $table = 'excecao_etaria';

    protected $fillable = [
        'participante_id',
        'tipo_necessidade',
        'apoios_necessarios',
        'justificativa_funcional',
        'status',
        'solicitado_por',
        'decidido_por',
        'responsavel_concordante_id',
        'decidido_em',
        'vigente_ate',
        'revisao_prevista_em',
        'revogado_em',
        'motivo',
    ];

    protected $casts = [
        'decidido_em' => 'datetime',
        'vigente_ate' => 'date',
        'revisao_prevista_em' => 'date',
        'revogado_em' => 'datetime',
    ];

    /** @return BelongsTo<Participante, $this> */
    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    protected static function newFactory(): ExcecaoEtariaFactory
    {
        return ExcecaoEtariaFactory::new();
    }
}
