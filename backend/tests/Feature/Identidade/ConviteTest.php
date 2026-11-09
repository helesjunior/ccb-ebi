<?php

use App\Domain\Identidade\Models\Convite;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Regional;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->central = CentralNacional::factory()->create();
});

it('cria convite e envia email com token', function () {
    Notification::fake();
    $regional = Regional::factory()->create(['central_nacional_id' => $this->central->id]);
    $admin = criarUsuarioComPapel('adm_regional', 'regional', $regional->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->postJson('/api/v1/invitations', [
        'email' => 'nova@ex.com', 'papel' => 'auxiliar',
        'tipo_escopo' => 'regional', 'escopo_id' => $regional->id,
    ], $auth)
        ->assertStatus(201)
        ->assertJsonPath('email', 'nova@ex.com');

    expect(Convite::query()->count())->toBe(1)
        ->and(Convite::query()->first()->token_hash)->not->toBeEmpty();
});

it('nega convite com papel acima da autoridade do convidante', function () {
    $regional = Regional::factory()->create(['central_nacional_id' => $this->central->id]);
    $admin = criarUsuarioComPapel('adm_setor', 'regional', $regional->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    // adm_setor nao convida adm_regional (papel acima) nem fora do escopo.
    $this->postJson('/api/v1/invitations', [
        'email' => 'x@ex.com', 'papel' => 'adm_regional',
        'tipo_escopo' => 'regional', 'escopo_id' => $regional->id,
    ], $auth)->assertStatus(403);
});

it('lista e cancela convites', function () {
    Notification::fake();
    $regional = Regional::factory()->create(['central_nacional_id' => $this->central->id]);
    $admin = criarUsuarioComPapel('adm_regional', 'regional', $regional->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->postJson('/api/v1/invitations', [
        'email' => 'nova@ex.com', 'papel' => 'auxiliar',
        'tipo_escopo' => 'regional', 'escopo_id' => $regional->id,
    ], $auth)->assertStatus(201);

    $id = $this->getJson('/api/v1/invitations', $auth)->assertOk()->json('data.0.id');

    $this->deleteJson("/api/v1/invitations/{$id}", [], $auth)->assertNoContent();
    expect(Convite::query()->first()->cancelado_em)->not->toBeNull();
});
