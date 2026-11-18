<?php

namespace App\Domain\Sessoes\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SessaoCorrecao extends Model
{
    use HasUlids;

    protected $table = 'sessao_correcao';

    protected $fillable = ['sessao_id', 'autor_id', 'motivo', 'antes', 'depois', 'ocorrido_em'];

    protected $casts = ['antes' => 'array', 'depois' => 'array', 'ocorrido_em' => 'datetime'];
}
