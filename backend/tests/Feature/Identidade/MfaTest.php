<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Support\Totp;
use Illuminate\Support\Facades\Hash;

it('inscreve, confirma TOTP e gera codigos de recuperacao', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $token = $u->createToken('t')->plainTextToken;

    $segredo = $this->postJson('/api/v1/me/mfa/totp', [], ['Authorization' => "Bearer {$token}"])
        ->assertOk()->json('segredo');

    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredo)], ['Authorization' => "Bearer {$token}"])
        ->assertOk();

    $this->postJson('/api/v1/me/mfa/recovery-codes', [], ['Authorization' => "Bearer {$token}"])
        ->assertOk()->assertJsonStructure(['codigos']);
});
