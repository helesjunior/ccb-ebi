<?php

use App\Domain\Identidade\Contracts\RecuperarSenha;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Notifications\RedefinicaoSenha;
use Illuminate\Support\Facades\Notification;

it('responde 202 identico para email existente e inexistente', function () {
    Notification::fake();
    Usuario::factory()->create(['email_normalizado' => 'existe@ex.com']);

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'existe@ex.com'])->assertStatus(202);
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'naoexiste@ex.com'])->assertStatus(202);
});

it('redefine a senha com token valido e rejeita token invalido', function () {
    Notification::fake();
    Usuario::factory()->create(['email_normalizado' => 'alvo@ex.com']);

    app(RecuperarSenha::class)->solicitar('alvo@ex.com');

    // O token em claro so existe na notificacao enfileirada; no banco fica o hash.
    $token = null;
    Notification::assertSentTo(
        Usuario::query()->firstOrFail(),
        RedefinicaoSenha::class,
        function (RedefinicaoSenha $n) use (&$token): bool {
            $token = $n->token;

            return true;
        },
    );

    $this->postJson('/api/v1/auth/password/reset', ['email' => 'alvo@ex.com', 'token' => $token, 'password' => 'nova-senha-longa-456'])->assertOk();
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'alvo@ex.com', 'token' => 'xxx', 'password' => 'nova-senha-longa-456'])->assertStatus(422);
});
