<?php

namespace App\Domain\Identidade\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessaoLogin extends Model
{
    use HasUlids;

    protected $table = 'sessao_login';

    protected $fillable = ['usuario_id', 'dispositivo', 'criada_em', 'expira_em', 'revogada_em'];

    protected $casts = [
        'criada_em' => 'datetime',
        'expira_em' => 'datetime',
        'revogada_em' => 'datetime',
    ];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function vigente(): bool
    {
        return $this->revogada_em === null && $this->expira_em->isFuture();
    }
}
