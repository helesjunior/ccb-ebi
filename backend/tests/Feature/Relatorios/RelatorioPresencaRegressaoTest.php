<?php

use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Services\GerenciadorMesclagem;
use App\Domain\Relatorios\Contracts\ConsultarRelatorio;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;

/**
 * Regressoes da revisao final da Etapa 7: os numeros do no EBI, do ativo
 * territorial e do M-02 nao podem vazar nem zerar.
 */
beforeEach(function () {
    $this->no = arvoreRelatorio();
    $this->relatorio = app(ConsultarRelatorio::class);
    $this->inicio = CarbonImmutable::now('UTC')->subDays(5)->toDateString();
    $this->fim = CarbonImmutable::now('UTC')->toDateString();
});

it('conta presencas e sessoes no no EBI', function () {
    // Tres pessoas, numa sessao do EBI, e uma sessao realizada sem presenca.
    $sessao = sessaoRegressao($this->no['igreja']->id, $this->no['ebi']->id);
    foreach (range(1, 3) as $i) {
        presencaDoTeste($sessao, Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]));
    }
    sessaoRegressao($this->no['igreja']->id, $this->no['ebi']->id, 2);

    $payload = $this->relatorio->presenca('ebi', $this->no['ebi']->id, $this->inicio, $this->fim, false);

    expect(indicadorDo($payload, 'presencas')['valor'])->toBe(3)
        ->and(indicadorDo($payload, 'sessoes_realizadas')['valor'])->toBe(2)
        ->and(indicadorDo($payload, 'participantes_distintos')['valor'])->toBe(3)
        ->and(indicadorDo($payload, 'media_por_sessao')['valor'])->toBe(1.5)
        ->and($payload['filhos'])->toBe([]);
});

it('deduplica mesclagem real no M-02 do no EBI', function () {
    $sessao = sessaoRegressao($this->no['igreja']->id, $this->no['ebi']->id);
    $principal = Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]);
    $duplicado = Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]);
    presencaDoTeste($sessao, $principal);
    presencaDoTeste($sessao, $duplicado);
    mesclarRegressao($principal->id, $duplicado->id);

    $payload = $this->relatorio->presenca('ebi', $this->no['ebi']->id, $this->inicio, $this->fim, false);

    expect(indicadorDo($payload, 'participantes_distintos')['valor'])->toBe(1)
        ->and(indicadorDo($payload, 'presencas')['valor'])->toBe(2);
});

it('conta no M-02 quem tem presenca na igreja mesmo com referencia em outra', function () {
    $outra = igrejaNoSetor($this->no['setor']);
    $sessao = sessaoRegressao($this->no['igreja']->id, $this->no['ebi']->id);
    // Referencia em outra igreja, presenca nesta: conta no M-02 desta.
    presencaDoTeste($sessao, Participante::factory()->create(['igreja_referencia_id' => $outra['igreja']->id]));

    $payload = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, false);

    expect(indicadorDo($payload, 'participantes_distintos')['valor'])->toBe(1)
        // M-01 continua pela igreja de referencia.
        ->and(indicadorDo($payload, 'participantes_cadastrados')['valor'])->toBe(0);
});

it('nao soma ativos de outra regional em igreja sem participantes', function () {
    $regionalVizinha = regionalRelatorio();

    // Seis ativos com presenca recente na regional vizinha.
    $sessaoVizinha = sessaoRegressao($regionalVizinha['igreja']->id, $regionalVizinha['ebi']->id);
    foreach (range(1, 6) as $i) {
        presencaDoTeste($sessaoVizinha, Participante::factory()->create([
            'igreja_referencia_id' => $regionalVizinha['igreja']->id,
        ]));
    }

    $payload = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, false);

    expect(indicadorDo($payload, 'participantes_ativos')['valor'])->toBe(0)
        ->and(indicadorDo($payload, 'participantes_cadastrados')['valor'])->toBe(0);
});

it('conta ativos so das igrejas da subarvore', function () {
    $regionalVizinha = regionalRelatorio();

    // Quatro na nossa igreja e seis na vizinha.
    $nossa = sessaoRegressao($this->no['igreja']->id, $this->no['ebi']->id);
    foreach (range(1, 4) as $i) {
        presencaDoTeste($nossa, Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]));
    }
    $vizinha = sessaoRegressao($regionalVizinha['igreja']->id, $regionalVizinha['ebi']->id);
    foreach (range(1, 6) as $i) {
        presencaDoTeste($vizinha, Participante::factory()->create([
            'igreja_referencia_id' => $regionalVizinha['igreja']->id,
        ]));
    }

    $payload = $this->relatorio->presenca('regional', $this->no['regional']->id, $this->inicio, $this->fim, false);

    expect(indicadorDo($payload, 'participantes_ativos')['valor'])->toBe(4)
        ->and(indicadorDo($payload, 'participantes_cadastrados')['valor'])->toBe(4);
});

it('nomeia e declara o fuso do EBI aberto', function () {
    $this->no['igreja']->update(['timezone' => 'America/Manaus']);
    $this->no['ebi']->update(['nome' => 'EBI Sementinha']);

    $payload = $this->relatorio->presenca('ebi', $this->no['ebi']->id, $this->inicio, $this->fim, false);

    expect($payload['no']['nome'])->toBe('EBI Sementinha')
        ->and($payload['fuso'])->toBe('America/Manaus');
});

it('declara fuso de cada igreja quando a subarvore tem fusos diferentes', function () {
    $outra = igrejaNoSetor($this->no['setor']);
    $outra['igreja']->update(['timezone' => 'America/Manaus']);

    $payload = $this->relatorio->presenca('setor', $this->no['setor']->id, $this->inicio, $this->fim, false);

    expect($payload['fuso'])->toBe('fuso de cada igreja');
});

it('nao conta como ativo quem so tem sessao ou presenca futura', function () {
    // Sessao no futuro, ja encerrada e realizada, nao qualifica ate a data de
    // geracao.
    $futuro = sessaoRegressao($this->no['igreja']->id, $this->no['ebi']->id, -5);
    presencaDoTeste($futuro, Participante::factory()->create([
        'igreja_referencia_id' => $this->no['igreja']->id,
        'created_at' => CarbonImmutable::now('UTC')->subYears(2),
    ]));

    $payload = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, false);

    expect(indicadorDo($payload, 'participantes_ativos')['valor'])->toBe(0);
});

it('conta como ativo quem tem presenca em igreja de outra subarvore', function () {
    // Presenca em outra regional qualifica; a contagem continua na referencia.
    $vizinha = regionalRelatorio();
    $sessaoVizinha = sessaoRegressao($vizinha['igreja']->id, $vizinha['ebi']->id);
    presencaDoTeste($sessaoVizinha, Participante::factory()->create([
        'igreja_referencia_id' => $this->no['igreja']->id,
        'created_at' => CarbonImmutable::now('UTC')->subYears(2),
    ]));

    $payload = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, false);

    expect(indicadorDo($payload, 'participantes_ativos')['valor'])->toBe(1);
});

it('descreve nos criterios as janelas, o EBI e o fuso', function () {
    $payload = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, false);

    expect($payload['criterios'])
        ->toContain('ate a data de geracao')
        ->toContain('No EBI')
        ->toContain('fuso da igreja');
});

/**
 * Sessao encerrada e realizada `diasAtras` dias antes de hoje, no EBI.
 */
function sessaoRegressao(string $igrejaId, string $ebiId, int $diasAtras = 1): Sessao
{
    $inicio = CarbonImmutable::now('UTC')->subDays($diasAtras)->setTime(13, 0);

    return Sessao::factory()->create([
        'igreja_id' => $igrejaId,
        'ebi_id' => $ebiId,
        'inicio_previsto' => $inicio->format('Y-m-d H:i:s'),
        'fim_previsto' => $inicio->addHour()->format('Y-m-d H:i:s'),
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
}

/**
 * Marcador de presenca valida para os testes deste arquivo.
 */
function presencaDoTeste(Sessao $sessao, Participante $participante): void
{
    Presenca::factory()->create([
        'sessao_id' => $sessao->id,
        'participante_id' => $participante->id,
        'status' => Presenca::STATUS_VALIDA,
    ]);
}

/** Valor do indicador no total do no. */
function indicadorDo(array $payload, string $indicador): array
{
    return $payload['totais'][$indicador];
}

/**
 * Segunda regional, com uma igreja (e EBI) fora da subarvore do no em teste.
 *
 * @return array{central: CentralNacional, regional: Regional, setor: Setor, igreja: Igreja, ebi: Ebi}
 */
function regionalRelatorio(): array
{
    return arvoreRelatorio();
}

function mesclarRegressao(string $principalId, string $duplicadoId): void
{
    app(GerenciadorMesclagem::class)->mesclar('ator-teste', $principalId, $duplicadoId);
}
