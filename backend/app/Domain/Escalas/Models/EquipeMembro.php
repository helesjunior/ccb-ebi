<?php

namespace App\Domain\Escalas\Models;

use App\Domain\Escalas\Database\Factories\EquipeMembroFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auxiliar no quadro de uma equipe. A vigencia do papel e lida do vinculo na
 * hora (o quadro guarda a intencao da coordenadora, nao a autorizacao).
 */
class EquipeMembro extends Model
{
    /** @use HasFactory<EquipeMembroFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'equipe_membro';

    protected $fillable = ['equipe_id', 'usuario_id', 'incluido_por'];

    /** @return BelongsTo<Equipe, $this> */
    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    protected static function newFactory(): EquipeMembroFactory
    {
        return EquipeMembroFactory::new();
    }
}
