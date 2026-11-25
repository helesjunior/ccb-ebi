<?php

namespace App\Domain\Escalas\Models;

use App\Domain\Escalas\Database\Factories\EscalaMembroFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pessoa escalada para uma sessao, com sua funcao (`coordenadora` ou
 * `auxiliar`). O membro substituido continua na linha para o historico e a
 * presenca ja registrada nao se perde.
 */
class EscalaMembro extends Model
{
    public const FUNCAO_COORDENADORA = 'coordenadora';

    public const FUNCAO_AUXILIAR = 'auxiliar';

    public const STATUS_ESCALADO = 'escalado';

    public const STATUS_CONFIRMADO = 'confirmado';

    public const STATUS_TROCA_SOLICITADA = 'troca_solicitada';

    public const STATUS_SUBSTITUIDO = 'substituido';

    /**
     * Papeis aceitos por funcao da escala. Constante unica do modulo: escala,
     * equipe, troca e vaga decidem elegibilidade pela mesma tabela.
     *
     * @var array<string, array<int, string>>
     */
    public const PAPEIS_POR_FUNCAO = [
        self::FUNCAO_COORDENADORA => ['coordenadora', 'coordenadora_chefe'],
        self::FUNCAO_AUXILIAR => ['auxiliar', 'coordenadora', 'coordenadora_chefe'],
    ];

    /** @use HasFactory<EscalaMembroFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'escala_membro';

    protected $fillable = [
        'escala_id', 'usuario_id', 'funcao', 'status', 'confirmado_em',
        'presente', 'presenca_registrada_por', 'presenca_registrada_em', 'substitui_membro_id',
    ];

    protected $casts = [
        'confirmado_em' => 'immutable_datetime',
        'presenca_registrada_em' => 'immutable_datetime',
        'presente' => 'boolean',
    ];

    /** @return BelongsTo<Escala, $this> */
    public function escala(): BelongsTo
    {
        return $this->belongsTo(Escala::class);
    }

    public function sessaoId(): string
    {
        /** @var Escala $escala */
        $escala = $this->escala;

        return (string) $escala->sessao_id;
    }

    /** @return HasMany<SolicitacaoTrocaEscala, $this> */
    public function trocas(): HasMany
    {
        return $this->hasMany(SolicitacaoTrocaEscala::class, 'escala_membro_id');
    }

    /**
     * Membros que ainda ocupam a vaga (quem foi substituido sai da escala).
     *
     * @param  Builder<EscalaMembro>  $query
     * @return Builder<EscalaMembro>
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', '<>', self::STATUS_SUBSTITUIDO);
    }

    /** Status a que o membro volta quando uma troca e cancelada ou recusada. */
    public function statusAnterior(): string
    {
        return $this->confirmado_em !== null ? self::STATUS_CONFIRMADO : self::STATUS_ESCALADO;
    }

    protected static function newFactory(): EscalaMembroFactory
    {
        return EscalaMembroFactory::new();
    }
}
