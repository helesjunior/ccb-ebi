<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Privacidade\Models\ManifestacaoPrivacidade;
use App\Domain\Privacidade\Models\TermoVersao;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

it('exige o aceite do termo vigente para criar a conta', function () {
    $this->postJson('/api/v1/auth/register-guardian', [
        'nome' => 'Responsável Teste', 'email' => 'resp@ebi.test', 'password' => 'senha-bem-longa-123',
    ], ['User-Agent' => 'phpunit'])->assertStatus(422);
});

it('cria a conta e registra a manifestacao com evidencia na mesma transacao', function () {
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->subDay()]);

    $this->postJson('/api/v1/auth/register-guardian', [
        'nome' => 'Responsável Teste', 'email' => 'resp2@ebi.test', 'password' => 'senha-bem-longa-123',
        'termo_versao_id' => $termo->id, 'aceite' => true,
    ], ['User-Agent' => 'phpunit'])->assertStatus(201);

    $m = ManifestacaoPrivacidade::query()->latest('id')->first();

    expect($m)->not->toBeNull();
    expect($m->decisao)->toBe('aceito');
    expect($m->origem)->toBe('proprio');
    expect($m->evidencia)->toContain('phpunit');
});

it('rejeita termo com vigencia futura e nao cria conta nem manifestacao', function () {
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->addDay()]);

    $this->postJson('/api/v1/auth/register-guardian', [
        'nome' => 'Responsável Teste', 'email' => 'futuro@ebi.test', 'password' => 'senha-bem-longa-123',
        'termo_versao_id' => $termo->id, 'aceite' => true,
    ], ['User-Agent' => 'phpunit'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'termo_indisponivel');

    expect(Usuario::query()->where('email_normalizado', 'futuro@ebi.test')->exists())->toBeFalse();
    expect(ManifestacaoPrivacidade::query()->count())->toBe(0);
});

it('rejeita termo encerrado', function () {
    $termo = TermoVersao::factory()->encerrado()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->subDay()]);

    $this->postJson('/api/v1/auth/register-guardian', [
        'nome' => 'Responsável Teste', 'email' => 'encerrado@ebi.test', 'password' => 'senha-bem-longa-123',
        'termo_versao_id' => $termo->id, 'aceite' => true,
    ], ['User-Agent' => 'phpunit'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'termo_indisponivel');

    expect(Usuario::query()->where('email_normalizado', 'encerrado@ebi.test')->exists())->toBeFalse();
});

it('rejeita quando nao existe termo de cadastro vigente', function () {
    // Nenhum termo cadastrado: o termo informado existe, mas nao ha vigente.
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->addDay()]);

    $this->postJson('/api/v1/auth/register-guardian', [
        'nome' => 'Responsável Teste', 'email' => 'semvigente@ebi.test', 'password' => 'senha-bem-longa-123',
        'termo_versao_id' => $termo->id, 'aceite' => true,
    ], ['User-Agent' => 'phpunit'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'termo_indisponivel');

    expect(Usuario::query()->where('email_normalizado', 'semvigente@ebi.test')->exists())->toBeFalse();
});
