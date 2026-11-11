<?php

namespace App\Domain\Participantes\Database\Factories;

use App\Domain\Participantes\Models\Participante;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Participante>
 */
class ParticipanteFactory extends Factory
{
    protected $model = Participante::class;

    public function definition(): array
    {
        return [
            'matricula' => strtoupper(Str::random(9)),
            'nome' => fake()->name(),
            'nome_social' => null,
            'data_nascimento' => fake()->dateTimeBetween('-14 years', '-4 years')->format('Y-m-d'),
            'status' => Participante::STATUS_ATIVO,
            'igreja_referencia_id' => null,
            'origem_cadastro' => Participante::ORIGEM_RESPONSAVEL,
            'cadastrado_por' => null,
            'evidencia_consentimento_id' => null,
        ];
    }
}
