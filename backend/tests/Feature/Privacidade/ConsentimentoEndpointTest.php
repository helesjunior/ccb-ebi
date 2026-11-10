<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Privacidade\Models\TermoVersao;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function usuarioCom(string $papel, string $tipo, string $escopoId): Usuario
{
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create([
        'usuario_id' => $u->id,
        'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => $tipo, 'escopo_id' => $escopoId,
    ]);

    return $u;
}

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->igreja = Igreja::factory()->create();
});

it('registra o proprio consentimento e o lista', function () {
    TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->subDay()]);
    $u = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $termoId = TermoVersao::query()->vigente('cadastro')->value('id');

    $this->postJson('/api/v1/privacy/consents', ['termo_versao_id' => $termoId, 'decisao' => 'aceito'], $auth)
        ->assertStatus(201)->assertJsonPath('decisao', 'aceito');

    $this->getJson('/api/v1/privacy/consents', $auth)
        ->assertOk()->assertJsonPath('data.0.decisao', 'aceito');
});

it('operador dentro do escopo recupera a versao do termo (CA-16)', function () {
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'versao' => '2026-11', 'vigente_em' => now()->subDay()]);
    $titular = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $eu = ['Authorization' => 'Bearer '.$titular->createToken('t', ['*'])->plainTextToken];
    $m = $this->postJson('/api/v1/privacy/consents', ['termo_versao_id' => $termo->id, 'decisao' => 'aceito'], $eu)->json();

    $admin = usuarioCom('adm_igreja', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:'.$this->igreja->id];

    // O request anterior autenticou o titular; a guarda cacheia o usuario entre
    // chamadas no mesmo teste. Esquece as guardas para o operador valer de fato.
    $this->app['auth']->forgetGuards();

    $this->getJson("/api/v1/privacy/consents/{$m['id']}", $auth)
        ->assertOk()
        ->assertJsonPath('termo_versao.versao', '2026-11');
});

it('nega a leitura a operador de outra subarvore', function () {
    $termo = TermoVersao::factory()->create(['vigente_em' => now()->subDay()]);
    $titular = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $eu = ['Authorization' => 'Bearer '.$titular->createToken('t', ['*'])->plainTextToken];
    $m = $this->postJson('/api/v1/privacy/consents', ['termo_versao_id' => $termo->id, 'decisao' => 'aceito'], $eu)->json();

    $outra = Igreja::factory()->create();
    $admin = usuarioCom('adm_igreja', 'igreja', $outra->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:'.$outra->id];

    $this->app['auth']->forgetGuards();

    $this->getJson("/api/v1/privacy/consents/{$m['id']}", $auth)
        ->assertStatus(403)
        ->assertJsonPath('code', 'consentimento_fora_escopo');
});

it('rejeita cadastro assistido (participante_id) nesta etapa', function () {
    $termo = TermoVersao::factory()->create(['vigente_em' => now()->subDay()]);
    $u = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $this->postJson('/api/v1/privacy/consents', [
        'termo_versao_id' => $termo->id,
        'decisao' => 'aceito',
        'participante_id' => (string) Str::ulid(),
    ], $auth)->assertStatus(422);
});

it('nega a leitura a papel sem a operacao consentimento.ler', function () {
    $termo = TermoVersao::factory()->create(['vigente_em' => now()->subDay()]);
    $titular = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $eu = ['Authorization' => 'Bearer '.$titular->createToken('t', ['*'])->plainTextToken];
    $m = $this->postJson('/api/v1/privacy/consents', ['termo_versao_id' => $termo->id, 'decisao' => 'aceito'], $eu)->json();

    $responsavel = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$responsavel->createToken('t', ['*'])->plainTextToken];

    $this->app['auth']->forgetGuards();

    $this->getJson("/api/v1/privacy/consents/{$m['id']}", $auth)->assertStatus(403);
});
