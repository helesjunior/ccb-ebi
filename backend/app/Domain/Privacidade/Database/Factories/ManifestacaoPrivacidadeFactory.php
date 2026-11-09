<?php

namespace App\Domain\Privacidade\Database\Factories;

use App\Domain\Privacidade\Models\ManifestacaoPrivacidade;
use App\Domain\Privacidade\Models\TermoVersao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManifestacaoPrivacidade>
 */
class ManifestacaoPrivacidadeFactory extends Factory
{
    protected $model = ManifestacaoPrivacidade::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => null,
            'participante_id' => null,
            'termo_versao_id' => TermoVersao::factory(),
            'decisao' => ManifestacaoPrivacidade::DECISAO_ACEITO,
            'origem' => ManifestacaoPrivacidade::ORIGEM_PROPRIO,
            'evidencia' => 'aceite digital ip=127.0.0.1 ua=phpunit',
            'ocorrido_em' => now(),
        ];
    }
}
