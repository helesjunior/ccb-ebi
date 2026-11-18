<?php

namespace App\Domain\Agenda\Models;

use App\Domain\Agenda\Database\Factories\AgendaExcecaoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaExcecao extends Model
{
    public const TIPO_FERIADO = 'feriado';

    public const TIPO_CANCELAMENTO = 'cancelamento';

    public const TIPO_HORARIO_ESPECIAL = 'horario_especial';

    /** @use HasFactory<AgendaExcecaoFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'agenda_excecao';

    protected $fillable = ['ebi_id', 'agenda_ebi_id', 'data', 'tipo', 'hora_inicio', 'hora_fim', 'motivo'];

    protected $casts = ['data' => 'date'];

    protected static function newFactory(): AgendaExcecaoFactory
    {
        return AgendaExcecaoFactory::new();
    }
}
