<?php

namespace App\Domain\Escalas\Models;

use App\Domain\Escalas\Database\Factories\EscalaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Escala de uma sessao (RF-07): quantos participantes sao esperados, quantos
 * cada auxiliar atende e quem compoe a equipe. Nasce `prevista` e so vira
 * `aprovada` pela coordenadora chefe (ou por quem a substitui no EBI).
 */
class Escala extends Model
{
    public const STATUS_PREVISTA = 'prevista';

    public const STATUS_APROVADA = 'aprovada';

    /** @use HasFactory<EscalaFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'escala';

    protected $fillable = [
        'sessao_id', 'equipe_id', 'ebi_id', 'igreja_id', 'estimativa_participantes',
        'participantes_por_auxiliar', 'status', 'aprovado_por', 'aprovado_em', 'criado_por',
    ];

    protected $casts = [
        'estimativa_participantes' => 'integer',
        'participantes_por_auxiliar' => 'integer',
        'aprovado_em' => 'immutable_datetime',
    ];

    /** @return HasMany<EscalaMembro, $this> */
    public function membros(): HasMany
    {
        return $this->hasMany(EscalaMembro::class);
    }

    /** @return BelongsTo<Equipe, $this> */
    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    /** @return HasMany<VagaEscala, $this> */
    public function vagas(): HasMany
    {
        return $this->hasMany(VagaEscala::class);
    }

    protected static function newFactory(): EscalaFactory
    {
        return EscalaFactory::new();
    }
}
