<?php

namespace App\Domain\Sessoes\Models;

use App\Domain\Sessoes\Database\Factories\SessaoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sessao extends Model
{
    public const STATUS_PREVISTA = 'prevista';

    public const STATUS_ABERTA = 'aberta';

    public const STATUS_ENCERRADA = 'encerrada';

    public const STATUS_CANCELADA = 'cancelada';

    /** @use HasFactory<SessaoFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'sessao';

    protected $fillable = ['ebi_id', 'igreja_id', 'agenda_ebi_id', 'inicio_previsto', 'fim_previsto', 'inicio_real', 'fim_real', 'tema', 'passagem_biblica', 'status', 'realizada', 'motivo_cancelamento', 'cancelada_por', 'auxiliares_escalados'];

    protected $casts = [
        'inicio_previsto' => 'datetime', 'fim_previsto' => 'datetime', 'inicio_real' => 'datetime', 'fim_real' => 'datetime',
        'realizada' => 'boolean', 'auxiliares_escalados' => 'integer',
    ];

    /** @return HasMany<Presenca, $this> */
    public function presencas(): HasMany
    {
        return $this->hasMany(Presenca::class);
    }

    /** @return HasMany<Presenca, $this> */
    public function presencasValidas(): HasMany
    {
        return $this->presencas()->where('status', Presenca::STATUS_VALIDA);
    }

    /** @return HasMany<SessaoCorrecao, $this> */
    public function correcoes(): HasMany
    {
        return $this->hasMany(SessaoCorrecao::class);
    }

    protected static function newFactory(): SessaoFactory
    {
        return SessaoFactory::new();
    }
}
