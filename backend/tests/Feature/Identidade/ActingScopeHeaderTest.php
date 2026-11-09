<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

/**
 * O cabecalho X-Acting-Scope tem o formato `papel=<codigo>;escopo=<tipo>:<id>`
 * (RN-11). Este teste trava o contrato e cobre o caminho felizico com usuario
 * de multiplos vinculos — o portal precisa falar exatamente esse formato.
 */
it('aceita um X-Acting-Scope valido em rota operacional', function () {
    $u = Usuario::factory()->create([
        'senha' => Hash::make('senha-bem-longa-123'),
        'email_verificado_em' => now(),
    ]);
    $papelId = Papel::query()->where('codigo', 'adm_igreja')->value('id');
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papelId, 'tipo_escopo' => 'igreja', 'escopo_id' => 'igreja-1']);
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papelId, 'tipo_escopo' => 'igreja', 'escopo_id' => 'igreja-2']);

    $token = $u->createToken('t', ['*'])->plainTextToken;

    $this->getJson('/api/v1/me/scopes', [
        'Authorization' => "Bearer {$token}",
        'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:igreja-1',
    ])
        ->assertOk()
        ->assertJsonFragment(['escopo_id' => 'igreja-1']);
});

it('recusa um X-Acting-Scope fora dos vinculos vigentes', function () {
    $u = Usuario::factory()->create([
        'senha' => Hash::make('senha-bem-longa-123'),
        'email_verificado_em' => now(),
    ]);
    $papelId = Papel::query()->where('codigo', 'adm_igreja')->value('id');
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papelId, 'tipo_escopo' => 'igreja', 'escopo_id' => 'igreja-1']);

    $token = $u->createToken('t', ['*'])->plainTextToken;

    $this->getJson('/api/v1/me/scopes', [
        'Authorization' => "Bearer {$token}",
        'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:outra-igreja',
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'acting_scope_invalido');
});
