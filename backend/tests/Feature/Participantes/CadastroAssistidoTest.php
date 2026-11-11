<?php

use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Privacidade\Models\ManifestacaoPrivacidade;
use App\Domain\Privacidade\Models\TermoVersao;
use App\Support\Arquivo\ClienteAntivirus;
use App\Support\Arquivo\Models\Arquivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('quarentena');
    Storage::fake('laudos');
    $this->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->andReturn(true));

    // Termo de cadastro vigente (CA-16).
    TermoVersao::factory()->create([
        'finalidade' => TermoVersao::FINALIDADE_CADASTRO,
        'vigente_em' => now()->subDay(),
        'encerrado_em' => null,
    ]);

    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', (string) Str::ulid());
    $this->auth = ['Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken];
});

it('cadastro assistido exige evidencia e grava origem assistido', function () {
    $resposta = $this->post('/api/v1/participants/assisted', [
        'nome' => 'Davi Souza',
        'data_nascimento' => '2015-04-01',
        'responsavel_nome' => 'Marta Souza',
        'tipo_relacao' => 'mae',
        'evidencia' => UploadedFile::fake()->createWithContent('termo.pdf', "%PDF-1.4\n%EOF\n"),
        'Idempotency-Key' => (string) Str::uuid(),
    ], $this->auth)
        ->assertCreated()
        ->assertJsonPath('origem_cadastro', 'assistido')
        ->json();

    $participante = Participante::query()->findOrFail($resposta['id']);
    expect($participante->evidencia_consentimento_id)->not->toBeNull();

    // O responsavel do cadastro assistido existe sem conta (usuario_id nulo).
    $vinculo = ResponsavelParticipante::query()->where('participante_id', $participante->id)->firstOrFail();
    expect($vinculo->usuario_id)->toBeNull();

    // O arquivo de evidencia foi criado.
    expect(Arquivo::query()->whereKey($participante->evidencia_consentimento_id)->exists())->toBeTrue();
});

it('cadastro assistido sem evidencia e recusado', function () {
    $this->post('/api/v1/participants/assisted', [
        'nome' => 'Sem Evidencia',
        'data_nascimento' => '2015-04-01',
        'responsavel_nome' => 'Marta Souza',
        'tipo_relacao' => 'mae',
        'Idempotency-Key' => (string) Str::uuid(),
    ], $this->auth)->assertStatus(422);
});

it('registra consentimento do cadastro assistido com usuario nulo', function () {
    $resposta = $this->post('/api/v1/participants/assisted', [
        'nome' => 'Eva Lima',
        'data_nascimento' => '2015-04-01',
        'responsavel_nome' => 'Marta Lima',
        'tipo_relacao' => 'mae',
        'evidencia' => UploadedFile::fake()->createWithContent('termo.pdf', "%PDF-1.4\n%EOF\n"),
        'Idempotency-Key' => (string) Str::uuid(),
    ], $this->auth)->assertCreated()->json();

    $manifestacao = ManifestacaoPrivacidade::query()
        ->where('participante_id', $resposta['id'])
        ->first();

    expect($manifestacao)->not->toBeNull();
    expect($manifestacao->usuario_id)->toBeNull();
    expect($manifestacao->origem)->toBe('assistido');
});
