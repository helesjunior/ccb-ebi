<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Support\Arquivo\ClienteAntivirus;
use App\Support\Arquivo\Contracts\ArmazenarArquivo;
use App\Support\Arquivo\Jobs\ProcessarArquivoJob;
use App\Support\Arquivo\Models\Arquivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('quarentena');
    Storage::fake('laudos');
});

/** Cria um PDF valido (assinatura %PDF) minimo. */
function pdfValido(string $nome = 'laudo.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n%EOF\n");
}

it('promove para o bucket laudos quando o antivirus aprova', function () {
    $this->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->once()->andReturn(true));

    $arquivo = app(ArmazenarArquivo::class)->enfileirar(pdfValido(), 'evidencia_consentimento', null);

    ProcessarArquivoJob::dispatchSync($arquivo->id);

    $arquivo->refresh();
    expect($arquivo->status_antivirus)->toBe('limpo');
    expect($arquivo->bucket)->toBe('laudos');
    expect(Storage::disk('laudos')->exists($arquivo->chave_storage))->toBeTrue();
    expect($arquivo->sha256)->not->toBeNull();
});

it('apaga e audita arquivo infectado', function () {
    $this->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->once()->andReturn(false));

    $arquivo = app(ArmazenarArquivo::class)->enfileirar(pdfValido(), 'evidencia_consentimento', null);

    ProcessarArquivoJob::dispatchSync($arquivo->id);

    $arquivo->refresh();
    expect($arquivo->status_antivirus)->toBe('infectado');
    expect(Storage::disk('laudos')->exists($arquivo->chave_storage))->toBeFalse();
    expect(EventoAuditoria::query()->where('acao', 'arquivo.infectado')->exists())->toBeTrue();
});

it('recusa arquivo com assinatura invalida', function () {
    $this->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->never());

    $arquivo = app(ArmazenarArquivo::class)->enfileirar(
        UploadedFile::fake()->createWithContent('falso.pdf', 'nao e pdf de verdade'),
        'evidencia_consentimento',
        null,
    );

    ProcessarArquivoJob::dispatchSync($arquivo->id);

    $arquivo->refresh();
    expect($arquivo->status_antivirus)->toBe('invalido');
    expect(Storage::disk('laudos')->exists($arquivo->chave_storage))->toBeFalse();
});

it('registra o arquivo e o processa ao enfileirar (fila sync)', function () {
    $this->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->andReturn(true));

    $arquivo = app(ArmazenarArquivo::class)->enfileirar(pdfValido(), 'evidencia_consentimento', '01abc');

    // Com a fila sync, o job roda durante o enfileiramento: o registro existe e
    // o arquivo terminou no bucket cifrado.
    expect(Arquivo::query()->whereKey($arquivo->id)->exists())->toBeTrue();
    $arquivo->refresh();
    expect($arquivo->status_antivirus)->toBe('limpo');
    expect(Storage::disk('laudos')->exists($arquivo->chave_storage))->toBeTrue();
    expect(Storage::disk('quarentena')->exists($arquivo->chave_storage))->toBeFalse();
});
