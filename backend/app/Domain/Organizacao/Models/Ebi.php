<?php

namespace App\Domain\Organizacao\Models;

use App\Domain\Organizacao\Database\Factories\EbiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ebi extends Model
{
    /** @use HasFactory<EbiFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'ebis';

    protected $fillable = ['igreja_id', 'nome', 'participantes_por_auxiliar', 'status'];

    protected $attributes = [
        'participantes_por_auxiliar' => 4,
    ];

    /** @return BelongsTo<Igreja, $this> */
    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }

    protected static function newFactory(): EbiFactory
    {
        return EbiFactory::new();
    }
}
