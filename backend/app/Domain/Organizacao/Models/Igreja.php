<?php

namespace App\Domain\Organizacao\Models;

use App\Domain\Organizacao\Database\Factories\IgrejaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Igreja extends Model
{
    /** @use HasFactory<IgrejaFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'igrejas';

    protected $fillable = ['codigo', 'nome', 'endereco', 'latitude', 'longitude', 'timezone', 'status'];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /** @return HasMany<IgrejaVinculacao, $this> */
    public function vinculacoes(): HasMany
    {
        return $this->hasMany(IgrejaVinculacao::class);
    }

    /** @return HasOne<IgrejaVinculacao, $this> */
    public function vinculacaoVigente(): HasOne
    {
        return $this->hasOne(IgrejaVinculacao::class)->whereNull('fim');
    }

    /** @return HasMany<Ebi, $this> */
    public function ebis(): HasMany
    {
        return $this->hasMany(Ebi::class);
    }

    protected static function newFactory(): IgrejaFactory
    {
        return IgrejaFactory::new();
    }
}
