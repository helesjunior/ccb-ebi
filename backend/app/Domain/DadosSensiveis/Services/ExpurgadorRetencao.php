<?php

namespace App\Domain\DadosSensiveis\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\DadosSensiveis\Contracts\ExpurgarRetencao;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use App\Domain\DadosSensiveis\Support\ResultadoRetencao;
use Illuminate\Support\Facades\DB;

class ExpurgadorRetencao implements ExpurgarRetencao
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function executar(bool $executar = false): ResultadoRetencao
    {
        return DB::transaction(function () use ($executar): ResultadoRetencao {
            $candidatos = DadoSensivelParticipante::query()
                ->expiraveis(now())
                ->get();

            // Sem ordem explícita de execução (dry-run), nada é escrito: apenas
            // devolve o relatório. O desligamento por padrão
            // (`config('privacidade.retencao.ativa')` falso) é resolvido pelo
            // comando, que só passa `$executar=true` com a retenção ligada ou
            // com `--force` — assim o serviço pode ser testado/acionado
            // explicitamente sem depender da config de ambiente.
            if (! $executar) {
                return new ResultadoRetencao($candidatos->count(), 0, false);
            }

            $expurgados = 0;

            foreach ($candidatos as $dado) {
                $dado->update([
                    'expurgado_em' => now(),
                    'conteudo_cifrado' => null,
                    'referencia_segura' => null,
                ]);

                $this->auditoria->registrar([
                    'acao' => 'retencao.expurgar',
                    'recurso' => 'dado_sensivel_participante',
                    'ator_id' => null,
                    'origem' => 'sistema',
                    'metadados_minimizados' => ['dado_id' => $dado->id, 'tipo' => $dado->tipo],
                ]);

                $expurgados++;
            }

            return new ResultadoRetencao($candidatos->count(), $expurgados, true);
        });
    }
}
