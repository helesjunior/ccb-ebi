<?php

namespace App\Console\Commands;

use App\Domain\Privacidade\Contracts\GerirTermos;
use Illuminate\Console\Command;

/**
 * Publica uma nova versao de termo de privacidade (RF-04). Caminho operacional
 * para o time publicar termos sem depender de um endpoint de usuario — a
 * publicacao fecha a versao vigente da finalidade ([ADR-0030]).
 *
 * O texto vem de `--texto=` ou, se omitido, de um arquivo via `--arquivo=`.
 *
 * [ADR-0030]: ../../docs/06-arquitetura/decisoes/ADR-0030-privacidade-e-auditoria.md
 */
class PrivacidadePublicarTermo extends Command
{
    protected $signature = 'privacidade:publicar-termo
        {finalidade : Finalidade do termo (ex.: cadastro, notificacoes)}
        {--base-legal=consentimento : Base legal do termo}
        {--versao= : Versao do termo (ex.: 2026-11-01); padrao: data de hoje}
        {--texto= : Texto integral do termo}
        {--arquivo= : Caminho de arquivo com o texto (alternativa a --texto)}';

    protected $description = 'Publica uma nova versão de termo de privacidade (fecha a vigente da finalidade)';

    public function handle(GerirTermos $termos): int
    {
        $finalidade = (string) $this->argument('finalidade');
        $texto = $this->resolverTexto();

        if ($texto === null) {
            $this->error('Informe o texto com --texto=... ou --arquivo=....');

            return self::FAILURE;
        }

        $versao = (string) ($this->option('versao') ?: now()->format('Y-m-d'));

        $termo = $termos->publicar(
            $finalidade,
            (string) $this->option('base-legal'),
            $versao,
            $texto,
            now(),
        );

        $this->info(sprintf(
            'Termo publicado: %s versao %s (id %s).',
            $finalidade,
            $versao,
            $termo->id,
        ));

        return self::SUCCESS;
    }

    private function resolverTexto(): ?string
    {
        $texto = $this->option('texto');
        if (is_string($texto) && $texto !== '') {
            return $texto;
        }

        $arquivo = $this->option('arquivo');
        if (is_string($arquivo) && $arquivo !== '') {
            if (! is_file($arquivo)) {
                $this->error("Arquivo nao encontrado: {$arquivo}");

                return null;
            }

            $conteudo = file_get_contents($arquivo);

            return $conteudo === false || $conteudo === '' ? null : $conteudo;
        }

        return null;
    }
}
