<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\FatorMfa;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

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

it('troca a senha mantendo a sessao atual viva', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $atual = $usuario->createToken('atual')->plainTextToken;
    $outraToken = $usuario->createToken('outra')->plainTextToken;
    $auth = ['Authorization' => 'Bearer '.$atual];

    postJson('/api/v1/me/password', [
        'senha_atual' => 'senha-bem-longa-123',
        'nova_senha' => 'outra-senha-longa-456',
        'nova_senha_confirmation' => 'outra-senha-longa-456',
    ], $auth)->assertNoContent();

    expect(Hash::check('outra-senha-longa-456', $usuario->refresh()->senha))->toBeTrue();

    // Sessão atual continua válida; a outra foi derrubada.
    getJson('/api/v1/me', $auth)->assertOk();
    $this->app['auth']->forgetGuards();
    getJson('/api/v1/me', ['Authorization' => 'Bearer '.$outraToken])->assertStatus(401);
});

it('recusa senha atual incorreta com 422 e nao troca nada', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    postJson('/api/v1/me/password', [
        'senha_atual' => 'errada-mas-longa-123',
        'nova_senha' => 'outra-senha-longa-456',
        'nova_senha_confirmation' => 'outra-senha-longa-456',
    ], $auth)->assertStatus(422)->assertJsonPath('code', 'senha_atual_invalida');

    expect(Hash::check('senha-bem-longa-123', $usuario->refresh()->senha))->toBeTrue();
});

it('exige nova senha com no minimo 12 caracteres', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    postJson('/api/v1/me/password', [
        'senha_atual' => 'senha-bem-longa-123',
        'nova_senha' => 'curta',
        'nova_senha_confirmation' => 'curta',
    ], $auth)->assertStatus(422);
});
