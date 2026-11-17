<?php

namespace App\Domain\Agenda\Models;

use App\Domain\Agenda\Database\Factories\AgendaEbiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaEbi extends Model
{
    /** @use HasFactory<AgendaEbiFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'agenda_ebi';

    protected $fillable = ['ebi_id', 'dia_semana', 'hora_inicio', 'hora_fim', 'inicio_vigencia', 'fim_vigencia'];

    protected $casts = ['inicio_vigencia' => 'date', 'fim_vigencia' => 'date'];

    protected static function newFactory(): AgendaEbiFactory
    {
        return AgendaEbiFactory::new();
    }
}
