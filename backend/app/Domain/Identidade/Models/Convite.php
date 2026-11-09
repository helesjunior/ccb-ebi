<?php

namespace App\Domain\Identidade\Models;

use App\Domain\Identidade\Database\Factories\ConviteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Convite extends Model
{
    /** @use HasFactory<ConviteFactory> */
    use HasFactory;

    use HasUlids;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_ACEITO = 'aceito';

    public const STATUS_CANCELADO = 'cancelado';

    protected $table = 'convite';

    protected $fillable = [
        'email_normalizado',
        'papel_id',
        'tipo_escopo',
        'escopo_id',
        'token_hash',
        'expira_em',
        'convidante_id',
        'aceito_em',
        'cancelado_em',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expira_em' => 'datetime',
        'aceito_em' => 'datetime',
        'cancelado_em' => 'datetime',
    ];

    /** @return BelongsTo<Papel, $this> */
    public function papel(): BelongsTo
    {
        return $this->belongsTo(Papel::class, 'papel_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function convidante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'convidante_id');
    }

    public function pendente(): bool
    {
        return $this->aceito_em === null
            && $this->cancelado_em === null
            && $this->expira_em->isFuture();
    }

    protected static function newFactory(): ConviteFactory
    {
        return ConviteFactory::new();
    }
}
