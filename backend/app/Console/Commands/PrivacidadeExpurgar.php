<?php

namespace App\Console\Commands;

use App\Domain\DadosSensiveis\Contracts\ExpurgarRetencao;
use Illuminate\Console\Command;

class PrivacidadeExpurgar extends Command
{
    protected $signature = 'privacidade:expurgar {--force : Executa mesmo com a retenção desligada (uso consciente)}';

    protected $description = 'Aplica os prazos de retenção (descarte/anonimização). Desligado até o DPO aprovar (RNF-07).';

    public function handle(ExpurgarRetencao $retencao): int
    {
        $executar = (bool) config('privacidade.retencao.ativa') || (bool) $this->option('force');

        $r = $retencao->executar($executar);

        $this->info(sprintf(
            'Candidatos: %d · Expurgados: %d · Executado: %s',
            $r->candidatos, $r->expurgados, $r->executado ? 'sim' : 'não (dry-run)',
        ));

        return self::SUCCESS;
    }
}
