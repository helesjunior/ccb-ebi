<?php

use App\Domain\Identidade\Contracts\GerenciarSessao;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Hash;

it('lista e revoga dispositivos', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $token = $u->createToken('t')->plainTextToken;
    $auth = ['Authorization' => "Bearer {$token}"];

    app(GerenciarSessao::class)->abrir($u, 'Chrome/Windows');
    $id = $this->getJson('/api/v1/me/sessions', $auth)->assertOk()->json('data.0.id');

    $this->deleteJson("/api/v1/me/sessions/{$id}", [], $auth)->assertNoContent();
});
