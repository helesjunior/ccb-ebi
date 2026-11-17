<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Setor;
use App\Http\Controllers\Api\V1\MeController;
use Database\Seeders\PapelSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

it('devolve apenas as EBIs alcancadas pelos vinculos do ator', function () {
    ['igreja' => $igrejaA, 'ebi' => $ebiA] = igrejaComEbi();
    ['igreja' => $igrejaB, 'ebi' => $ebiB] = igrejaComEbi();
    ['igreja' => $igrejaFora, 'ebi' => $ebiFora] = igrejaComEbi();

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
        ->assertJsonFragment(['id' => $ebiB->id])
        ->assertJsonMissing(['id' => $ebiFora->id]);
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

it('sem contexto de atuacao, deduplica as EBIs alcancadas por varios vinculos', function () {
    ['ebi' => $ebi] = igrejaComEbi();

    $usuario = criarUsuarioComPapel('coordenadora', 'ebi', $ebi->id);
    VinculoAcesso::factory()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', 'auxiliar')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $ebi->id,
        'inicio' => now()->subDay(),
    ]);

    // Via HTTP o middleware RN-11 exige X-Acting-Scope quando o usuario acumula
    // papeis (422). O ramo sem contexto do controller e exercido diretamente:
    // request sem `contexto_atuacao` + o usuario com dois vinculos para a MESMA
    // EBI — `listarPorEscopo` roda duas vezes e o dedupe por id prevalece.
    $request = Request::create('/api/v1/me/ebis');
    $request->setUserResolver(fn () => $usuario);

    $dados = app(MeController::class)->ebis($request)->getData(true)['data'];

    expect($dados)->toHaveCount(1)
        ->and($dados[0]['id'])->toBe($ebi->id);
});
