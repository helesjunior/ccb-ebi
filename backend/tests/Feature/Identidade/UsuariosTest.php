<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Igreja;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->igreja = Igreja::factory()->create();
});

/** Cria um usuario com um vinculo de acesso vigente num no real. */
function usuarioComVinculo(string $papelCodigo, string $tipoEscopo, string $escopoId): Usuario
{
    $usuario = Usuario::factory()->create([
        'senha' => Hash::make('senha-bem-longa-123'),
        'email_verificado_em' => now(),
    ]);

    VinculoAcesso::factory()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', $papelCodigo)->value('id'),
        'tipo_escopo' => $tipoEscopo,
        'escopo_id' => $escopoId,
    ]);

    return $usuario;
}

it('exige permissao de leitura', function () {
    $admin = usuarioComVinculo('adm_igreja', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->getJson('/api/v1/users', $auth)->assertOk()->assertJsonStructure(['data' => [['id', 'nome', 'email', 'email_verificado', 'status', 'papeis']], 'meta' => ['proximo_cursor']]);
});

it('nega acesso sem token', function () {
    $this->getJson('/api/v1/users')->assertStatus(401);
});

it('suspende um usuario dentro da subarvore', function () {
    $admin = usuarioComVinculo('adm_igreja', 'igreja', $this->igreja->id);
    $alvo = usuarioComVinculo('coordenadora', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->patchJson("/api/v1/users/{$alvo->id}", ['status' => 'suspenso'], $auth)->assertOk()->assertJsonPath('status', 'suspenso');
});

it('nega leitura a papel sem permissao (responsavel)', function () {
    $usuario = usuarioComVinculo('responsavel', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    $this->getJson('/api/v1/users', $auth)->assertStatus(403);
});

it('nega escalada vertical: admin de igreja nao gere super_admin', function () {
    $ator = usuarioComVinculo('adm_igreja', 'igreja', $this->igreja->id);
    $alvo = usuarioComVinculo('super_admin', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$ator->createToken('t')->plainTextToken];

    $this->patchJson("/api/v1/users/{$alvo->id}", ['status' => 'suspenso'], $auth)
        ->assertStatus(403)
        ->assertJsonPath('code', 'usuario_fora_escopo');
});

it('nega gestao de alvo fora da subarvore', function () {
    $ator = usuarioComVinculo('adm_igreja', 'igreja', $this->igreja->id);
    $outra = Igreja::factory()->create();
    $alvo = usuarioComVinculo('coordenadora', 'igreja', $outra->id);
    $auth = ['Authorization' => 'Bearer '.$ator->createToken('t')->plainTextToken];

    $this->patchJson("/api/v1/users/{$alvo->id}", ['status' => 'suspenso'], $auth)
        ->assertStatus(403)
        ->assertJsonPath('code', 'usuario_fora_escopo');
});
