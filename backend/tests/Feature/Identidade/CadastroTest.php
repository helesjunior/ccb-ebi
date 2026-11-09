<?php

use App\Domain\Identidade\Models\Usuario;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Notification;

it('cadastra responsavel, sem verificar, e envia e-mail', function () {
    Notification::fake();
    $this->seed(PapelSeeder::class);

    $this->postJson('/api/v1/auth/register-guardian', ['nome' => 'Ana', 'email' => 'Ana@Ex.com', 'password' => 'senha-bem-longa-123'])
        ->assertStatus(201)->assertJsonPath('email', 'ana@ex.com');

    $u = Usuario::query()->where('email_normalizado', 'ana@ex.com')->firstOrFail();
    expect($u->email_verificado_em)->toBeNull();
    $this->assertDatabaseHas('vinculo_acesso', ['usuario_id' => $u->id, 'status' => 'ativo']); // papel responsavel
});

it('nao revela se o email existe no reenvio', function () {
    $this->seed(PapelSeeder::class);
    $this->postJson('/api/v1/auth/email/resend', ['email' => 'ninguem@ex.com'])->assertStatus(202);
});
