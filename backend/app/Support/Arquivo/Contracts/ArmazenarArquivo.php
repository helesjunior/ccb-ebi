<?php

namespace App\Support\Arquivo\Contracts;

use App\Support\Arquivo\Models\Arquivo;
use Illuminate\Http\UploadedFile;

/**
 * Superficie publica do armazenamento de arquivos. Quem envia um arquivo chama
 * `enfileirar` e recebe o registro (o processamento roda em fila, separado da
 * requisicao). O arquivo so fica disponivel apos `status_antivirus = limpo`.
 */
interface ArmazenarArquivo
{
    /**
     * Grava o arquivo na quarentena, cria o registro e despacha o processamento
     * (validacao de tipo real + antivirus + promocao para o bucket cifrado).
     */
    public function enfileirar(UploadedFile $arquivo, string $finalidade, ?string $enviadoPor): Arquivo;

    /** Registro de arquivo pelo id, ou null. Superficie para a camada HTTP. */
    public function localizar(string $id): ?Arquivo;

    /** URL assinada de curta duracao para o arquivo ja limpo. */
    public function urlAssinada(Arquivo $arquivo, int $minutos = 5): string;
}
