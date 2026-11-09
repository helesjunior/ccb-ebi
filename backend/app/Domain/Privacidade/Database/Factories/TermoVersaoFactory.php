<?php

namespace App\Domain\Privacidade\Database\Factories;

use App\Domain\Privacidade\Models\TermoVersao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermoVersao>
 */
class TermoVersaoFactory extends Factory
{
    protected $model = TermoVersao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finalidade' => 'cadastro',
            'base_legal' => 'consentimento',
            'versao' => '2026-11',
            'texto' => '<rascunho>',
            'texto_hash' => hash('sha256', '<rascunho>'),
            'vigente_em' => now(),
            'encerrado_em' => null,
        ];
    }

    public function encerrado(): static
    {
        return $this->state(['encerrado_em' => now()->subDay()]);
    }
}
