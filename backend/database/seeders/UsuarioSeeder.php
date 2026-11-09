<?php

namespace Database\Seeders;

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        Usuario::query()->updateOrCreate(
            ['email_normalizado' => 'coordenadora@ebi.test'],
            [
                'nome' => 'Coordenadora EBI (fictícia)',
                'email_verificado_em' => now(),
                'senha' => Hash::make('senha'),
                'status' => 'ativo',
            ],
        );
    }
}
