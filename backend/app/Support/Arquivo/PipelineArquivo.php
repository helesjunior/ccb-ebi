<?php

namespace App\Support\Arquivo;

use App\Support\Arquivo\Contracts\ArmazenarArquivo;
use App\Support\Arquivo\Jobs\ProcessarArquivoJob;
use App\Support\Arquivo\Models\Arquivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pipeline de armazenamento (E08). O upload vai para a `quarentena` (sem cifra)
 * e um job processa: valida o tipo real, roda o antivirus e, se limpo, promove
 * para o bucket `laudos` (cifrado). Infectado ou invalido: apaga da quarentena.
 */
class PipelineArquivo implements ArmazenarArquivo
{
    public function enfileirar(UploadedFile $arquivo, string $finalidade, ?string $enviadoPor): Arquivo
    {
        $chave = 'quarentena/'.Str::ulid().'/'.$arquivo->getClientOriginalName();

        $conteudo = $arquivo->get();

        Storage::disk('quarentena')->put($chave, $conteudo === false ? '' : $conteudo);

        $registro = Arquivo::create([
            'finalidade' => $finalidade,
            'nome_original' => $arquivo->getClientOriginalName(),
            'mime' => $arquivo->getMimeType(),
            'tamanho' => $arquivo->getSize(),
            'chave_storage' => $chave,
            'bucket' => 'quarentena',
            'cifrado' => false,
            'status_antivirus' => Arquivo::STATUS_PENDENTE,
            'enviado_por' => $enviadoPor,
        ]);

        ProcessarArquivoJob::dispatch($registro->id);

        return $registro;
    }

    public function urlAssinada(Arquivo $arquivo, int $minutos = 5): string
    {
        return Storage::disk('laudos')->temporaryUrl(
            $arquivo->chave_storage,
            now()->addMinutes($minutos),
        );
    }
}
