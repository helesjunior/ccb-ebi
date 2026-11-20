<?php

namespace App\Domain\Escalas\Models;

use App\Domain\Escalas\Database\Factories\SolicitacaoTrocaEscalaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de troca de um membro da escala (RF-07): o substituto sugerido e
 * opcional na abertura; quem decide e a coordenadora chefe do EBI.
 */
class SolicitacaoTrocaEscala extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_RECUSADA = 'recusada';

    public const STATUS_CANCELADA = 'cancelada';

    /** @use HasFactory<SolicitacaoTrocaEscalaFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'solicitacao_troca_escala';

    protected $fillable = [
        'escala_membro_id', 'solicitante_id', 'motivo', 'status',
        'substituto_membro_id', 'decidido_por', 'decidido_em', 'motivo_decisao',
    ];

    protected $casts = [
        'decidido_em' => 'immutable_datetime',
    ];

    /** @return BelongsTo<EscalaMembro, $this> */
    public function membro(): BelongsTo
    {
        return $this->belongsTo(EscalaMembro::class, 'escala_membro_id');
    }

    protected static function newFactory(): SolicitacaoTrocaEscalaFactory
    {
        return SolicitacaoTrocaEscalaFactory::new();
    }
}
