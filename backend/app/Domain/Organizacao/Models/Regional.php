<?php

namespace App\Domain\Organizacao\Models;

use App\Domain\Organizacao\Database\Factories\RegionalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regional extends Model
{
    /** @use HasFactory<RegionalFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'regionais';

    protected $fillable = ['central_nacional_id', 'nome', 'codigo', 'status'];

    /** @return BelongsTo<CentralNacional, $this> */
    public function centralNacional(): BelongsTo
    {
        return $this->belongsTo(CentralNacional::class);
    }

    /** @return HasMany<Setor, $this> */
    public function setores(): HasMany
    {
        return $this->hasMany(Setor::class);
    }

    protected static function newFactory(): RegionalFactory
    {
        return RegionalFactory::new();
    }
}
