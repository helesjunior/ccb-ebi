<?php

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