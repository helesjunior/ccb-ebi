<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Organizacao\Models\Igreja;

it('concede e revoga vinculo de acesso', function () {
    $admin = criarUsuarioComPapel('super_admin', 'central', 'c1');
    $alvo = Usuario::factory()->create();
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $vinculo = $this->postJson('/api/v1/access-grants', [
        'usuario_id' => $alvo->id, 'papel' => 'coordenadora',
        'tipo_escopo' => 'regional', 'escopo_id' => 'reg1',
    ], $auth)->assertStatus(201)->json();

    $this->assertDatabaseHas('vinculo_acesso', ['id' => $vinculo['id'], 'fim' => null]);
    $this->deleteJson("/api/v1/access-grants/{$vinculo['id']}", [], $auth)->assertNoContent();
    $this->assertDatabaseMissing('vinculo_acesso', ['id' => $vinculo['id'], 'fim' => null]);
});

it('exige permissao de gestao de acesso', function () {
    $usuario = criarUsuarioComPapel('coordenadora', 'central', 'c1');
    $alvo = Usuario::factory()->create();
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => $alvo->id, 'papel' => 'auxiliar',
        'tipo_escopo' => 'central', 'escopo_id' => 'c1',
    ], $auth)->assertStatus(403);
});

it('nega escalada vertical via concessao de super_admin', function () {
    $ator = criarUsuarioComPapel('adm_igreja', 'igreja', Igreja::factory()->create()->id);
    $alvo = Usuario::factory()->create();
    $auth = ['Authorization' => 'Bearer '.$ator->createToken('t')->plainTextToken];

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => $alvo->id, 'papel' => 'super_admin',
        'tipo_escopo' => 'central', 'escopo_id' => 'c1',
    ], $auth)->assertStatus(403)->assertJsonPath('code', 'vinculo_fora_autoridade');
});

it('nega concession fora da subarvore', function () {
    $igreja = Igreja::factory()->create();
    $outra = Igreja::factory()->create();
    $ator = criarUsuarioComPapel('adm_igreja', 'igreja', $igreja->id);
    $alvo = Usuario::factory()->create();
    $auth = ['Authorization' => 'Bearer '.$ator->createToken('t')->plainTextToken];

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => $alvo->id, 'papel' => 'coordenadora',
        'tipo_escopo' => 'igreja', 'escopo_id' => $outra->id,
    ], $auth)->assertStatus(403)->assertJsonPath('code', 'vinculo_fora_autoridade');
});
