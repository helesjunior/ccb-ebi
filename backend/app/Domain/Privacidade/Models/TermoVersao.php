<?php

namespace App\Domain\Privacidade\Models;

use App\Domain\Privacidade\Database\Factories\TermoVersaoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermoVersao extends Model
{
    /** @use HasFactory<TermoVersaoFactory> */
    use HasFactory;

    use HasUlids;

    public const FINALIDADE_CADASTRO = 'cadastro';

    public const FINALIDADE_NOTIFICACOES = 'notificacoes';

    protected $table = 'termo_versao';

    protected $fillable = [
        'finalidade',
        'base_legal',
        'versao',
        'texto',
        'texto_hash',
        'vigente_em',
        'encerrado_em',
    ];

    protected $casts = [
        'vigente_em' => 'datetime',
        'encerrado_em' => 'datetime',
    ];

    /**
     * @param  Builder<TermoVersao>  $query
     * @return Builder<TermoVersao>
     */
    public function scopeVigente(Builder $query, string $finalidade): Builder
    {
        return $query
            ->where('finalidade', $finalidade)
            ->where('vigente_em', '<=', now())
            ->whereNull('encerrado_em');
    }

    protected static function newFactory(): TermoVersaoFactory
    {
        return TermoVersaoFactory::new();
    }
}
