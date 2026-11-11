<?php

namespace App\Support\Arquivo\Jobs;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Support\Arquivo\ClienteAntivirus;
use App\Support\Arquivo\Models\Arquivo;
use App\Support\Arquivo\ValidadorTipoArquivo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Processa um arquivo da quarentena: valida o tipo real, roda o antivirus e
 * promove para o bucket cifrado (`laudos`) se limpo; infectado/invalido e
 * apagado e auditado. Roda na fila `default` (Horizon).
 */
class ProcessarArquivoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $arquivoId) {}

    public function handle(ClienteAntivirus $antivirus, ValidadorTipoArquivo $validador, RegistrarEvento $auditoria): void
    {
        $arquivo = Arquivo::query()->find($this->arquivoId);

        if ($arquivo === null || $arquivo->status_antivirus !== Arquivo::STATUS_PENDENTE) {
            return;
        }

        $conteudo = Storage::disk('quarentena')->get($arquivo->chave_storage);

        if ($conteudo === null) {
            return;
        }

        $mime = $validador->mimeReal($conteudo);

        if ($mime === null) {
            $this->rejeitar($arquivo, $auditoria, 'arquivo.invalido', 'invalido');

            return;
        }

        if (! $antivirus->scan($conteudo)) {
            $this->rejeitar($arquivo, $auditoria, 'arquivo.infectado', 'infectado');

            return;
        }

        // Limpo: promove para o bucket cifrado e apaga da quarentena.
        $destino = 'laudos/'.basename($arquivo->chave_storage);
        Storage::disk('laudos')->put($destino, $conteudo);
        Storage::disk('quarentena')->delete($arquivo->chave_storage);

        $arquivo->fill([
            'chave_storage' => $destino,
            'bucket' => 'laudos',
            'cifrado' => true,
            'mime' => $mime,
            'sha256' => hash('sha256', $conteudo),
            'status_antivirus' => Arquivo::STATUS_LIMPO,
        ])->save();

        $this->registrar($auditoria, 'arquivo.limpo', $arquivo);
    }

    private function rejeitar(Arquivo $arquivo, RegistrarEvento $auditoria, string $acao, string $status): void
    {
        Storage::disk('quarentena')->delete($arquivo->chave_storage);

        $arquivo->fill(['status_antivirus' => $status])->save();

        $this->registrar($auditoria, $acao, $arquivo);
    }

    private function registrar(RegistrarEvento $auditoria, string $acao, Arquivo $arquivo): void
    {
        $auditoria->registrar([
            'acao' => $acao,
            'recurso' => 'arquivo',
            'ator_id' => $arquivo->enviado_por,
            'origem' => 'sistema',
            'escopo' => null,
            'motivo' => null,
            'metadados_minimizados' => [
                'arquivo_id' => (string) $arquivo->id,
                'finalidade' => $arquivo->finalidade,
            ],
            'ocorrido_em' => now(),
        ]);
    }
}
