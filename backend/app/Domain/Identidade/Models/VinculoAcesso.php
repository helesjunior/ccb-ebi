<?php

namespace App\Domain\Identidade\Models;

use App\Domain\Identidade\Database\Factories\VinculoAcessoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VinculoAcesso extends Model
{
    /** @use HasFactory<VinculoAcessoFactory> */
    use HasFactory;

    use HasUlids;

    public const TIPO_CENTRAL = 'central';

    public const TIPO_REGIONAL = 'regional';

    public const TIPO_SETOR = 'setor';

    public const TIPO_IGREJA = 'igreja';

    public const TIPO_EBI = 'ebi';

    protected $table = 'vinculo_acesso';

    protected $fillable = [
        'usuario_id',
        'papel_id',
        'tipo_escopo',
        'escopo_id',
        'inicio',
        'fim',
        'status',
    ];

    protected $casts = [
        'inicio' => 'date',
        'fim' => 'date',
    ];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** @return BelongsTo<Papel, $this> */
    public function papel(): BelongsTo
    {
        return $this->belongsTo(Papel::class, 'papel_id');
    }

    /**
     * @param  Builder<VinculoAcesso>  $query
     * @return Builder<VinculoAcesso>
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->whereNull('fim');
    }

    protected static function newFactory(): VinculoAcessoFactory
    {
        return VinculoAcessoFactory::new();
    }
}
