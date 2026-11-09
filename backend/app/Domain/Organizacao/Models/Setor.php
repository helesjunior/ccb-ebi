<?php

namespace App\Domain\Organizacao\Models;

use App\Domain\Organizacao\Database\Factories\SetorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setor extends Model
{
    /** @use HasFactory<SetorFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'setores';

    protected $fillable = ['regional_id', 'nome', 'codigo', 'status'];

    /** @return BelongsTo<Regional, $this> */
    public function regional(): BelongsTo
    {
        return $this->belongsTo(Regional::class);
    }

    protected static function newFactory(): SetorFactory
    {
        return SetorFactory::new();
    }
}
