<?php

use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Setor;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Relatorios\Contracts\ConsultarRelatorio;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->no = arvoreRelatorio();
    $this->relatorio = app(ConsultarRelatorio::class);
    $this->inicio = CarbonImmutable::now('UTC')->subDays(5)->toDateString();
    $this->fim = CarbonImmutable::now('UTC')->toDateString();
});

function sessaoNo(string $igrejaId, string $ebiId, int $diasAtras = 1, int $hora = 13): Sessao
{
    $inicio = CarbonImmutable::now('UTC')->subDays($diasAtras)->setTime($hora, 0);

    return Sessao::factory()->create([
        'igreja_id' => $igrejaId,
        'ebi_id' => $ebiId,
        'inicio_previsto' => $inicio->format('Y-m-d H:i:s'),
        'fim_previsto' => $inicio->addHour()->format('Y-m-d H:i:s'),
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
}

function presencaNo(Sessao $sessao, Participante $participante): Presenca
{
    return Presenca::factory()->create([
        'sessao_id' => $sessao->id,
        'participante_id' => $participante->id,
        'status' => Presenca::STATUS_VALIDA,
    ]);
}

/** Cria N participantes ativos da igreja e presenca de cada um numa sessao. */
function participantesComPresenca(string $igrejaId, Sessao $sessao, int $quantidade): void
{
    for ($i = 0; $i < $quantidade; $i++) {
        $p = Participante::factory()->create(['igreja_referencia_id' => $igrejaId]);
        presencaNo($sessao, $p);
    }
}

function total(array $payload, string $indicador, ?array $totais = null): array
{
    return ($totais ?? $payload['totais'])[$indicador];
}

it('recalcula os totais da subarvore em vez de somar filhos', function () {
    $outra = igrejaNoSetor($this->no['setor']);
    $s1 = sessaoNo($this->no['igreja']->id, $this->no['ebi']->id);
    $s2 = sessaoNo($outra['igreja']->id, $outra['ebi']->id);
    // A mesma pessoa (mesma igreja de referencia) presente nas duas igrejas.
    $pessoa = Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]);
    presencaNo($s1, $pessoa);
    presencaNo($s2, $pessoa);
    participantesComPresenca($this->no['igreja']->id, $s1, 5);

    $payload = $this->relatorio->presenca('setor', $this->no['setor']->id, $this->inicio, $this->fim, false);

    // M-02 do setor conta cada pessoa uma vez e so as atribuidas as igrejas da
    // subarvore; a presenca em outra igreja nao atribui ninguem.
    expect(total($payload, 'participantes_distintos')['valor'])->toBe(6)
        ->and(total($payload, 'presencas')['valor'])->toBe(7)
        ->and(total($payload, 'sessoes_realizadas')['valor'])->toBe(2)
        ->and(total($payload, 'media_por_sessao')['valor'])->toBe(3.5)
        ->and($payload['filhos'])->toHaveCount(2)
        ->and($payload['no'])->toBe(['nivel' => 'setor', 'id' => $this->no['setor']->id, 'nome' => $this->no['setor']->nome]);
});

it('mostra zero visivel e suprime de 1 a 4 acima da igreja (sem cascata)', function () {
    participantesComPresenca($this->no['igreja']->id, sessaoNo($this->no['igreja']->id, $this->no['ebi']->id), 3);

    // A igreja tem o proprio valor 3 no limiar; o EBI dela tem 3 e tambem e
    // suprimido, entao o motivo mais forte e a cascata do filho.
    // Quem ve o valor real recebe 3.
    $suprimido = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, true);
    $real = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, false);

    // Cada linha tem o proprio valor 3; o EBI dela tambem, e a cascata dele
    // prevalece sobre o limiar da igreja.
    expect(total($suprimido, 'participantes_distintos'))->toBe(['valor' => null, 'suprimido' => true, 'motivo' => 'filho_suprimido'])
        ->and(total($suprimido, 'sessoes_realizadas'))->toBe(['valor' => 1, 'suprimido' => false, 'motivo' => null])
        ->and(total($suprimido, 'media_por_sessao'))->toBe(['valor' => null, 'suprimido' => true, 'motivo' => 'filho_suprimido'])
        ->and(total($real, 'participantes_distintos'))->toBe(['valor' => 3, 'suprimido' => false, 'motivo' => null]);
});

it('expoe a media com uma casa e null com zero sessoes', function () {
    $s1 = sessaoNo($this->no['igreja']->id, $this->no['ebi']->id);
    $s2 = sessaoNo($this->no['igreja']->id, $this->no['ebi']->id, 1, 15);
    $s3 = sessaoNo($this->no['igreja']->id, $this->no['ebi']->id, 1, 17);
    presencaNo($s1, Participante::factory()->create());
    presencaNo($s2, Participante::factory()->create());
    presencaNo($s2, Participante::factory()->create());
    presencaNo($s3, Participante::factory()->create());

    $semSessao = igrejaNoSetor($this->no['setor'], []);
    $vazio = $this->relatorio->presenca('igreja', $semSessao['igreja']->id, $this->inicio, $this->fim, false);
    $comSessao = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, false);

    expect(total($comSessao, 'media_por_sessao'))->toBe(['valor' => 1.3, 'suprimido' => false, 'motivo' => null])
        ->and(total($vazio, 'media_por_sessao'))->toBe(['valor' => null, 'suprimido' => false, 'motivo' => null])
        ->and(total($vazio, 'sessoes_realizadas'))->toBe(['valor' => 0, 'suprimido' => false, 'motivo' => null]);
});

it('cascateia a supressao do EBI ate a central, inclusive na linha do pai', function () {
    $outro = igrejaNoSetor($this->no['setor']);
    participantesComPresenca($outro['igreja']->id, sessaoNo($outro['igreja']->id, $outro['ebi']->id), 7);
    participantesComPresenca($this->no['igreja']->id, sessaoNo($this->no['igreja']->id, $this->no['ebi']->id, 1, 15), 3);

    $setor = $this->relatorio->presenca('setor', $this->no['setor']->id, $this->inicio, $this->fim, true);
    $central = $this->relatorio->presenca('central', $this->no['central']->id, $this->inicio, $this->fim, true);
    $igrejaPequena = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, true);

    // No setor, o total (10) some por causa do filho de 3; a linha da igreja
    // pequena aponta para a cascata do EBI.
    expect(total($setor, 'participantes_distintos'))->toBe(['valor' => null, 'suprimido' => true, 'motivo' => 'filho_suprimido'])
        // A contagem de sessoes nao identifica pessoa: ela nunca some nem herda
        // cascata.
        ->and(total($setor, 'sessoes_realizadas'))->toBe(['valor' => 2, 'suprimido' => false, 'motivo' => null])
        ->and(total($setor, 'participantes_cadastrados')['motivo'])->toBe('filho_suprimido')
        ->and(collect($setor['filhos'])->firstWhere('id', $this->no['igreja']->id)['totais']['participantes_distintos']['motivo'])->toBe('filho_suprimido')
        ->and(total($central, 'participantes_distintos')['motivo'])->toBe('filho_suprimido')
        ->and(total($central, 'media_por_sessao')['motivo'])->toBe('filho_suprimido')
        // Mesmo no aberto, a igreja pequena continua suprimida pela cascata do EBI.
        ->and(total($igrejaPequena, 'participantes_distintos')['motivo'])->toBe('filho_suprimido');
});

it('nao suprime nada quando todos os valores sao 5 ou mais', function () {
    // Cinco sessoes com cinco pessoas cada: nenhum indicador de contagem no
    // limiar (1..4) e, portanto, nenhuma cascata.
    for ($dia = 1; $dia <= 5; $dia++) {
        $hora = 8 + $dia * 2;
        $s = sessaoNo($this->no['igreja']->id, $this->no['ebi']->id, 3, $hora);
        participantesComPresenca($this->no['igreja']->id, $s, 5);
    }

    $payload = $this->relatorio->presenca('central', $this->no['central']->id, $this->inicio, $this->fim, true);

    expect(total($payload, 'participantes_distintos'))->toBe(['valor' => 25, 'suprimido' => false, 'motivo' => null])
        ->and(total($payload, 'presencas'))->toBe(['valor' => 25, 'suprimido' => false, 'motivo' => null])
        ->and(total($payload, 'sessoes_realizadas'))->toBe(['valor' => 5, 'suprimido' => false, 'motivo' => null])
        ->and(total($payload, 'media_por_sessao'))->toBe(['valor' => 5.0, 'suprimido' => false, 'motivo' => null])
        ->and(total($payload, 'participantes_cadastrados')['valor'])->toBe(25)
        ->and(total($payload, 'igrejas_com_ebi_ativo'))->toBe(['valor' => 1, 'suprimido' => false, 'motivo' => null]);
});

it('nunca suprime igrejas com EBI ativo nem o usa para cascatear', function () {
    // Igreja com EBI ativo e nenhuma presenca: M-09 = 1, pessoas = 0.
    sessaoNo($this->no['igreja']->id, $this->no['ebi']->id);

    $igreja = $this->relatorio->presenca('igreja', $this->no['igreja']->id, $this->inicio, $this->fim, true);
    $campanha = $this->relatorio->presenca('central', $this->no['central']->id, $this->inicio, $this->fim, true);
    $setor = $this->relatorio->presenca('setor', $this->no['setor']->id, $this->inicio, $this->fim, true);

    expect(total($igreja, 'igrejas_com_ebi_ativo'))->toBe(['valor' => 1, 'suprimido' => false, 'motivo' => null])
        ->and(total($setor, 'igrejas_com_ebi_ativo'))->toBe(['valor' => 1, 'suprimido' => false, 'motivo' => null])
        // A igreja tem EBI ativo; M-09 conta igrejas, nao pessoas, e por isso
        // nunca e suprimido nem sofre cascata, em nenhum nivel.
        ->and(total($campanha, 'igrejas_com_ebi_ativo'))->toBe(['valor' => 1, 'suprimido' => false, 'motivo' => null])
        // Sem presenca de ninguem, nao ha contagem pequena a esconder.
        ->and(total($setor, 'participantes_distintos'))->toBe(['valor' => 0, 'suprimido' => false, 'motivo' => null])
        ->and(total($setor, 'sessoes_realizadas'))->toBe(['valor' => 1, 'suprimido' => false, 'motivo' => null]);
});

it('marca M-01 e ativos como nao se aplica no EBI', function () {
    $payload = $this->relatorio->presenca('ebi', $this->no['ebi']->id, $this->inicio, $this->fim, true);

    expect(total($payload, 'participantes_cadastrados'))->toBe(['valor' => null, 'suprimido' => false, 'motivo' => 'nao_se_aplica'])
        ->and(total($payload, 'participantes_ativos'))->toBe(['valor' => null, 'suprimido' => false, 'motivo' => 'nao_se_aplica'])
        ->and(total($payload, 'igrejas_com_ebi_ativo'))->toBe(['valor' => null, 'suprimido' => false, 'motivo' => 'nao_se_aplica'])
        ->and($payload['filhos'])->toBe([]);
});

it('conta cadastrados e ativos pela igreja de referencia', function () {
    Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]);
    Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id, 'status' => 'inativo']);
    $semReferencia = Participante::factory()->create(['igreja_referencia_id' => null]);
    presencaNo(sessaoNo($this->no['igreja']->id, $this->no['ebi']->id), $semReferencia);

    $payload = $this->relatorio->presenca('central', $this->no['central']->id, $this->inicio, $this->fim, false);

    expect(total($payload, 'participantes_cadastrados')['valor'])->toBe(1)
        ->and(total($payload, 'participantes_ativos')['valor'])->toBe(1);
});

it('migra os numeros quando a igreja muda de setor', function () {
    $s = sessaoNo($this->no['igreja']->id, $this->no['ebi']->id);
    participantesComPresenca($this->no['igreja']->id, $s, 5);
    $novoSetor = Setor::factory()->create(['regional_id' => $this->no['regional']->id]);

    IgrejaVinculacao::query()->where('igreja_id', $this->no['igreja']->id)->update(['fim' => CarbonImmutable::now()->subDay()->toDateString()]);
    IgrejaVinculacao::query()->create([
        'igreja_id' => $this->no['igreja']->id,
        'setor_id' => $novoSetor->id,
        'inicio' => CarbonImmutable::now()->toDateString(),
        'fim' => null,
    ]);

    $antigo = $this->relatorio->presenca('setor', $this->no['setor']->id, $this->inicio, $this->fim, false);
    $novo = $this->relatorio->presenca('setor', $novoSetor->id, $this->inicio, $this->fim, false);

    expect(total($antigo, 'participantes_distintos')['valor'])->toBe(0)
        ->and(total($novo, 'participantes_distintos')['valor'])->toBe(5);
});
