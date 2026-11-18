<?php

namespace App\Domain\Sessoes\Database\Factories;

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Presenca> */
class PresencaFactory extends Factory
{
    protected $model = Presenca::class;

    public function definition(): array
    {
        return [
            'sessao_id' => Sessao::factory(),
            'participante_id' => Participante::factory(),
            'registrado_por' => Usuario::factory(),
            'registrado_em' => now(),
            'origem' => Presenca::ORIGEM_WEB,
            'status' => Presenca::STATUS_VALIDA,
        ];
    }
}
