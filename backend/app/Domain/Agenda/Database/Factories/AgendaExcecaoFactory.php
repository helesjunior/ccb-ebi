<?php

namespace App\Domain\Agenda\Database\Factories;

use App\Domain\Agenda\Models\AgendaExcecao;
use App\Domain\Organizacao\Models\Ebi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgendaExcecao> */
class AgendaExcecaoFactory extends Factory
{
    protected $model = AgendaExcecao::class;

    public function definition(): array
    {
        return [
            'ebi_id' => Ebi::factory(),
            'tipo' => AgendaExcecao::TIPO_FERIADO,
            'data' => now()->addWeek()->toDateString(),
            'motivo' => 'feriado local',
        ];
    }
}
