<?php

namespace App\Domain\Agenda\Database\Factories;

use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Organizacao\Models\Ebi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgendaEbi> */
class AgendaEbiFactory extends Factory
{
    protected $model = AgendaEbi::class;

    public function definition(): array
    {
        return [
            'ebi_id' => Ebi::factory(),
            'dia_semana' => 1,
            'hora_inicio' => '10:00',
            'hora_fim' => '11:30',
            'inicio_vigencia' => now()->subYear()->toDateString(),
            'fim_vigencia' => null,
        ];
    }
}
