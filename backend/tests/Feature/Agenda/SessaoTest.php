<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use App\Domain\Sessoes\Models\SessaoCorrecao;
use App\Domain\Sessoes\Services\CalculadoraEquipeMinima;

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
        'status' => Sessao::STATUS_PREVISTA,
    ]);
});

it('abre, encerra e recusa transicoes invalidas', function () {
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/open", [], $this->auth)
        ->assertOk()->assertJsonPath('status', 'aberta');
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/open", [], $this->auth)
        ->assertStatus(409)->assertJsonPath('code', 'sessao_em_estado_invalido');
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/close", [], $this->auth)
        ->assertStatus(422);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/close", ['realizada' => false], $this->auth)
        ->assertOk()->assertJsonPath('status', 'encerrada')->assertJsonPath('realizada', false);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/cancel", ['motivo' => 'sem equipe'], $this->auth)
        ->assertStatus(409);
});

it('cancela prevista com motivo e audita', function () {
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/cancel", ['motivo' => 'sem equipe'], $this->auth)
        ->assertOk()->assertJsonPath('status', 'cancelada');
    expect($this->sessao->refresh()->motivo_cancelamento)->toBe('sem equipe')
        ->and(EventoAuditoria::query()->where('acao', 'sessao.cancelada')->exists())->toBeTrue();
});

it('corrigir encerrada exige motivo e guarda antes/depois', function () {
    $this->sessao->update(['status' => Sessao::STATUS_ENCERRADA, 'realizada' => true, 'fim_real' => now()]);
    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", ['tema' => 'Novo tema'], $this->auth)
        ->assertStatus(422)->assertJsonPath('code', 'motivo_obrigatorio');
    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", [
        'tema' => 'Novo tema', 'motivo' => 'erro de digitacao',
    ], $this->auth)->assertOk()->assertJsonPath('tema', 'Novo tema');
    $correcao = SessaoCorrecao::query()->firstOrFail();
    expect($correcao->antes['tema'])->not->toBe('Novo tema')
        ->and($correcao->depois['tema'])->toBe('Novo tema')
        ->and(EventoAuditoria::query()->where('acao', 'sessao.corrigida')->exists())->toBeTrue();
});

it('detalhe traz a equipe minima sem bloquear', function () {
    $this->sessao->update(['status' => Sessao::STATUS_ABERTA, 'auxiliares_escalados' => 2]);
    Presenca::factory()->count(9)->create(['sessao_id' => $this->sessao->id, 'status' => Presenca::STATUS_VALIDA]);
    $this->getJson("/api/v1/sessions/{$this->sessao->id}", $this->auth)
        ->assertOk()
        ->assertJsonPath('equipe_minima.presentes', 9)
        ->assertJsonPath('equipe_minima.auxiliares_necessarios', 3)
        ->assertJsonPath('equipe_minima.auxiliares_informados', 2)
        ->assertJsonPath('equipe_minima.alerta', true);
});

it('calcula equipe minima quando nao ha valor informado', function () {
    $calc = new CalculadoraEquipeMinima;
    expect($calc->calcular(9, 4, null))->toMatchArray(['auxiliares_necessarios' => 3, 'alerta' => false])
        ->and($calc->calcular(0, 4, 1))->toMatchArray(['auxiliares_necessarios' => 0, 'alerta' => false])
        ->and($calc->calcular(1, 4, 0))->toMatchArray(['auxiliares_necessarios' => 1, 'alerta' => true]);
});

it('coordenadora de outra EBI nao abre a sessao', function () {
    $outra = igrejaComEbi()['ebi'];
    $intrusa = criarUsuarioComPapel('coordenadora', 'ebi', $outra->id);
    $auth = [
        'Authorization' => 'Bearer '.$intrusa->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra->id}",
    ];
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/open", [], $auth)->assertForbidden();
});

it('lista por intervalo e status com nome e somente presencas validas', function () {
    Presenca::factory()->create(['sessao_id' => $this->sessao->id, 'status' => Presenca::STATUS_VALIDA]);
    Presenca::factory()->create(['sessao_id' => $this->sessao->id, 'status' => Presenca::STATUS_ESTORNADA]);
    Sessao::factory()->create(['ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id, 'inicio_previsto' => '2026-11-18 15:00:00', 'fim_previsto' => '2026-11-18 16:00:00', 'status' => Sessao::STATUS_CANCELADA]);
    $this->getJson("/api/v1/ebis/{$this->ebi->id}/sessions?de=2026-11-18&ate=2026-11-18&status=prevista", $this->auth)
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->sessao->id)
        ->assertJsonPath('0.ebi_nome', $this->ebi->nome)->assertJsonPath('0.presentes', 1)
        ->assertJsonPath('0.inicio_previsto', '2026-11-18T13:00:00+00:00');
    $this->getJson("/api/v1/ebis/{$this->ebi->id}/sessions?de=2026-01-01&ate=2026-12-31", $this->auth)->assertStatus(422);
    $this->getJson("/api/v1/ebis/{$this->ebi->id}/sessions?de=invalida&ate=2026-12-31", $this->auth)->assertStatus(422);
});

it('valida motivo e ordem dos instantes e audita abertura e encerramento', function () {
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/cancel", [], $this->auth)->assertStatus(422);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/open", [], $this->auth)->assertOk();
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/close", ['realizada' => true, 'fim_real' => '2000-01-01T00:00:00Z'], $this->auth)->assertStatus(422);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/close", ['realizada' => true], $this->auth)->assertOk();
    expect($this->sessao->refresh()->inicio_real)->not->toBeNull()
        ->and($this->sessao->fim_real)->not->toBeNull();
    $evento = EventoAuditoria::where('acao', 'sessao.encerrada')->firstOrFail();
    expect($evento->escopo)->toBe("ebi:{$this->ebi->id}")
        ->and($evento->metadados_minimizados['realizada'])->toBeTrue()
        ->and(EventoAuditoria::where('acao', 'sessao.aberta')->exists())->toBeTrue();
});

it('corrige somente campos alterados e recusa cancelada', function () {
    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", ['tema' => 'Tema', 'auxiliares_escalados' => 0, 'status' => 'aberta'], $this->auth)
        ->assertOk()->assertJsonPath('status', 'prevista');
    $correcao = SessaoCorrecao::firstOrFail();
    expect($correcao->depois)->toBe(['tema' => 'Tema', 'auxiliares_escalados' => 0]);
    $this->sessao->update(['status' => Sessao::STATUS_CANCELADA]);
    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", ['tema' => 'Outro'], $this->auth)
        ->assertStatus(409)->assertJsonPath('code', 'sessao_em_estado_invalido');
});

it('retorna 404 para sessao inexistente', function () {
    $this->getJson('/api/v1/sessions/01J00000000000000000000000', $this->auth)->assertNotFound();
});

it('recusa abertura quando o fim real informado e anterior ao inicio atual', function () {
    $this->freezeTime();
    $fim = now()->subHour()->toIso8601String();
    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", ['fim_real' => $fim], $this->auth)->assertOk();

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/open", [], $this->auth)
        ->assertStatus(422)->assertJsonPath('code', 'horario_invalido');

    expect($this->sessao->refresh()->status)->toBe(Sessao::STATUS_PREVISTA)
        ->and($this->sessao->inicio_real)->toBeNull()
        ->and(EventoAuditoria::where('acao', 'sessao.aberta')->exists())->toBeFalse();
});

it('recusa correcao cujo inicio real ultrapassa o fim real existente', function () {
    $this->sessao->update(['fim_real' => '2026-11-18 14:30:00']);
    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", ['inicio_real' => '2026-11-18T15:00:00Z'], $this->auth)
        ->assertStatus(422)->assertJsonPath('code', 'horario_invalido');

    expect($this->sessao->refresh()->inicio_real)->toBeNull()
        ->and(SessaoCorrecao::count())->toBe(0);
});

it('omite campos permitidos inalterados dos snapshots da correcao', function () {
    $this->sessao->update(['tema' => 'Tema mantido']);
    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", [
        'tema' => 'Tema mantido', 'auxiliares_escalados' => 2,
    ], $this->auth)->assertOk();

    $correcao = SessaoCorrecao::firstOrFail();
    expect($correcao->antes)->toBe(['auxiliares_escalados' => null])
        ->and($correcao->depois)->toBe(['auxiliares_escalados' => 2]);
});

it('corrige realizada de sessao encerrada e audita com motivo', function () {
    $this->sessao->update(['status' => Sessao::STATUS_ENCERRADA, 'realizada' => true]);
    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", [
        'realizada' => false, 'motivo' => 'Registro incorreto',
    ], $this->auth)->assertOk()->assertJsonPath('realizada', false);

    $correcao = SessaoCorrecao::firstOrFail();
    $evento = EventoAuditoria::where('acao', 'sessao.corrigida')->firstOrFail();
    expect($this->sessao->refresh()->realizada)->toBeFalse()
        ->and($correcao->antes)->toBe(['realizada' => true])
        ->and($correcao->depois)->toBe(['realizada' => false])
        ->and($evento->motivo)->toBe('Registro incorreto')
        ->and($evento->escopo)->toBe("ebi:{$this->ebi->id}");
});

it('lista pelo dia local da igreja, nao pelo dia UTC', function () {
    $this->igreja->update(['timezone' => 'America/Manaus']);

    $madrugadaLocal = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-19 00:30:00', 'fim_previsto' => '2026-11-19 02:00:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
    $diaSeguinteLocal = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id, 'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-19 04:00:00', 'fim_previsto' => '2026-11-19 05:30:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);

    $resposta = $this->getJson("/api/v1/ebis/{$this->ebi->id}/sessions?de=2026-11-18&ate=2026-11-18", $this->auth)
        ->assertOk()->assertJsonCount(2)->assertJsonPath('0.timezone', 'America/Manaus');

    $ids = collect($resposta->json())->pluck('id');
    expect($ids)->toContain($this->sessao->id, $madrugadaLocal->id)
        ->and($ids)->not->toContain($diaSeguinteLocal->id);
});

it('nao grava correcao quando nada muda', function () {
    $this->sessao->update(['tema' => 'Tema mantido']);

    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", ['tema' => 'Tema mantido'], $this->auth)
        ->assertOk()->assertJsonPath('tema', 'Tema mantido');

    expect(SessaoCorrecao::count())->toBe(0)
        ->and(EventoAuditoria::where('acao', 'sessao.corrigida')->exists())->toBeFalse();
});
