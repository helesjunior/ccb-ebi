<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Igreja;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () { $this->seed(PapelSeeder::class); $this->igreja = Igreja::factory()->create(); });

it('grava o ator na alteracao de status de usuario', function () {
    $admin = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create(['usuario_id' => $admin->id, 'papel_id' => Papel::query()->where('codigo','adm_igreja')->value('id'), 'tipo_escopo'=>'igreja','escopo_id'=>$this->igreja->id]);
    $alvo = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create(['usuario_id' => $alvo->id, 'papel_id' => Papel::query()->where('codigo','coordenadora')->value('id'), 'tipo_escopo'=>'igreja','escopo_id'=>$this->igreja->id]);

    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:'.$this->igreja->id];

    $this->patchJson("/api/v1/users/{$alvo->id}", ['status' => 'suspenso'], $auth)->assertOk();

    $evento = EventoAuditoria::query()->where('acao', 'alterar_status_usuario')->latest('id')->first();
    expect($evento->ator_id)->toBe($admin->id);
});