<?php

namespace App\Console\Commands;

use App\Domain\Organizacao\Services\ImportadorBrasilia;
use Illuminate\Console\Command;

class ImportarBrasilia extends Command
{
    protected $signature = 'organizacao:importar-brasilia {arquivo : Caminho do CSV da hierarquia}';

    protected $description = 'Importa a hierarquia organizacional de Brasilia a partir de um CSV (idempotente por codigo)';

    public function handle(ImportadorBrasilia $importador): int
    {
        $arquivo = (string) $this->argument('arquivo');

        try {
            $resultado = $importador->importar($arquivo);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Importacao concluida: %d criados, %d atualizados, %d ignorados.',
            $resultado['criados'],
            $resultado['atualizados'],
            $resultado['ignorados'],
        ));

        return self::SUCCESS;
    }
}
