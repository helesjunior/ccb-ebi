<?php

namespace Database\Seeders;

use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Database\Seeder;

/**
 * Hierarquia organizacional fictícia para desenvolvimento (Brasília).
 * Idempotente por código: pode rodar repetidas vezes.
 */
class OrganizacaoSeeder extends Seeder
{
    public function run(): void
    {
        $central = CentralNacional::query()->firstOrCreate(['nome' => 'Central (Brás)']);

        $regional = Regional::query()->updateOrCreate(
            ['codigo' => '01'],
            ['central_nacional_id' => $central->id, 'nome' => 'Brasília', 'status' => 'ativo'],
        );

        $setorNucleo = Setor::query()->updateOrCreate(
            ['codigo' => '0110'],
            ['regional_id' => $regional->id, 'nome' => 'Núcleo Bandeirante', 'status' => 'ativo'],
        );

        $setorTaguatinga = Setor::query()->updateOrCreate(
            ['codigo' => '0111'],
            ['regional_id' => $regional->id, 'nome' => 'Taguatinga', 'status' => 'ativo'],
        );

        $igreja = Igreja::query()->updateOrCreate(
            ['codigo' => '240003'],
            ['nome' => 'Igreja do Núcleo Bandeirante', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo'],
        );

        if ($igreja->vinculacaoVigente()->doesntExist()) {
            $igreja->vinculacoes()->create([
                'setor_id' => $setorNucleo->id,
                'inicio' => now()->toDateString(),
                'fim' => null,
            ]);
        }

        $igreja2 = Igreja::query()->updateOrCreate(
            ['codigo' => '240004'],
            ['nome' => 'Igreja de Taguatinga', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo'],
        );

        if ($igreja2->vinculacaoVigente()->doesntExist()) {
            $igreja2->vinculacoes()->create([
                'setor_id' => $setorTaguatinga->id,
                'inicio' => now()->toDateString(),
                'fim' => null,
            ]);
        }

        Ebi::query()->updateOrCreate(
            ['igreja_id' => $igreja->id, 'nome' => 'EBI Núcleo Bandeirante'],
            ['participantes_por_auxiliar' => 4, 'status' => 'ativo'],
        );
    }
}
