<?php

namespace App\Domain\Participantes\Models;

use App\Domain\Participantes\Database\Factories\ResponsavelParticipanteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponsavelParticipante extends Model
{
    /** @use HasFactory<ResponsavelParticipanteFactory> */
    use HasFactory;

    use HasUlids;

    public const VALIDACAO_PENDENTE = 'pendente';

    public const VALIDACAO_APROVADA = 'aprovada';

    public const VALIDACAO_AGUARDANDO_DOCUMENTO = 'aguardando_documento';

    public const VALIDACAO_RECUSADA = 'recusada';

    protected $table = 'responsavel_participante';

    protected $fillable = [
        'usuario_id',
        'nome',
        'telefone',
        'participante_id',
        'tipo_relacao',
        'permissoes',
        'restricoes',
        'status',
        'validacao',
        'conferido_em',
        'conferido_por',
        'inicio',
        'fim',
    ];

    protected $casts = [
        'permissoes' => 'array',
        'conferido_em' => 'datetime',
        'inicio' => 'date',
        'fim' => 'date',
    ];

    /** @return BelongsTo<Participante, $this> */
    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    protected static function newFactory(): ResponsavelParticipanteFactory
    {
        return ResponsavelParticipanteFactory::new();
    }
}
