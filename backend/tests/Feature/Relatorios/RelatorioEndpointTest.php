<?php

use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Setor;
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

function sessaoRelatorioNo(string $igrejaId, string $ebiId): Sessao
{
    return Sessao::factory()->create([
        'igreja_id' => $igrejaId,
        'ebi_id' => $ebiId,
        'inicio_previsto' => CarbonImmutable::now('UTC')->subDays(2)->setTime(14, 0)->format('Y-m-d H:i:s'),
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
}

function presencasNoRelatorio(Sessao $sessao, string $igrejaId, int $quantos): void
{
    for ($i = 0; $i < $quantos; $i++) {
        $participante = Participante::factory()->create(['igreja_referencia_id' => $igrejaId]);
        Presenca::factory()->create([
            'sessao_id' => $sessao->id,
            'participante_id' => $participante->id,
            'status' => Presenca::STATUS_VALIDA,
        ]);
    }
}

it('devolve o relatorio no formato da spec', function () {
    $sessao = sessaoRelatorioNo($this->no['igreja']->id, $this->no['ebi']->id);
    presencasNoRelatorio($sessao, $this->no['igreja']->id, 6);

    $auth = ($this->auth)('adm_regional', 'regional', $this->no['regional']->id);
    $resposta = $this->getJson("/api/v1/reports/attendance?nivel=setor&id={$this->no['setor']->id}&inicio={$this->inicio}&fim={$this->fim}", $auth);

    $resposta->assertOk()
        ->assertJsonPath('no.nivel', 'setor')
        ->assertJsonPath('no.id', $this->no['setor']->id)
        ->assertJsonPath('periodo.inicio', $this->inicio)
        ->assertJsonPath('periodo.fim', $this->fim)
        ->assertJsonStructure([
            'no' => ['nivel', 'id', 'nome'],
            'periodo' => ['inicio', 'fim'],
            'fuso',
            'gerado_em',
            'criterios',
            'totais' => array_fill_keys(
                ['participantes_distintos', 'presencas', 'sessoes_realizadas', 'media_por_sessao', 'participantes_cadastrados', 'participantes_ativos', 'igrejas_com_ebi_ativo'],
                ['valor', 'suprimido', 'motivo'],
            ),
            'filhos' => [['nivel', 'id', 'nome', 'totais']],
        ])
        ->assertJsonPath('totais.participantes_distintos.valor', 6)
        ->assertJsonPath('totais.sessoes_realizadas.valor', 1)
        ->assertJsonPath('filhos.0.nivel', 'igreja');
});

it('sem nivel usa o no do contexto de atuacao', function () {
    $auth = ($this->auth)('ministerio_local', 'igreja', $this->no['igreja']->id);

    $this->getJson("/api/v1/reports/attendance?inicio={$this->inicio}&fim={$this->fim}", $auth)
        ->assertOk()
        ->assertJsonPath('no.nivel', 'igreja')
        ->assertJsonPath('no.id', $this->no['igreja']->id);
});

it('responde 403 para papel sem relatorio.ler', function () {
    $auth = ($this->auth)('auxiliar', 'ebi', $this->no['ebi']->id);

    $this->getJson("/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}", $auth)
        ->assertForbidden();
});

it('responde 403 quando o contexto de atuacao foi revogado', function () {
    $usuario = criarUsuarioComPapel('adm_igreja', 'igreja', $this->no['igreja']->id);
    $auth = [
        'Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=adm_igreja;escopo=igreja:{$this->no['igreja']->id}",
    ];
    VinculoAcesso::query()->where('usuario_id', $usuario->id)->update(['fim' => now()->subDay()]);

    $this->getJson("/api/v1/reports/attendance?inicio={$this->inicio}&fim={$this->fim}", $auth)
        ->assertForbidden();
});

it('responde 404 para no inexistente', function () {
    $auth = ($this->auth)('adm_setor', 'setor', $this->no['setor']->id);

    $this->getJson("/api/v1/reports/attendance?nivel=igreja&id=01kx0000000000000000000000&inicio={$this->inicio}&fim={$this->fim}", $auth)
        ->assertNotFound();
});

it('responde 404 para no fora do escopo, mesmo existente', function () {
    ['igreja' => $outra] = igrejaNoSetor(Setor::factory()->create(['regional_id' => $this->no['regional']->id]));
    $auth = ($this->auth)('adm_setor', 'setor', $this->no['setor']->id);

    $this->getJson("/api/v1/reports/attendance?nivel=igreja&id={$outra->id}&inicio={$this->inicio}&fim={$this->fim}", $auth)
        ->assertNotFound();
});

it('valida periodo', function (array $query, string $campo) {
    $auth = ($this->auth)('adm_igreja', 'igreja', $this->no['igreja']->id);

    $this->getJson('/api/v1/reports/attendance?'.http_build_query($query), $auth)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);
})->with([
    'inicio ausente' => [['fim' => '2026-11-30'], 'inicio'],
    'fim ausente' => [['inicio' => '2026-11-01'], 'fim'],
    'fim antes do inicio' => [['inicio' => '2026-11-30', 'fim' => '2026-11-01'], 'fim'],
    'mais de 366 dias' => [['inicio' => '2024-01-01', 'fim' => '2026-11-30'], 'fim'],
    'nivel invalido' => [['inicio' => '2026-11-01', 'fim' => '2026-11-30', 'nivel' => 'bairro'], 'nivel'],
]);

it('suprime de 1 a 4 para quem atua acima da igreja', function () {
    // Um so EBI na igreja: nenhum EBI vazio fornece a cascata, entao a igreja
    // aparece pelo proprio limiar.
    Presenca::query()->delete();
    Ebi::query()->where('igreja_id', '!=', $this->no['igreja']->id)->delete();
    Participante::query()->delete();
    $sessao = sessaoRelatorioNo($this->no['igreja']->id, $this->no['ebi']->id);
    presencasNoRelatorio($sessao, $this->no['igreja']->id, 3);

    // Dois usuarios no mesmo teste: o guard do Sanctum guarda o usuario da
    // requisicao anterior, entao cada cenario com usuario proprio e um teste.
    $acima = ($this->auth)('ministerio_regional', 'regional', $this->no['regional']->id);

    $this->getJson("/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}", $acima)
        ->assertOk()
        ->assertJsonPath('totais.participantes_distintos.suprimido', true)
        ->assertJsonPath('totais.participantes_distintos.valor', null);

});

it('mostra de 1 a 4 para quem ve a igreja dentro do proprio territorio', function () {
    $sessao = sessaoRelatorioNo($this->no['igreja']->id, $this->no['ebi']->id);
    presencasNoRelatorio($sessao, $this->no['igreja']->id, 3);

    $naIgreja = ($this->auth)('coordenadora', 'igreja', $this->no['igreja']->id);

    $this->getJson("/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}", $naIgreja)
        ->assertOk()
        ->assertJsonPath('totais.participantes_distintos.valor', 3)
        ->assertJsonPath('totais.participantes_distintos.suprimido', false);
});

it('suprime com papel de igreja atuando em escopo acima da igreja', function () {
    $sessao = sessaoRelatorioNo($this->no['igreja']->id, $this->no['ebi']->id);
    presencasNoRelatorio($sessao, $this->no['igreja']->id, 2);

    $auth = ($this->auth)('adm_igreja', 'setor', $this->no['setor']->id);

    $this->getJson("/api/v1/reports/attendance?nivel=igreja&id={$this->no['igreja']->id}&inicio={$this->inicio}&fim={$this->fim}", $auth)
        ->assertOk()
        ->assertJsonPath('totais.participantes_distintos.suprimido', true);
});
