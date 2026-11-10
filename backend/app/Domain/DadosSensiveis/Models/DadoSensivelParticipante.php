<?php

namespace App\Domain\DadosSensiveis\Models;

use App\Domain\DadosSensiveis\Database\Factories\DadoSensivelParticipanteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DadoSensivelParticipante extends Model
{
    /** @use HasFactory<DadoSensivelParticipanteFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'dado_sensivel_participante';

    protected $guarded = [];

    protected $casts = [
        'prazo_retencao' => 'date',
        'expurgado_em' => 'datetime',
    ];

    protected $hidden = ['conteudo_cifrado']; // nunca serializar conteúdo sensível

    protected static function newFactory(): DadoSensivelParticipanteFactory
    {
        return DadoSensivelParticipanteFactory::new();
    }

    /**
     * @param  Builder<DadoSensivelParticipante>  $q
     * @return Builder<DadoSensivelParticipante>
     */
    public function scopeExpiraveis(Builder $q, \DateTimeInterface $ref): Builder
    {
        return $q->whereNotNull('prazo_retencao')
            ->where('prazo_retencao', '<=', $ref)
            ->whereNull('expurgado_em');
    }
}
