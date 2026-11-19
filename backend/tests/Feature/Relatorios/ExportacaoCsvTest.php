<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->no = arvoreRelatorio();
    $this->inicio = CarbonImmutable::now('UTC')->subDays(5)->toDateString();
    $this->fim = CarbonImmutable::now('UTC')->toDateString();
    $this->auth = function (string $papel, string $tipoEscopo, string $escopoId): array {
        $usuario = criarUsuarioComPapel($papel, $tipoEscopo, $escopoId);

        return [
            'Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken,
            'X-Acting-Scope' => "papel={$papel};escopo={$tipoEscopo}:{$escopoId}",
        ];
    };
});

function linhaCsv(string $corpo): array
{
    $linhas = array_values(array_filter(explode("\n", trim($corpo)), fn (string $l): bool => $l !== ''));
    $cabecalho = str_getcsv(array_shift($linhas));

    return array_map(fn (string $linha): array => array_combine($cabecalho, str_getcsv($linha)), $linhas);
}

it('exporta uma linha para o no e uma por filho, com valores iguais aos do json', function () {
    $sessao = Sessao::factory()->create([
        'igreja_id' => $this->no['igreja']->id,
        'ebi_id' => $this->no['ebi']->id,
        'inicio_previsto' => CarbonImmutable::now('UTC')->subDays(2)->setTime(14, 0)->format('Y-m-d H:i:s'),
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);

    for ($i = 0; $i < 6; $i++) {
        $participante = Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]);
        Presenca::factory()->create([
            'sessao_id' => $sessao->id,
            'participante_id' => $participante->id,
            'status' => Presenca::STATUS_VALIDA,
        ]);
    }

    $auth = ($this->auth)('adm_regional', 'regional', $this->no['regional']->id);
    $consulta = "/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}";

    $json = $this->getJson($consulta, $auth)->assertOk()->json();

    $resposta = $this->get($consulta.'&formato=csv', $auth)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $linhas = linhaCsv($resposta->getContent());

    expect($linhas)->toHaveCount(1 + count($json['filhos']))
        ->and($linhas[0]['nivel'])->toBe('igreja')
        ->and($linhas[0]['id'])->toBe($this->no['igreja']->id)
        ->and($linhas[0]['participantes_distintos'])->toBe('6')
        ->and($linhas[0]['sessoes_realizadas'])->toBe('1')
        ->and($linhas[0]['media_por_sessao'])->toBe('6.0')
        ->and($linhas[0]['periodo_inicio'])->toBe($this->inicio)
        ->and($linhas[0]['periodo_fim'])->toBe($this->fim)
        ->and($linhas[0]['fuso'])->toBe($json['fuso'])
        ->and($linhas[0]['gerado_em'])->toBe($json['gerado_em'])
        ->and($linhas[0]['criterios'])->toBe($json['criterios'])
        ->and($linhas[1]['nivel'])->toBe('ebi')
        ->and($linhas[1]['id'])->toBe($this->no['ebi']->id);
});

it('nao leva numero suprimido nem total escondido para quem atua acima da igreja', function () {
    $sessao = Sessao::factory()->create([
        'igreja_id' => $this->no['igreja']->id,
        'ebi_id' => $this->no['ebi']->id,
        'inicio_previsto' => CarbonImmutable::now('UTC')->subDays(2)->setTime(14, 0)->format('Y-m-d H:i:s'),
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);

    for ($i = 0; $i < 3; $i++) {
        $participante = Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]);
        Presenca::factory()->create([
            'sessao_id' => $sessao->id,
            'participante_id' => $participante->id,
            'status' => Presenca::STATUS_VALIDA,
        ]);
    }

    $auth = ($this->auth)('ministerio_regional', 'regional', $this->no['regional']->id);

    $linhas = linhaCsv($this->get(
        "/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}&formato=csv",
        $auth,
    )->assertOk()->getContent());

    // O EBI com 3 pessoas suprime a contagem de pessoas da igreja por cascata;
    // o CSV herda o mesmo motivo do JSON.
    expect($linhas[0]['participantes_distintos'])->toBe('Não divulgado')
        ->and($linhas[0]['presencas'])->toBe('Não divulgado')
        ->and($linhas[0]['media_por_sessao'])->toBe('Não divulgado')
        // A contagem de sessoes nao identifica pessoa e segue visivel.
        ->and($linhas[0]['sessoes_realizadas'])->toBe('1');
});

it('nao leva numero pequeno para quem atua acima da igreja', function () {
    $sessao = Sessao::factory()->create([
        'igreja_id' => $this->no['igreja']->id,
        'ebi_id' => $this->no['ebi']->id,
        'inicio_previsto' => CarbonImmutable::now('UTC')->subDays(2)->setTime(14, 0)->format('Y-m-d H:i:s'),
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);

    for ($i = 0; $i < 3; $i++) {
        $participante = Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]);
        Presenca::factory()->create([
            'sessao_id' => $sessao->id,
            'participante_id' => $participante->id,
            'status' => Presenca::STATUS_VALIDA,
        ]);
    }

    $auth = ($this->auth)('adm_regional', 'regional', $this->no['regional']->id);
    $consulta = "/api/v1/reports/attendance?nivel=setor&id={$this->no['setor']->id}&inicio={$this->inicio}&fim={$this->fim}";

    $filho = collect($this->getJson($consulta, $auth)->assertOk()->json('filhos'))
        ->firstWhere('id', $this->no['igreja']->id);

    $linhas = linhaCsv($this->get($consulta.'&formato=csv', $auth)->assertOk()->getContent());
    $igreja = collect($linhas)->firstWhere('id', $this->no['igreja']->id);

    // Todas as igrejas vazias da arvore recebem a cascata dos proprios EBIs:
    // nada de numero pequeno sai no CSV.
    expect($filho['totais']['participantes_distintos']['suprimido'])->toBeTrue()
        ->and($igreja['participantes_distintos'])->toBe('Não divulgado')
        ->and($igreja['presencas'])->toBe('Não divulgado')
        ->and($igreja['media_por_sessao'])->toBe('Não divulgado')
        ->and($igreja['participantes_cadastrados'])->toBe('Não divulgado')
        // A contagem de sessoes nao identifica pessoa e segue visivel.
        ->and($igreja['sessoes_realizadas'])->toBe('1');
});

it('mostra o numero real da igreja para quem ve a igreja', function () {
    $sessao = Sessao::factory()->create([
        'igreja_id' => $this->no['igreja']->id,
        'ebi_id' => $this->no['ebi']->id,
        'inicio_previsto' => CarbonImmutable::now('UTC')->subDays(2)->setTime(14, 0)->format('Y-m-d H:i:s'),
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);

    for ($i = 0; $i < 3; $i++) {
        $participante = Participante::factory()->create(['igreja_referencia_id' => $this->no['igreja']->id]);
        Presenca::factory()->create([
            'sessao_id' => $sessao->id,
            'participante_id' => $participante->id,
            'status' => Presenca::STATUS_VALIDA,
        ]);
    }

    $auth = ($this->auth)('coordenadora', 'igreja', $this->no['igreja']->id);

    $linhas = linhaCsv($this->get(
        "/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}&formato=csv",
        $auth,
    )->assertOk()->getContent());

    expect($linhas[0]['participantes_distintos'])->toBe('3')
        ->and($linhas[0]['sessoes_realizadas'])->toBe('1');
});

it('neutraliza texto que comecaria formula na planilha', function () {
    Igreja::query()->whereKey($this->no['igreja']->id)->update(['nome' => '=HYPERLINK("http://x")']);
    Ebi::query()->whereKey($this->no['ebi']->id)->delete();

    $auth = ($this->auth)('adm_igreja', 'igreja', $this->no['igreja']->id);

    $linhas = linhaCsv($this->get(
        "/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}&formato=csv",
        $auth,
    )->assertOk()->getContent());

    expect($linhas[0]['nome'])->toBe("'=HYPERLINK(\"http://x\")");
});

it('audita a exportacao sem nomes', function () {
    $auth = ($this->auth)('adm_igreja', 'igreja', $this->no['igreja']->id);

    $this->get(
        "/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}&formato=csv",
        $auth,
    )->assertOk();

    $evento = EventoAuditoria::query()->where('acao', 'relatorio.exportado')->sole();

    expect($evento->recurso)->toBe('relatorio')
        ->and($evento->metadados_minimizados)->toMatchArray([
            'nivel' => 'igreja',
            'id' => $this->no['igreja']->id,
            'inicio' => $this->inicio,
            'fim' => $this->fim,
        ])
        ->and(json_encode($evento->metadados_minimizados))->not->toContain($this->no['igreja']->nome);
});

it('recusa a exportacao sem relatorio.ler', function () {
    $auth = ($this->auth)('auxiliar', 'ebi', $this->no['ebi']->id);

    $this->get(
        "/api/v1/reports/attendance?inicio={$this->inicio}&fim={$this->fim}&formato=csv",
        $auth,
    )->assertForbidden();
});
