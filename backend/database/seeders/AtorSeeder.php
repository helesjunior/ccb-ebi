<?php

namespace Database\Seeders;

use App\Domain\Identidade\Models\Ator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AtorSeeder extends Seeder
{
    public function run(): void
    {
        Ator::query()->updateOrCreate(
            ['email' => 'coordenadora@ebi.test'],
            [
                'nome' => 'Coordenadora EBI (fictícia)',
                'papel' => 'coordenadora',
                'senha' => Hash::make('senha'),
            ],
        );
    }
}
