<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Organizacao\Models\CentralNacional;

beforeEach(function () {
    $this->central = CentralNacional::factory()->create();
});

it('exige permissao de leitura', function () {
    $admin = criarUsuarioComPapel('adm_setor', 'central', $this->central->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->getJson('/api/v1/users', $auth)->assertOk()->assertJsonStructure(['data' => [['id', 'nome', 'email', 'email_verificado', 'status', 'papeis']], 'meta' => ['proximo_cursor']]);
});

it('nega acesso sem token', function () {
    $this->getJson('/api/v1/users')->assertStatus(401);
});

it('suspende um usuario', function () {
    $admin = criarUsuarioComPapel('adm_igreja', 'central', $this->central->id);
    $alvo = Usuario::factory()->create();
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->patchJson("/api/v1/users/{$alvo->id}", ['status' => 'suspenso'], $auth)->assertOk()->assertJsonPath('status', 'suspenso');
});

it('nega leitura a papel sem permissao (responsavel)', function () {
    $usuario = criarUsuarioComPapel('responsavel', 'central', $this->central->id);
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    $this->getJson('/api/v1/users', $auth)->assertStatus(403);
});
