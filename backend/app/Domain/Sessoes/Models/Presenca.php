<?php

namespace App\Domain\Sessoes\Models;

use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Database\Factories\PresencaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presenca extends Model
{
    public const STATUS_VALIDA = 'valida';

    public const STATUS_ESTORNADA = 'estornada';

    public const ORIGEM_WEB = 'web';

    public const ORIGEM_APP = 'app';

    public const ORIGEM_CONTINGENCIA = 'contingencia';

    /** @use HasFactory<PresencaFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'presenca';

    protected $fillable = ['sessao_id', 'participante_id', 'registrado_por', 'registrado_em', 'origem', 'status', 'estornado_por', 'estornado_em', 'motivo_estorno'];

    protected $casts = ['registrado_em' => 'datetime', 'estornado_em' => 'datetime'];

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id');
    }

    protected static function newFactory(): PresencaFactory
    {
        return PresencaFactory::new();
    }
}
