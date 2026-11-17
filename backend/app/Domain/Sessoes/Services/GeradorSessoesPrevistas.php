<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Agenda\Contracts\ConsultarAgenda;
use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Sessoes\Contracts\GerarSessoesPrevistas;
use App\Domain\Sessoes\Contracts\ResultadoGeracao;
use App\Domain\Sessoes\Models\Sessao;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GeradorSessoesPrevistas implements GerarSessoesPrevistas
{
    public function __construct(
        private readonly ConsultarAgenda $agenda,
        private readonly ConsultarEbi $ebis,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function gerar(string $ebiId, string $de, string $ate): ResultadoGeracao
    {
        $primeiroDia = CarbonImmutable::createFromFormat('!Y-m-d', $de);
        $ultimoDia = CarbonImmutable::createFromFormat('!Y-m-d', $ate);
        if ($ultimoDia->lt($primeiroDia) || $ultimoDia->gt($primeiroDia->addDays(84))) {
            throw new ProblemException('intervalo_invalido', 'Dados inválidos', 422, 'O intervalo deve ser ordenado e não pode exceder 84 dias.');
        }

        $ebi = $this->ebis->dados($ebiId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);

        return DB::transaction(function () use ($ebiId, $de, $ate, $primeiroDia, $ultimoDia, $ebi): ResultadoGeracao {
            $criadas = 0;
            $ignoradas = 0;

            for ($dia = $primeiroDia; $dia->lte($ultimoDia); $dia = $dia->addDay()) {
                foreach ($this->agenda->ocorrenciasDoDia($ebiId, $dia->toDateString()) as $ocorrencia) {
                    $inicio = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$ocorrencia->data} {$ocorrencia->horaInicio}", $ebi->timezone)->utc();
                    $fim = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$ocorrencia->data} {$ocorrencia->horaFim}", $ebi->timezone)->utc();

                    $mesmoInicio = Sessao::query()->where('ebi_id', $ebiId)->where('inicio_previsto', $inicio)->exists();
                    $sobreposta = Sessao::query()->where('ebi_id', $ebiId)
                        ->where('status', '<>', Sessao::STATUS_CANCELADA)
                        ->where('inicio_previsto', '<', $fim)->where('fim_previsto', '>', $inicio)->exists();
                    if ($mesmoInicio || $sobreposta) {
                        $ignoradas++;

                        continue;
                    }

                    Sessao::query()->create([
                        'ebi_id' => $ebiId,
                        'igreja_id' => $ebi->igrejaId,
                        'agenda_ebi_id' => $ocorrencia->agendaEbiId,
                        'inicio_previsto' => $inicio,
                        'fim_previsto' => $fim,
                        'status' => Sessao::STATUS_PREVISTA,
                    ]);
                    $criadas++;
                }
            }

            $this->auditoria->registrar([
                'acao' => 'sessao.gerada',
                'recurso' => 'sessao',
                'ator_id' => auth()->id(),
                'origem' => auth()->check() ? 'api' : 'console',
                'escopo' => "ebi:{$ebiId}",
                'motivo' => null,
                'metadados_minimizados' => compact('criadas', 'ignoradas', 'de', 'ate'),
            ]);

            return new ResultadoGeracao($criadas, $ignoradas, $de, $ate);
        });
    }
}
