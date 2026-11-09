<?php

use App\Domain\Identidade\Models\Convite;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Notifications\ConviteEbi;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Regional;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->central = CentralNacional::factory()->create();
});

/** Cria um convite via API e devolve ['id' => ..., 'token' => ...]. */
function criarConvite(Regional $regional): array
{
    $admin = criarUsuarioComPapel('adm_regional', 'regional', $regional->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $id = test()->postJson('/api/v1/invitations', [
        'email' => 'nova@ex.com', 'papel' => 'auxiliar',
        'tipo_escopo' => 'regional', 'escopo_id' => $regional->id,
    ], $auth)->assertStatus(201)->json('id');

    $token = null;
    Notification::assertSentOnDemand(
        ConviteEbi::class,
        function (ConviteEbi $n) use (&$token): bool {
            $token = $n->token;

            return true;
        },
    );

    return ['id' => $id, 'token' => $token];
}

it('aceita convite sem login e cria vinculo vigente', function () {
    Notification::fake();
    $regional = Regional::factory()->create(['central_nacional_id' => $this->central->id]);
    $criado = criarConvite($regional);

    $this->postJson("/api/v1/invitations/{$criado['id']}/accept", [
        'token' => $criado['token'], 'nome' => 'Nova', 'password' => 'senha-bem-longa-123',
    ])->assertStatus(201)->assertJsonPath('email_normalizado', 'nova@ex.com');

    $u = Usuario::query()->where('email_normalizado', 'nova@ex.com')->firstOrFail();
    expect($u->vinculosVigentes()->count())->toBe(1);
    expect(Convite::query()->find($criado['id'])->aceito_em)->not->toBeNull();

    // MFA obrigatório no primeiro acesso: o novo membro de equipe não tem fator.
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nova@ex.com', 'password' => 'senha-bem-longa-123',
    ])->assertStatus(200)->assertJsonPath('mfa_required', true);

    // Replay: o token é de uso único.
    $this->postJson("/api/v1/invitations/{$criado['id']}/accept", [
        'token' => $criado['token'], 'nome' => 'Nova', 'password' => 'senha-bem-longa-123',
    ])->assertStatus(410);
});

it('rejeita token de convite invalido', function () {
    Notification::fake();
    $regional = Regional::factory()->create(['central_nacional_id' => $this->central->id]);
    $criado = criarConvite($regional);

    $this->postJson("/api/v1/invitations/{$criado['id']}/accept", [
        'token' => 'token-errado', 'nome' => 'Nova', 'password' => 'senha-bem-longa-123',
    ])->assertStatus(410);
});
