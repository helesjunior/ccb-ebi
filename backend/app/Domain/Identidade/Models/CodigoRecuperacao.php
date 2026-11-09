<?php

namespace App\Domain\Identidade\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodigoRecuperacao extends Model
{
    use HasUlids;

    protected $table = 'codigo_recuperacao';

    protected $fillable = ['usuario_id', 'hash', 'usado_em'];

    protected $casts = [
        'usado_em' => 'datetime',
    ];

    protected $hidden = ['hash'];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
