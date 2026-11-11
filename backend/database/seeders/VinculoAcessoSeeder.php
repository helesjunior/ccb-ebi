<?php

namespace Database\Seeders;

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use Illuminate\Database\Seeder;

/**
 * Vínculos de acesso para desenvolvimento e demonstração (E03).
 *
 * Sem um `vinculo_acesso`, o usuário autentica mas **não tem escopo de atuação**
 * (o `X-Acting-Scope` não resolve), então as rotas territoriais negam (403). Este
 * seeder dá à coordenadora de demonstração escopo na **EBI do seed** — é a igreja
 * em que os participantes de demonstração estão (ver `ParticipanteSeeder`).
 *
 * Idempotente: pode rodar repetidas vezes.
 */
class VinculoAcessoSeeder extends Seeder
{
    public function run(): void
    {
        $coordenadora = Usuario::query()
            ->where('email_normalizado', 'coordenadora@ebi.test')
            ->first();

        $ebi = Ebi::query()->orderBy('nome')->first();

        if ($coordenadora === null || $ebi === null) {
            return; // sem usuário/organização de demonstração, nada a fazer
        }

        $papelId = Papel::query()->where('codigo', 'coordenadora')->value('id');

        VinculoAcesso::query()->updateOrCreate(
            [
                'usuario_id' => $coordenadora->id,
                'papel_id' => $papelId,
                'tipo_escopo' => VinculoAcesso::TIPO_EBI,
                'escopo_id' => $ebi->id,
            ],
            ['inicio' => now()->toDateString(), 'fim' => null, 'status' => 'ativo'],
        );
    }
}
