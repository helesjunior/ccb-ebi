<?php

namespace App\Domain\Escalas\Models;

use App\Domain\Escalas\Database\Factories\EquipeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equipe de uma coordenadora (E07, evolucao): o quadro de auxiliares que ela
 * mesma monta e mantem. Uma escala nasce a partir da equipe de uma
 * coordenadora; a mesma pessoa pode estar em varias equipes.
 */
class Equipe extends Model
{
    /** @use HasFactory<EquipeFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'equipe';

    protected $fillable = [
        'ebi_id', 'igreja_id', 'coordenadora_id', 'criado_por',
    ];

    /** @return HasMany<EquipeMembro, $this> */
    public function membros(): HasMany
    {
        return $this->hasMany(EquipeMembro::class);
    }

    /** As factories moram no modulo, nao em `Database\Factories` (mesmo padrao da escala). */
    protected static function newFactory(): EquipeFactory
    {
        return EquipeFactory::new();
    }
}
