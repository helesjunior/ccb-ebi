<?php

use App\Support\Arquivo\ClienteAntivirus;
use App\Support\Arquivo\Contracts\ArmazenarArquivo;
use App\Support\Arquivo\Models\Arquivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Download de evidencia por URL assinada (B1 do review, design §6.5): o arquivo
 * so e servido apos autorizacao (`evidencia.ler`) e quando o pipeline ja o
 * promoveu a `limpo`. Nada de servir a quarentena nem arquivo infectado.
 */
beforeEach(function () {
    Storage::fake('quarentena');
    Storage::fake('laudos');

    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', (string) Str::ulid());
    $this->auth = ['Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken];

    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->authResp = ['Authorization' => 'Bearer '.$this->responsavel->createToken('t')->plainTextToken];
});

function arquivoLimpo(): Arquivo
{
    test()->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->andReturn(true));

    $arquivo = app(ArmazenarArquivo::class)->enfileirar(
        UploadedFile::fake()->createWithContent('evidencia.pdf', "%PDF-1.4\n%EOF\n"),
        'evidencia_consentimento',
        null,
    );

    return $arquivo->refresh();
}

it('devolve url assinada de curta duracao para arquivo limpo', function () {
    $arquivo = arquivoLimpo();
    expect($arquivo->status_antivirus)->toBe(Arquivo::STATUS_LIMPO);

    $this->getJson("/api/v1/arquivos/{$arquivo->id}", $this->auth)
        ->assertOk()
        ->assertJsonStructure(['url', 'expira_em']);
});

it('nao serve arquivo ainda em quarentena', function () {
    // Enfileira sem processar (nao mocka o antivirus -> fila sync processa; entao
    // forco o status para pendente criando o registro direto).
    $arquivo = Arquivo::create([
        'finalidade' => 'evidencia_consentimento',
        'nome_original' => 'x.pdf',
        'mime' => 'application/pdf',
        'tamanho' => 10,
        'chave_storage' => 'quarentena/1/x.pdf',
        'bucket' => 'quarentena',
        'cifrado' => false,
        'status_antivirus' => Arquivo::STATUS_PENDENTE,
    ]);

    $this->getJson("/api/v1/arquivos/{$arquivo->id}", $this->auth)->assertStatus(409);
});

it('nao serve arquivo infectado', function () {
    $arquivo = Arquivo::create([
        'finalidade' => 'evidencia_consentimento',
        'nome_original' => 'x.pdf',
        'mime' => 'application/pdf',
        'tamanho' => 10,
        'chave_storage' => 'laudos/1/x.pdf',
        'bucket' => 'laudos',
        'cifrado' => true,
        'status_antivirus' => Arquivo::STATUS_INFECTADO,
    ]);

    $this->getJson("/api/v1/arquivos/{$arquivo->id}", $this->auth)->assertStatus(409);
});

it('responsavel sem evidencia.ler nao baixa o arquivo (B1)', function () {
    $arquivo = arquivoLimpo();

    $this->getJson("/api/v1/arquivos/{$arquivo->id}", $this->authResp)->assertForbidden();
});

it('arquivo inexistente devolve 404', function () {
    $this->getJson('/api/v1/arquivos/'.(string) Str::ulid(), $this->auth)->assertNotFound();
});
