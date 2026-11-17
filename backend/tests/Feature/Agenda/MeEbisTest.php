<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Setor;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

it('devolve apenas as EBIs alcancadas pelos vinculos do ator', function () {
    ['igreja' => $igrejaA, 'ebi' => $ebiA] = igrejaComEbi();
    ['igreja' => $igrejaB, 'ebi' => $ebiB] = igrejaComEbi();

    $setor = Setor::factory()->create();
    foreach ([$igrejaA, $igrejaB] as $igreja) {
        IgrejaVinculacao::query()->create([
            'igreja_id' => $igreja->id,
            'setor_id' => $setor->id,
            'inicio' => now()->subYear()->toDateString(),
            'fim' => null,
        ]);
    }

    $adm = criarUsuarioComPapel('adm_setor', 'setor', $setor->id);
    $auth = [
        'Authorization' => 'Bearer '.$adm->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=adm_setor;escopo=setor:{$setor->id}",
    ];

    $this->getJson('/api/v1/me/ebis', $auth)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $ebiA->id, 'igreja_nome' => $igrejaA->nome])
        ->assertJsonFragment(['id' => $ebiB->id]);
});

it('o contexto de atuacao restringe a lista', function () {
    ['igreja' => $igreja, 'ebi' => $ebi] = igrejaComEbi();
    $outra = igrejaComEbi()['ebi'];

    $coord = criarUsuarioComPapel('coordenadora', 'ebi', $ebi->id);
    VinculoAcesso::factory()->create([
        'usuario_id' => $coord->id,
        'papel_id' => Papel::query()->where('codigo', 'coordenadora')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $outra->id,
        'inicio' => now()->subDay(),
    ]);

    $auth = [
        'Authorization' => 'Bearer '.$coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra->id}",
    ];

    $this->getJson('/api/v1/me/ebis', $auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $outra->id);
});
