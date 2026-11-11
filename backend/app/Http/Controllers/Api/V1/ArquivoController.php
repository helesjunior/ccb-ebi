<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Arquivo\Contracts\ArmazenarArquivo;
use App\Support\Arquivo\Models\Arquivo;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;

/**
 * Download de evidencia/arquivo (design §6.5): o arquivo NUNCA e servido
 * diretamente; o download sai por **URL assinada de curta duracao**, apos a
 * checagem de autorizacao (`can:evidencia.ler`, territorial) e so quando o
 * pipeline ja promoveu o arquivo a `limpo` (bucket cifrado). Arquivo ainda em
 * quarentena, infectado ou invalido nao e servido (CA-06).
 */
class ArquivoController
{
    public function __construct(private readonly ArmazenarArquivo $arquivos) {}

    public function show(string $id): JsonResponse
    {
        $arquivo = $this->arquivos->localizar($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Arquivo não encontrado.');

        if ($arquivo->status_antivirus !== Arquivo::STATUS_LIMPO) {
            throw new ProblemException(
                'arquivo_indisponivel',
                'Arquivo indisponível',
                409,
                'O arquivo ainda não foi liberado (pendente, infectado ou inválido).',
            );
        }

        return response()->json([
            'url' => $this->arquivos->urlAssinada($arquivo),
            'expira_em' => now()->addMinutes(5)->toIso8601String(),
        ]);
    }
}
