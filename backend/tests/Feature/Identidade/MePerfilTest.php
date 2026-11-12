<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\FatorMfa;
use App\Domain\Identidade\Models\Usuario;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

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

it('atualiza nome e e-mail do proprio usuario', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    patchJson('/api/v1/me', ['nome' => 'Maria Silva', 'email' => 'Maria@Exemplo.test'], $auth)
        ->assertOk()
        ->assertJsonPath('nome', 'Maria Silva')
        ->assertJsonPath('email', 'maria@exemplo.test');

    $usuario->refresh();
    expect($usuario->nome)->toBe('Maria Silva')
        ->and($usuario->email_normalizado)->toBe('maria@exemplo.test')
        ->and($usuario->email_verificado_em)->toBeNull(); // e-mail mudou: reverifica
});

it('mantem o e-mail verificado quando o e-mail nao muda', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    patchJson('/api/v1/me', ['nome' => 'Novo Nome', 'email' => $usuario->email_normalizado], $auth)
        ->assertOk();

    expect($usuario->refresh()->email_verificado_em)->not->toBeNull();
});

it('recusa e-mail ja usado por outro usuario com 422', function () {
    Usuario::factory()->create(['email_normalizado' => 'tomado@exemplo.test']);
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    patchJson('/api/v1/me', ['email' => 'tomado@exemplo.test'], $auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'email_em_uso');

    expect($usuario->refresh()->email_normalizado)->not->toBe('tomado@exemplo.test');
});

it('audita a alteracao de dados pessoais', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    patchJson('/api/v1/me', ['nome' => 'Nome Auditado'], $auth)->assertOk();

    expect(EventoAuditoria::query()->where('acao', 'alterar_perfil')->where('ator_id', $usuario->id)->exists())
        ->toBeTrue();
});

it('nega PATCH /me sem token', function () {
    patchJson('/api/v1/me', ['nome' => 'X'])->assertStatus(401);
});
