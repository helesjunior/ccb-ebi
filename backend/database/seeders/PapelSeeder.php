<?php

namespace Database\Seeders;

use App\Domain\Identidade\Models\Papel;
use Illuminate\Database\Seeder;

class PapelSeeder extends Seeder
{
    /**
     * Catalogo de papeis derivado da matriz de autorizacao e do ADR-0017.
     * Ministerios sao papeis distintos por nivel; super_admin ignora escopo.
     *
     * @var array<string, string>
     */
    private const PAPEIS = [
        'super_admin' => 'Super administrador (ignora escopo territorial)',
        'adm_regional' => 'Administrador regional',
        'adm_setor' => 'Administrador de setor',
        'adm_igreja' => 'Administrador de igreja',
        'ministerio_central' => 'Ministerio (nivel central)',
        'ministerio_regional' => 'Ministerio (nivel regional)',
        'ministerio_setorial' => 'Ministerio (nivel setorial)',
        'ministerio_local' => 'Ministerio (nivel local)',
        'coordenadora' => 'Coordenadora',
        'auxiliar' => 'Auxiliar',
        'responsavel' => 'Responsavel',
    ];

    public function run(): void
    {
        foreach (self::PAPEIS as $codigo => $descricao) {
            Papel::query()->updateOrCreate(['codigo' => $codigo], ['descricao' => $descricao]);
        }
    }
}
