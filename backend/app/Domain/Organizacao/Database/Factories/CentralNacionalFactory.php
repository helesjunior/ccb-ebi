<?php

namespace App\Domain\Organizacao\Database\Factories;

use App\Domain\Organizacao\Models\CentralNacional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CentralNacional>
 */
class CentralNacionalFactory extends Factory
{
    protected $model = CentralNacional::class;

    public function definition(): array
    {
        return [
            'nome' => 'Central Nacional',
        ];
    }
}
