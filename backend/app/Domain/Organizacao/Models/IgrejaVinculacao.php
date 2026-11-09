<?php

namespace App\Domain\Organizacao\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IgrejaVinculacao extends Model
{
    use HasUlids;

    protected $table = 'igreja_vinculacao';

    protected $fillable = ['igreja_id', 'setor_id', 'inicio', 'fim'];

    protected $casts = [
        'inicio' => 'date',
        'fim' => 'date',
    ];

    /** @return BelongsTo<Igreja, $this> */
    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    /** @return BelongsTo<Setor, $this> */
    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }
}
