<?php

namespace App\Domain\DadosSensiveis\Database\Factories;

use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DadoSensivelParticipante> */
class DadoSensivelParticipanteFactory extends Factory
{
    protected $model = DadoSensivelParticipante::class;

    public function definition(): array
    {
        return [
            'participante_id' => null,
            'tipo' => 'laudo',
            'conteudo_cifrado' => 'cifrado-de-teste',
            'referencia_segura' => null,
            'finalidade' => 'excecao_etaria',
            'base_legal' => 'consentimento',
            'controle_acesso' => 'coordenador',
            'prazo_retencao' => null,
            'expurgado_em' => null,
        ];
    }
}
