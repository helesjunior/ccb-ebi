<?php

namespace App\Domain\Organizacao\Models;

use App\Domain\Organizacao\Database\Factories\CentralNacionalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentralNacional extends Model
{
    /** @use HasFactory<CentralNacionalFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'central_nacional';

    protected $fillable = ['nome'];

    protected static function newFactory(): CentralNacionalFactory
    {
        return CentralNacionalFactory::new();
    }

    /** @return HasMany<Regional, $this> */
    public function regionais(): HasMany
    {
        return $this->hasMany(Regional::class);
    }
}
