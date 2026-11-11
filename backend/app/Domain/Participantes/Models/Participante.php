<?php

namespace App\Domain\Participantes\Models;

use App\Domain\Participantes\Database\Factories\ParticipanteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participante extends Model
{
    /** @use HasFactory<ParticipanteFactory> */
    use HasFactory;

    use HasUlids;

    public const ORIGEM_RESPONSAVEL = 'responsavel';

    public const ORIGEM_ASSISTIDO = 'assistido';

    public const STATUS_ATIVO = 'ativo';

    protected $table = 'participante';

    protected $fillable = [
        'matricula',
        'nome',
        'nome_social',
        'data_nascimento',
        'status',
        'igreja_referencia_id',
        'origem_cadastro',
        'cadastrado_por',
        'evidencia_consentimento_id',
        'snapshot_mesclagem',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'snapshot_mesclagem' => 'array',
    ];

    /** @return HasMany<ResponsavelParticipante, $this> */
    public function responsaveis(): HasMany
    {
        return $this->hasMany(ResponsavelParticipante::class);
    }

    /** @return HasMany<ExcecaoEtaria, $this> */
    public function excecoes(): HasMany
    {
        return $this->hasMany(ExcecaoEtaria::class);
    }

    public function excecaoVigente(): ?ExcecaoEtaria
    {
        return $this->excecoes()
            ->where('status', ExcecaoEtaria::STATUS_APROVADA)
            ->orderByDesc('decidido_em')
            ->first();
    }

    protected static function newFactory(): ParticipanteFactory
    {
        return ParticipanteFactory::new();
    }
}
