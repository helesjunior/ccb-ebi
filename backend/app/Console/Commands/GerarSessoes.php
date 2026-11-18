<?php

namespace App\Console\Commands;

use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Sessoes\Contracts\GerarSessoesPrevistas;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class GerarSessoes extends Command
{
    protected $signature = 'sessoes:gerar {--dias=28 : Janela a partir de hoje}';

    protected $description = 'Gera sessões previstas para todas as EBIs ativas';

    public function handle(ConsultarEbi $ebis, GerarSessoesPrevistas $gerador): int
    {
        $dias = filter_var($this->option('dias'), FILTER_VALIDATE_INT);
        if ($dias === false || $dias < 0 || $dias > 84) {
            $this->error('A janela deve ser um número inteiro entre 0 e 84 dias.');

            return self::INVALID;
        }

        $falhas = 0;
        foreach ($ebis->todasAtivas() as $ebi) {
            $hoje = CarbonImmutable::now($ebi->timezone);

            try {
                $resultado = $gerador->gerar($ebi->id, $hoje->toDateString(), $hoje->addDays($dias)->toDateString());
                $this->line("EBI {$ebi->nome}: criadas {$resultado->criadas}, ignoradas {$resultado->ignoradas}");
            } catch (Throwable $e) {
                report($e);
                $this->error("EBI {$ebi->nome}: falha ao gerar sessoes ({$e->getMessage()})");
                $falhas++;
            }
        }

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
