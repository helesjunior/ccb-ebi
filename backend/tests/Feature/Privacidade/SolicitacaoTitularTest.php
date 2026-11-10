<?php

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Hash;

it('abre uma solicitacao com protocolo e status recebida', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $this->postJson('/api/v1/privacy/requests', ['tipo' => 'acesso'], $auth)
        ->assertStatus(201)
        ->assertJsonPath('status', 'recebida')
        ->assertJsonStructure(['id', 'protocolo', 'tipo', 'status']);
});

it('rejeita tipo invalido', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $this->postJson('/api/v1/privacy/requests', ['tipo' => 'x'], $auth)->assertStatus(422);
});
