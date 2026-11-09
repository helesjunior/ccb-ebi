<?php

namespace App\Domain\Organizacao\Database\Factories;

use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ebi>
 */
class EbiFactory extends Factory
{
    protected $model = Ebi::class;

    public function definition(): array
    {
        return [
            'igreja_id' => Igreja::factory(),
            'nome' => 'EBI '.fake()->city(),
            'participantes_por_auxiliar' => 4,
            'status' => 'ativo',
        ];
    }
}
