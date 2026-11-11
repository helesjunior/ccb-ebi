<?php

namespace App\Support\Arquivo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Arquivo enviado ao sistema (E08). O ciclo de vida e dirigido pelo pipeline:
 * `pendente` (em quarentena) -> `limpo` (no bucket cifrado) | `infectado` |
 * `invalido` (tipo real recusado). Nunca e servido diretamente; o download sai
 * por URL assinada de curta duracao.
 */
class Arquivo extends Model
{
    use HasUlids;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_LIMPO = 'limpo';

    public const STATUS_INFECTADO = 'infectado';

    public const STATUS_INVALIDO = 'invalido';

    protected $table = 'arquivo';

    protected $fillable = [
        'finalidade',
        'nome_original',
        'mime',
        'tamanho',
        'sha256',
        'chave_storage',
        'bucket',
        'cifrado',
        'status_antivirus',
        'enviado_por',
    ];

    protected $casts = [
        'cifrado' => 'boolean',
        'tamanho' => 'integer',
    ];
}
