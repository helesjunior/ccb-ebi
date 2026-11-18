<?php

namespace App\Domain\Sessoes\Database\Factories;

use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sessao> */
class SessaoFactory extends Factory
{
    protected $model = Sessao::class;

    public function definition(): array
    {
        return [
            'ebi_id' => Ebi::factory(),
            'igreja_id' => Igreja::factory(),
            'inicio_previsto' => now()->addDay()->setTime(10, 0),
            'fim_previsto' => now()->addDay()->setTime(11, 30),
            'status' => Sessao::STATUS_PREVISTA,
            'realizada' => null,
        ];
    }
}
