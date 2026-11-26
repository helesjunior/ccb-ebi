<?php

namespace App\Domain\Escalas\Models;

use App\Domain\Escalas\Database\Factories\VagaEscalaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vaga aberta numa escala (E07, evolucao): a auxiliar faltou (substituicao) ou
 * a dona quer mais gente (acrescimo). Preenche e a dona, escolhendo uma das
 * ofertas; a chefe so fica sabendo pelo painel.
 *
 * Sem FK em `substituido_usuario_id`: o membro original pode ser apagado na
 * retirada e a vaga continua dizendo quem foi substituido.
 */
class VagaEscala extends Model
{
    public const STATUS_ABERTA = 'aberta';

    public const STATUS_PREENCHIDA = 'preenchida';

    public const STATUS_CANCELADA = 'cancelada';

    public const MOTIVO_GESTAO = 'cancelada_pela_gestao';

    public const MOTIVO_RETIRADO = 'membro_retirado';

    public const MOTIVO_FUNCAO = 'funcao_alterada';

    public const MOTIVO_TROCADO = 'membro_trocado';

    public const MOTIVO_INDISPONIVEL = 'original_indisponivel';

    /** @use HasFactory<VagaEscalaFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'vaga_escala';

    protected $fillable = [
        'escala_id', 'funcao', 'substitui_membro_id', 'substituido_usuario_id', 'observacao',
        'status', 'aberta_por', 'encerrada_por', 'encerrada_em', 'motivo_encerramento',
    ];

    protected $casts = [
        'encerrada_em' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Escala, $this> */
    public function escala(): BelongsTo
    {
        return $this->belongsTo(Escala::class);
    }

    /** @return BelongsTo<EscalaMembro, $this> */
    public function substituido(): BelongsTo
    {
        return $this->belongsTo(EscalaMembro::class, 'substitui_membro_id');
    }

    /** @return HasMany<OfertaVagaEscala, $this> */
    public function ofertas(): HasMany
    {
        return $this->hasMany(OfertaVagaEscala::class, 'vaga_id');
    }

    public function estaAberta(): bool
    {
        return $this->status === self::STATUS_ABERTA;
    }

    protected static function newFactory(): VagaEscalaFactory
    {
        return VagaEscalaFactory::new();
    }
}
