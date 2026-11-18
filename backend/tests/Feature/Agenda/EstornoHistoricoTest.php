<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-18 13:00:00',
        'fim_previsto' => '2026-11-18 14:30:00',
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
    $this->participante = Participante::factory()->create(['igreja_referencia_id' => $this->igreja->id]);
    $this->presenca = Presenca::factory()->create([
        'sessao_id' => $this->sessao->id,
        'participante_id' => $this->participante->id,
        'status' => Presenca::STATUS_VALIDA,
    ]);
});

it('estorna preservando o original, com motivo e auditoria', function () {
    $registradoEm = $this->presenca->registrado_em;

    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'registro em duplicidade'], $this->auth)
        ->assertOk()
        ->assertJsonPath('status', 'estornada')
        ->assertJsonPath('motivo_estorno', 'registro em duplicidade');

    $this->presenca->refresh();
    expect($this->presenca->status)->toBe(Presenca::STATUS_ESTORNADA)
        ->and($this->presenca->registrado_em->equalTo($registradoEm))->toBeTrue()
        ->and($this->presenca->estornado_por)->not->toBeNull()
        ->and(EventoAuditoria::query()->where('acao', 'presenca.estornada')->exists())->toBeTrue();
});

it('exige motivo e recusa estorno repetido', function () {
    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'ab'], $this->auth)
        ->assertStatus(422);

    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'erro'], $this->auth)->assertOk();
    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'erro'], $this->auth)
        ->assertStatus(409)->assertJsonPath('code', 'presenca_ja_estornada');
});

it('permite nova presenca depois do estorno', function () {
    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'erro'], $this->auth)->assertOk();

    $this->sessao->update(['status' => Sessao::STATUS_ABERTA]);
    $this->participante->update(['data_nascimento' => '2016-01-01']);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", [
        'participante_id' => $this->participante->id,
    ], $this->auth)->assertCreated();

    expect(Presenca::query()->where('sessao_id', $this->sessao->id)->count())->toBe(2);
});

it('aceita motivo de 500 caracteres e rejeita 501', function () {
    $url = "/api/v1/attendance/{$this->presenca->id}/reversal";
    $this->postJson($url, ['motivo' => str_repeat('a', 501)], $this->auth)->assertUnprocessable();
    $this->postJson($url, ['motivo' => str_repeat('a', 500)], $this->auth)->assertOk()
        ->assertJsonPath('motivo_estorno', str_repeat('a', 500));
});

it('autoriza historico pela igreja do participante e nao pela sessao frequentada', function () {
    $outra = igrejaComEbi();
    $this->sessao->update(['ebi_id' => $outra['ebi']->id, 'igreja_id' => $outra['igreja']->id]);
    $url = "/api/v1/participants/{$this->participante->id}/attendance";

    $this->getJson($url, $this->auth)->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.ebi.id', $outra['ebi']->id);

    $coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $outra['ebi']->id);
    app('auth')->forgetGuards();
    $this->getJson($url, [
        'Authorization' => 'Bearer '.$coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra['ebi']->id}",
    ])->assertForbidden();
});

it('historico do operador respeita o escopo e lista EBI e data', function () {
    $this->getJson("/api/v1/participants/{$this->participante->id}/attendance", $this->auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ebi.nome', $this->ebi->nome);

    $outra = igrejaComEbi()['ebi'];
    $intrusa = criarUsuarioComPapel('coordenadora', 'ebi', $outra->id);
    $auth = [
        'Authorization' => 'Bearer '.$intrusa->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra->id}",
    ];

    app('auth')->forgetGuards();
    $this->getJson("/api/v1/participants/{$this->participante->id}/attendance", $auth)->assertForbidden();
});

it('responsavel ve so o historico do proprio dependente', function () {
    $resp = criarUsuarioComPapel('responsavel');
    $auth = ['Authorization' => 'Bearer '.$resp->createToken('t')->plainTextToken];

    $this->getJson("/api/v1/me/participants/{$this->participante->id}/attendance", $auth)->assertForbidden();

    ResponsavelParticipante::factory()->create([
        'usuario_id' => $resp->id,
        'participante_id' => $this->participante->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_APROVADA,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
    ]);

    $this->getJson("/api/v1/me/participants/{$this->participante->id}/attendance", $auth)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('audita o motivo e escopo uma vez no replay idempotente', function () {
    $url = "/api/v1/attendance/{$this->presenca->id}/reversal";
    $auth = $this->auth + ['Idempotency-Key' => 'estorno-task-12'];
    $primeira = $this->postJson($url, ['motivo' => 'registro em duplicidade'], $auth)->assertOk()->json();
    $this->postJson($url, ['motivo' => 'registro em duplicidade'], $auth)->assertOk()->assertExactJson($primeira);
    $eventos = EventoAuditoria::query()->where('acao', 'presenca.estornada')->get();
    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->motivo)->toBe('registro em duplicidade')
        ->and($eventos[0]->escopo)->toBe("ebi:{$this->ebi->id}")
        ->and($eventos[0]->metadados_minimizados)->toBe([
            'presenca_id' => $this->presenca->id, 'sessao_id' => $this->sessao->id,
        ])
        ->and($this->presenca->fresh()->estornado_por)->toBe($this->coord->id)
        ->and($this->presenca->fresh()->estornado_em)->not->toBeNull();
});

it('resolve inexistentes e restringe estorno ao escopo da presenca', function () {
    $this->postJson('/api/v1/attendance/inexistente/reversal', ['motivo' => 'erro'], $this->auth)->assertNotFound();
    $this->getJson('/api/v1/participants/inexistente/attendance', $this->auth)->assertNotFound();
    $this->getJson('/api/v1/me/participants/inexistente/attendance', $this->auth)->assertNotFound();
    $outra = igrejaComEbi();
    $sessao = Sessao::factory()->create(['ebi_id' => $outra['ebi']->id, 'igreja_id' => $outra['igreja']->id]);
    $presenca = Presenca::factory()->create(['sessao_id' => $sessao->id]);
    $this->postJson("/api/v1/attendance/{$presenca->id}/reversal", ['motivo' => 'erro'], $this->auth)->assertForbidden();
    expect($presenca->fresh()->status)->toBe(Presenca::STATUS_VALIDA);
});

it('sem igreja de referencia permite historico apenas ao super admin', function () {
    $this->participante->update(['igreja_referencia_id' => null]);
    $url = "/api/v1/participants/{$this->participante->id}/attendance";
    $this->getJson($url, $this->auth)->assertForbidden();
    $admin = criarUsuarioComPapel('super_admin');
    app('auth')->forgetGuards();
    $this->getJson($url, ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken])->assertOk();
});

it('ordena historico e filtra pelo registro incluindo estornadas', function () {
    $this->presenca->update(['registrado_em' => '2026-11-19 10:00:00']);
    $antiga = Presenca::factory()->create([
        'sessao_id' => $this->sessao->id, 'participante_id' => $this->participante->id,
        'status' => Presenca::STATUS_ESTORNADA, 'registrado_em' => '2026-11-18 10:00:00',
    ]);
    $url = "/api/v1/participants/{$this->participante->id}/attendance";
    $this->getJson($url, $this->auth)->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $this->presenca->id)
        ->assertJsonPath('data.1.id', $antiga->id)
        ->assertJsonPath('data.1.status', 'estornada')
        ->assertJsonPath('data.0.inicio_previsto', $this->sessao->inicio_previsto->toIso8601String());
    $this->getJson($url.'?de=2026-11-19', $this->auth)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->presenca->id);
    $this->getJson($url.'?de=2026-11-19T09:00:00', $this->auth)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson($url.'?ate=2026-11-19T09:00:00', $this->auth)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson($url.'?ate=2026-11-18T10:00:00', $this->auth)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $antiga->id);
    $this->getJson($url.'?de=2026-11-18&ate=2026-11-19T10:00:00', $this->auth)
        ->assertOk()->assertJsonCount(2, 'data');
    $this->getJson($url.'?de=invalida', $this->auth)->assertUnprocessable();
    $this->getJson($url.'?de=2026-11-19&ate=2026-11-18', $this->auth)->assertUnprocessable();
});
