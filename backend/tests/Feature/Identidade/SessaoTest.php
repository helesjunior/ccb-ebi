<?php

use App\Domain\Identidade\Contracts\GerenciarSessao;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Hash;

it('abre no login, lista e revoga o dispositivo invalidando o token', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $token = app(GerenciarSessao::class)->abrir($u, 'Chrome/Windows');
    $auth = ['Authorization' => "Bearer {$token}"];

    // A sessão aparece na listagem e o token abre a área.
    $id = $this->getJson('/api/v1/me/sessions', $auth)->assertOk()->json('data.0.id');
    expect($id)->not->toBeNull();
    $this->getJson('/api/v1/me', $auth)->assertOk();

    // Revogar o dispositivo invalida o token de fato.
    $this->deleteJson("/api/v1/me/sessions/{$id}", [], $auth)->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', $auth)->assertStatus(401);
});

it('nao revoga sessao de outro usuario', function () {
    $u = Usuario::factory()->create(['email_verificado_em' => now()]);
    $outro = Usuario::factory()->create(['email_verificado_em' => now()]);

    $sessoes = app(GerenciarSessao::class);
    $tokenOutro = $sessoes->abrir($outro, 'Outro');
    $idOutro = $this->getJson('/api/v1/me/sessions', ['Authorization' => "Bearer {$tokenOutro}"])->json('data.0.id');

    $meuToken = $sessoes->abrir($u, 'Meu');
    $this->app['auth']->forgetGuards();
    $this->deleteJson("/api/v1/me/sessions/{$idOutro}", [], ['Authorization' => "Bearer {$meuToken}"])->assertNoContent();

    // A sessão do outro continua vigente no banco e o token segue válido.
    $this->assertDatabaseHas('sessao_login', ['id' => $idOutro, 'revogada_em' => null]);
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me/sessions', ['Authorization' => "Bearer {$tokenOutro}"])
        ->assertOk()
        ->assertJsonPath('data.0.id', $idOutro);
});
