<?php

namespace App\Domain\Privacidade\Models;

use App\Domain\Privacidade\Database\Factories\ManifestacaoPrivacidadeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestacaoPrivacidade extends Model
{
    /** @use HasFactory<ManifestacaoPrivacidadeFactory> */
    use HasFactory;

    use HasUlids;

    public const DECISAO_ACEITO = 'aceito';

    public const DECISAO_RECUSADO = 'recusado';

    public const DECISAO_RETIRADO = 'retirado';

    public const ORIGEM_PROPRIO = 'proprio';

    public const ORIGEM_ASSISTIDO = 'assistido';

    protected $table = 'manifestacao_privacidade';

    protected $fillable = [
        'usuario_id',
        'participante_id',
        'termo_versao_id',
        'decisao',
        'origem',
        'evidencia',
        'ocorrido_em',
    ];

    protected $casts = [
        'ocorrido_em' => 'datetime',
    ];

    /** @return BelongsTo<TermoVersao, $this> */
    public function termoVersao(): BelongsTo
    {
        return $this->belongsTo(TermoVersao::class, 'termo_versao_id');
    }

    protected static function newFactory(): ManifestacaoPrivacidadeFactory
    {
        return ManifestacaoPrivacidadeFactory::new();
    }
}
