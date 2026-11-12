<?php

use App\Domain\Identidade\Models\CodigoRecuperacao;
use App\Domain\Identidade\Models\FatorMfa;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Support\Totp;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

/**
 * Regressão do fluxo CA-15 ponta a ponta: login de perfil com MFA devolve
 * desafio, o desafio conclui o segundo fator e só então a sessão plena acessa a
 * área operacional. Cobria um buraco onde /auth/mfa/verify nunca chegava ao
 * controller (alias `abilities` ausente + ResolveActingScope bloqueando o
 * token de desafio).
 */
it('conclui o MFA e libera a area operacional', function () {
    $u = criarUsuarioComPapel('coordenadora', 'central', 'central-1');

    // Setup: inscreve e confirma o TOTP com um token de sessão plena.
    $setup = $u->createToken('setup', ['*'])->plainTextToken;
    $segredo = $this->postJson('/api/v1/me/mfa/totp', [], ['Authorization' => "Bearer {$setup}"])
        ->assertOk()->json('segredo');
    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredo)], ['Authorization' => "Bearer {$setup}"])
        ->assertOk();

    // 1) Login devolve desafio (sem sessão plena).
    $desafio = $this->postJson('/api/v1/auth/login', [
        'email' => $u->email_normalizado,
        'password' => 'senha-bem-longa-123',
    ])
        ->assertOk()
        ->assertJsonPath('mfa_required', true)
        ->json('token_desafio');

    // 2) O token de desafio NÃO acessa a área operacional.
    // O guard Sanctum cacheia o último token no mesmo teste; limpa para
    // garantir que a requisição usa o token de desafio de fato.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$desafio}"])
        ->assertStatus(403)
        ->assertJsonPath('code', 'mfa_pendente');

    // 3) Conclui o segundo fator e recebe a sessão plena.
    $this->app['auth']->forgetGuards();
    $token = $this->postJson('/api/v1/auth/mfa/verify', ['codigo' => Totp::codigoAtual($segredo)], ['Authorization' => "Bearer {$desafio}"])
        ->assertStatus(201)
        ->assertJsonStructure(['token', 'usuario'])
        ->json('token');

    // 4) A sessão plena acessa a área operacional; o desafio foi revogado.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('email', $u->email_normalizado);

    expect(Usuario::query()->find($u->id)->tokens()->where('name', 'mfa-desafio')->exists())->toBeFalse();
});

/**
 * Usuário de perfil com MFA que ainda NÃO tem fator inscrito: o desafio aponta
 * a inscrição pendente e o token de desafio consegue inscrever/confirmar o TOTP
 * antes de concluir o login. Sem isso, todo usuário novo fica travado (não há
 * código para digitar).
 */
it('inscreve o TOTP pelo token de desafio quando o usuario nao tem fator', function () {
    $u = criarUsuarioComPapel('coordenadora', 'central', 'central-1');

    // 1) Login devolve desafio e sinaliza que a inscricao esta pendente.
    $desafio = $this->postJson('/api/v1/auth/login', [
        'email' => $u->email_normalizado,
        'password' => 'senha-bem-longa-123',
    ])
        ->assertOk()
        ->assertJsonPath('mfa_required', true)
        ->assertJsonPath('mfa_inscricao_pendente', true)
        ->json('token_desafio');

    // 2) O token de desafio NAO alcanca a area operacional nem os codigos de
    //    recuperacao (que exigem sessao plena).
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$desafio}"])
        ->assertStatus(403)
        ->assertJsonPath('code', 'mfa_pendente');

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/me/mfa/recovery-codes', [], ['Authorization' => "Bearer {$desafio}"])
        ->assertStatus(403)
        ->assertJsonPath('code', 'mfa_pendente');

    // 3) MAS o desafio PODE inscrever o TOTP (excecao estreita).
    $this->app['auth']->forgetGuards();
    $segredo = $this->postJson('/api/v1/me/mfa/totp', [], ['Authorization' => "Bearer {$desafio}"])
        ->assertOk()
        ->assertJsonStructure(['segredo', 'uri'])
        ->json('segredo');

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredo)], ['Authorization' => "Bearer {$desafio}"])
        ->assertOk()
        ->assertJsonPath('confirmado', true);

    // 4) Agora o desafio conclui o segundo fator e recebe a sessao plena.
    $this->app['auth']->forgetGuards();
    $token = $this->postJson('/api/v1/auth/mfa/verify', ['codigo' => Totp::codigoAtual($segredo)], ['Authorization' => "Bearer {$desafio}"])
        ->assertStatus(201)
        ->json('token');

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('email', $u->email_normalizado);
});

it('nao sinaliza inscricao pendente quando o usuario ja tem fator validado', function () {
    $u = criarUsuarioComPapel('coordenadora', 'central', 'central-1');

    $setup = $u->createToken('setup', ['*'])->plainTextToken;
    $segredo = $this->postJson('/api/v1/me/mfa/totp', [], ['Authorization' => "Bearer {$setup}"])
        ->assertOk()->json('segredo');
    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredo)], ['Authorization' => "Bearer {$setup}"])
        ->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'email' => $u->email_normalizado,
        'password' => 'senha-bem-longa-123',
    ])
        ->assertOk()
        ->assertJsonPath('mfa_required', true)
        ->assertJsonPath('mfa_inscricao_pendente', false);
});

/**
 * Renovar o MFA com um fator já confirmado: ao iniciar a nova inscrição o fator
 * ativo continua valendo (design §6.4); a substituição só acontece ao confirmar
 * o novo código, sem violar o índice único parcial `fator_mfa_confirmado_unique`.
 */
it('renova o MFA substituindo o fator confirmado ao confirmar', function () {
    $u = criarUsuarioComPapel('coordenadora', 'central', 'central-1');
    $auth = ['Authorization' => 'Bearer '.$u->createToken('setup', ['*'])->plainTextToken];

    $segredoAntigo = $this->postJson('/api/v1/me/mfa/totp', [], $auth)->assertOk()->json('segredo');
    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredoAntigo)], $auth)
        ->assertOk();

    // Renova: nova inscrição com o fator antigo já ativo.
    $segredoNovo = $this->postJson('/api/v1/me/mfa/totp', [], $auth)->assertOk()->json('segredo');

    expect($segredoNovo)->not->toBe($segredoAntigo);

    // Entre iniciar e confirmar o fator antigo segue ativo (sem janela sem MFA).
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', $auth)->assertOk()->assertJsonPath('mfa_ativo', true);
    expect(Totp::validar($segredoAntigo, Totp::codigoAtual($segredoAntigo)))->toBeTrue();

    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredoNovo)], $auth)
        ->assertOk()
        ->assertJsonPath('confirmado', true);

    // Ao confirmar, o fator antigo é revogado: resta só o novo confirmado.
    $confirmados = FatorMfa::query()
        ->where('usuario_id', $u->id)
        ->whereNotNull('confirmado_em')
        ->get();

    expect($confirmados)->toHaveCount(1)
        ->and((string) $confirmados->first()->segredo_cifrado)->toBe($segredoNovo)
        ->and(FatorMfa::query()->where('usuario_id', $u->id)->count())->toBe(1);

    // A sessão plena segue acessando a área operacional.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', $auth)->assertOk()->assertJsonPath('mfa_ativo', true);
});

it('mantem o fator antigo quando a confirmacao da renovacao falha', function () {
    $u = criarUsuarioComPapel('coordenadora', 'central', 'central-1');
    $auth = ['Authorization' => 'Bearer '.$u->createToken('setup', ['*'])->plainTextToken];

    $segredoAntigo = $this->postJson('/api/v1/me/mfa/totp', [], $auth)->assertOk()->json('segredo');
    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredoAntigo)], $auth)
        ->assertOk();

    $this->postJson('/api/v1/me/mfa/totp', [], $auth)->assertOk();

    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => '000000'], $auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'mfa_invalido');

    // O fator antigo continua válido: o usuário não fica sem segundo fator.
    $confirmado = FatorMfa::query()
        ->where('usuario_id', $u->id)
        ->whereNotNull('confirmado_em')
        ->sole();

    expect((string) $confirmado->segredo_cifrado)->toBe($segredoAntigo);

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', $auth)->assertOk()->assertJsonPath('mfa_ativo', true);
});

it('invalida os codigos de recuperacao antigos ao renovar o MFA', function () {
    $u = criarUsuarioComPapel('coordenadora', 'central', 'central-1');
    $auth = ['Authorization' => 'Bearer '.$u->createToken('setup', ['*'])->plainTextToken];

    $segredoAntigo = $this->postJson('/api/v1/me/mfa/totp', [], $auth)->assertOk()->json('segredo');
    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredoAntigo)], $auth)
        ->assertOk();
    $codigosAntigos = $this->postJson('/api/v1/me/mfa/recovery-codes', [], $auth)
        ->assertOk()->json('codigos');

    $segredoNovo = $this->postJson('/api/v1/me/mfa/totp', [], $auth)->assertOk()->json('segredo');
    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($segredoNovo)], $auth)
        ->assertOk();

    // Nenhum código de recuperação anterior sobrevive à renovação.
    expect(CodigoRecuperacao::query()->where('usuario_id', $u->id)->count())->toBe(0);

    $desafio = $this->postJson('/api/v1/auth/login', [
        'email' => $u->email_normalizado,
        'password' => 'senha-bem-longa-123',
    ])->assertOk()->json('token_desafio');

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/auth/mfa/verify', ['codigo' => $codigosAntigos[0]], ['Authorization' => "Bearer {$desafio}"])
        ->assertStatus(422);
});
