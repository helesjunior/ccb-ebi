<?php

use App\Domain\Identidade\Models\FatorMfa;

use function Pest\Laravel\getJson;

it('expoe mfa_ativo falso quando nao ha fator confirmado', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    getJson('/api/v1/me', $auth)
        ->assertOk()
        ->assertJsonPath('mfa_ativo', false);
});

it('expoe mfa_ativo verdadeiro com fator confirmado', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    FatorMfa::query()->create([
        'usuario_id' => $usuario->id,
        'tipo' => FatorMfa::TIPO_TOTP,
        'segredo_cifrado' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
        'confirmado_em' => now(),
    ]);
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    getJson('/api/v1/me', $auth)
        ->assertOk()
        ->assertJsonPath('mfa_ativo', true);
});