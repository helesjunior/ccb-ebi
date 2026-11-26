<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Support\Str;

/** Finalidade obrigatoria e auditada nas leituras nominais de presenca (RNF-07). */
beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->participante = participanteNaIgreja($this->igreja, ['data_nascimento' => '2016-01-01']);
    $this->sessao = Sessao::factory()->create(['ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id]);
    Presenca::factory()->create([
        'sessao_id' => $this->sessao->id, 'participante_id' => $this->participante->id,
        'registrado_por' => $this->coord->id, 'status' => Presenca::STATUS_VALIDA,
    ]);
    $this->urlSessao = "/api/v1/sessions/{$this->sessao->id}/attendance";
    $this->urlHistorico = "/api/v1/participants/{$this->participante->id}/attendance";
});

function leiturasPresenca(): int
{
    return EventoAuditoria::query()->where('acao', 'presenca.lida')->count();
}

it('F1 lista da sessao sem finalidade responde 422', function () {
    $this->getJson($this->urlSessao, $this->auth)->assertUnprocessable()->assertJsonPath('code', 'finalidade_obrigatoria');

    expect(leiturasPresenca())->toBe(0);
});

it('F2 lista da sessao com finalidade audita presenca.lida', function () {
    $this->getJson($this->urlSessao.'?finalidade=registro_presenca', $this->auth)->assertOk()->assertJsonCount(1, 'data');

    $evento = EventoAuditoria::query()->where('acao', 'presenca.lida')->sole();
    expect($evento->motivo)->toBe('registro_presenca')
        ->and($evento->escopo)->toBe("ebi:{$this->ebi->id}")
        ->and($evento->metadados_minimizados)->toBe([
            'sessao_id' => $this->sessao->id, 'participante_ids' => [$this->participante->id],
        ]);
});

it('F3 fora do escopo e inexistente respondem 403 e 404 antes da finalidade', function () {
    $outra = igrejaComEbi();
    $sessaoB = Sessao::factory()->create(['ebi_id' => $outra['ebi']->id, 'igreja_id' => $outra['igreja']->id]);
    $participanteB = participanteNaIgreja($outra['igreja']);

    $this->getJson("/api/v1/sessions/{$sessaoB->id}/attendance", $this->auth)->assertForbidden();
    $this->getJson("/api/v1/participants/{$participanteB->id}/attendance", $this->auth)->assertForbidden();
    $this->getJson('/api/v1/sessions/'.Str::ulid().'/attendance', $this->auth)->assertNotFound();
    $this->getJson('/api/v1/participants/'.Str::ulid().'/attendance', $this->auth)->assertNotFound();

    expect(leiturasPresenca())->toBe(0);
});

it('F4 historico do operador exige finalidade e audita', function () {
    $this->getJson($this->urlHistorico, $this->auth)->assertUnprocessable()->assertJsonPath('code', 'finalidade_obrigatoria');
    $this->getJson($this->urlHistorico.'?finalidade=consulta_historico', $this->auth)->assertOk()->assertJsonCount(1, 'data');

    $evento = EventoAuditoria::query()->where('acao', 'presenca.lida')->sole();
    expect($evento->motivo)->toBe('consulta_historico')
        ->and($evento->metadados_minimizados)->toBe(['participante_id' => $this->participante->id]);
});

it('F5 responsavel le o historico do dependente sem finalidade', function () {
    $resp = criarUsuarioComPapel('responsavel');
    ResponsavelParticipante::factory()->aprovado()->create(['usuario_id' => $resp->id, 'participante_id' => $this->participante->id]);
    app('auth')->forgetGuards();

    $this->getJson("/api/v1/me/participants/{$this->participante->id}/attendance", [
        'Authorization' => 'Bearer '.$resp->createToken('t')->plainTextToken,
    ])->assertOk();

    expect(leiturasPresenca())->toBe(0);
});

it('F6 aceita a finalidade pelo header X-Finalidade', function () {
    $this->getJson($this->urlSessao, $this->auth + ['X-Finalidade' => 'registro_presenca'])->assertOk();

    expect(leiturasPresenca())->toBe(1);
});

it('F7 finalidade acima de 255 caracteres responde 422', function () {
    $this->getJson($this->urlSessao.'?finalidade='.str_repeat('a', 256), $this->auth)
        ->assertUnprocessable()->assertJsonPath('code', 'finalidade_invalida');

    expect(leiturasPresenca())->toBe(0);
});
