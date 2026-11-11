<?php

namespace App\Domain\Participantes\Models;

use App\Domain\Participantes\Database\Factories\SolicitacaoVinculoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log append-only de pedidos de vinculo (RF-03). Nao gateia acesso: o estado
 * corrente vive em `responsavel_participante.validacao` (CA-04).
 */
class SolicitacaoVinculo extends Model
{
    /** @use HasFactory<SolicitacaoVinculoFactory> */
    use HasFactory;

    use HasUlids;

    public const TIPO_SOLICITACAO = 'solicitacao';

    public const TIPO_CONTESTACAO = 'contestacao';

    public const TIPO_REVOGACAO = 'revogacao';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_RECUSADA = 'recusada';

    protected $table = 'solicitacao_vinculo';

    protected $fillable = [
        'usuario_id',
        'participante_id',
        'responsavel_participante_id',
        'tipo',
        'status',
        'decidido_por',
        'decidido_em',
        'motivo',
    ];

    protected $casts = [
        'decidido_em' => 'datetime',
    ];

    /** @return BelongsTo<Participante, $this> */
    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    protected static function newFactory(): SolicitacaoVinculoFactory
    {
        return SolicitacaoVinculoFactory::new();
    }
}
