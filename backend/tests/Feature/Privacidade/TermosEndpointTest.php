<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Privacidade\Models\TermoVersao;
use Illuminate\Support\Facades\Hash;

it('lista o termo vigente da finalidade', function () {
    TermoVersao::factory()->create(['finalidade' => 'cadastro', 'versao' => '2026-11', 'vigente_em' => now()->subDay()]);
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $this->getJson('/api/v1/privacy/terms?finalidade=cadastro', $auth)
        ->assertOk()
        ->assertJsonPath('data.0.versao', '2026-11');
});

it('exige autenticacao', function () {
    $this->getJson('/api/v1/privacy/terms?finalidade=cadastro')->assertStatus(401);
});
