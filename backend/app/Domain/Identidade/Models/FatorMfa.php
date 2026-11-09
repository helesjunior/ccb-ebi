<?php

namespace App\Domain\Identidade\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FatorMfa extends Model
{
    use HasUlids;

    public const TIPO_TOTP = 'totp';

    public const TIPO_EMAIL = 'email';

    protected $table = 'fator_mfa';

    protected $fillable = ['usuario_id', 'tipo', 'segredo_cifrado', 'confirmado_em'];

    protected $casts = [
        'segredo_cifrado' => 'encrypted',
        'confirmado_em' => 'datetime',
    ];

    protected $hidden = ['segredo_cifrado'];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
