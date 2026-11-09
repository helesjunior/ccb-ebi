<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Organizacao\Models\Igreja;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

function tokenDe(string $papelCodigo, string $tipoEscopo = 'central', ?string $escopoId = null): array
{
    $usuario = criarUsuarioComPapel($papelCodigo, $tipoEscopo, $escopoId);

    return ['Authorization' => 'Bearer '.$usuario->createToken('web', ['*'])->plainTextToken];
}

it('nega leitura e escrita de organizacao para responsavel (sem permissao)', function () {
    $igreja = Igreja::factory()->create();
    $auth = tokenDe('responsavel', 'ebi', (string) $igreja->id);

    $this->getJson('/api/v1/churches', $auth)->assertStatus(403);
    $this->getJson("/api/v1/churches/{$igreja->id}", $auth)->assertStatus(403);
    $this->patchJson("/api/v1/churches/{$igreja->id}", ['name' => 'X'], $auth)->assertStatus(403);
});

it('nega escrita de organizacao para coordenadora (so leitura na matriz)', function () {
    $igreja = Igreja::factory()->create();
    $auth = tokenDe('coordenadora', 'setor', (string) Str::ulid());

    $this->getJson('/api/v1/churches', $auth)->assertOk();
    $this->patchJson("/api/v1/churches/{$igreja->id}", ['name' => 'X'], $auth)->assertStatus(403);
});

it('permite leitura ao adm_setor', function () {
    $auth = tokenDe('adm_setor', 'setor', (string) Str::ulid());

    $this->getJson('/api/v1/churches', $auth)->assertOk();
});

it('rejeita token de desafio de MFA na area operacional', function () {
    criarUsuarioComPapel('coordenadora', 'setor', (string) Str::ulid());
    $u = Usuario::query()->firstOrFail();

    $desafio = $this->postJson('/api/v1/auth/login', [
        'email' => $u->email_normalizado,
        'password' => 'senha-bem-longa-123',
    ])->assertStatus(200)->json('token_desafio');

    $this->getJson('/api/v1/churches', ['Authorization' => "Bearer {$desafio}"])
        ->assertStatus(403)
        ->assertJsonPath('code', 'mfa_pendente');
});
