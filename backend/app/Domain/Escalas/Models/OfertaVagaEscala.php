<?php

namespace App\Domain\Escalas\Models;

use App\Domain\Escalas\Database\Factories\OfertaVagaEscalaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Oferta de uma pessoa para uma vaga aberta (E07, evolucao). A pessoa diz "eu
 * vou"; a dona da escala escolhe. Escolhida, nasce um membro `confirmado`.
 */
class OfertaVagaEscala extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_ACEITA = 'aceita';

    public const STATUS_RETIRADA = 'retirada';

    public const STATUS_DESCARTADA = 'descartada';

    /** @use HasFactory<OfertaVagaEscalaFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'oferta_vaga_escala';

    protected $fillable = [
        'vaga_id', 'usuario_id', 'status', 'membro_criado_id',
    ];

    /** @return BelongsTo<VagaEscala, $this> */
    public function vaga(): BelongsTo
    {
        return $this->belongsTo(VagaEscala::class, 'vaga_id');
    }

    /** @return BelongsTo<EscalaMembro, $this> */
    public function membroCriado(): BelongsTo
    {
        return $this->belongsTo(EscalaMembro::class, 'membro_criado_id');
    }

    protected static function newFactory(): OfertaVagaEscalaFactory
    {
        return OfertaVagaEscalaFactory::new();
    }
}
