<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Privacidade\Models\TermoVersao;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('verifica o e-mail apenas com assinatura valida', function () {
    Notification::fake();
    $this->seed(PapelSeeder::class);
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->subDay()]);

    $this->postJson('/api/v1/auth/register-guardian', [
        'nome' => 'Ana', 'email' => 'ana@ex.com', 'password' => 'senha-bem-longa-123',
        'termo_versao_id' => $termo->id, 'aceite' => true,
    ])
        ->assertStatus(201);

    $u = Usuario::query()->where('email_normalizado', 'ana@ex.com')->firstOrFail();

    // Sem assinatura: recusado (422, campo obrigatório).
    $this->postJson('/api/v1/auth/verify-email', ['usuario' => $u->id])
        ->assertStatus(422);

    // Com assinatura forjada/inválida: recusado.
    $this->postJson('/api/v1/auth/verify-email?signature=abc', ['usuario' => $u->id])
        ->assertStatus(403);

    // Com a URL assinada correta (parâmetros na query do POST): aceito.
    $url = URL::temporarySignedRoute('auth.verify-email', now()->addHour(), ['usuario' => $u->id]);
    $query = parse_url($url, PHP_URL_QUERY);

    $this->postJson("/api/v1/auth/verify-email?{$query}", ['usuario' => $u->id])->assertNoContent();

    expect(Usuario::query()->find($u->id)->email_verificado_em)->not->toBeNull();
});
