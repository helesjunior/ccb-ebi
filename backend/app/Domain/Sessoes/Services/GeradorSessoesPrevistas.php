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
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
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
        try {
            return Cache::lock("sessoes:gerar:{$ebiId}", 60)->block(10, function () use ($ebiId, $de, $ate): ResultadoGeracao {
                $primeiroDia = CarbonImmutable::parse($de);
                $ultimoDia = CarbonImmutable::parse($ate);
                if ($ultimoDia->lt($primeiroDia) || $ultimoDia->gt($primeiroDia->addDays(84))) {
                    throw new ProblemException('intervalo_invalido', 'Dados inválidos', 422, 'O intervalo deve ser ordenado e não pode exceder 84 dias.');
                }

                $ebi = $this->ebis->dados($ebiId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);

                return DB::transaction(function () use ($ebiId, $de, $ate, $primeiroDia, $ultimoDia, $ebi): ResultadoGeracao {
                    $criadas = 0;
                    $ignoradas = 0;

                    for ($dia = $primeiroDia; $dia->lte($ultimoDia); $dia = $dia->addDay()) {
                        foreach ($this->agenda->ocorrenciasDoDia($ebiId, $dia->toDateString()) as $ocorrencia) {
                            $inicio = CarbonImmutable::parse("{$ocorrencia->data} {$ocorrencia->horaInicio}", $ebi->timezone)->utc();
                            $fim = CarbonImmutable::parse("{$ocorrencia->data} {$ocorrencia->horaFim}", $ebi->timezone)->utc();

                            $mesmoInicio = Sessao::query()->where('ebi_id', $ebiId)->where('inicio_previsto', $inicio)->exists();
                            $sobreposta = Sessao::query()->where('ebi_id', $ebiId)
                                ->where('status', '<>', Sessao::STATUS_CANCELADA)
                                ->where('inicio_previsto', '<', $fim)->where('fim_previsto', '>', $inicio)->exists();
                            if ($mesmoInicio || $sobreposta) {
                                $ignoradas++;

                                continue;
                            }

                            try {
                                // O savepoint mantém a transação utilizável após uma violação no PostgreSQL.
                                DB::transaction(fn () => Sessao::query()->create([
                                    'ebi_id' => $ebiId,
                                    'igreja_id' => $ebi->igrejaId,
                                    'agenda_ebi_id' => $ocorrencia->agendaEbiId,
                                    'inicio_previsto' => $inicio,
                                    'fim_previsto' => $fim,
                                    'status' => Sessao::STATUS_PREVISTA,
                                ]));
                            } catch (QueryException $e) {
                                $mensagem = strtolower($e->getPrevious()?->getMessage() ?? $e->getMessage());
                                $duplicada = str_contains($mensagem, 'sessao_ebi_inicio_unico')
                                    || (str_contains($mensagem, 'unique')
                                        && str_contains($mensagem, 'sessao.ebi_id')
                                        && str_contains($mensagem, 'inicio_previsto'));
                                if (! $duplicada) {
                                    throw $e;
                                }

                                $ignoradas++;

                                continue;
                            }
                            $criadas++;
                        }
                    }

                    $this->auditoria->registrar([
                        'acao' => 'sessao.gerada',
                        'recurso' => 'sessao',
                        'ator_id' => auth()->id() === null ? null : (string) auth()->id(),
                        'origem' => auth()->check() ? 'api' : 'console',
                        'escopo' => "ebi:{$ebiId}",
                        'motivo' => null,
                        'metadados_minimizados' => compact('criadas', 'ignoradas', 'de', 'ate'),
                    ]);

                    return new ResultadoGeracao($criadas, $ignoradas, $de, $ate);
                });
            });
        } catch (LockTimeoutException) {
            throw new ProblemException('geracao_em_andamento', 'Conflito', 409, 'Já existe uma geração em andamento para esta EBI.');
        }
    }
}
