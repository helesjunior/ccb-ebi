<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Sessoes\Models\Sessao;
use Database\Seeders\PapelSeeder;

/**
 * Presenca da equipe (E07, RF-07): o planejado (quem foi escalado) contra o
 * cumprido (quem compareceu), registrada so com a sessao encerrada.
 * `cenarioEscala()`, `authEscala()` e `cenarioTroca()` vem dos outros testes
 * de Escalas (Pest inclui os arquivos do diretorio).
 */

/** Escala aprovada com auxiliar confirmada e uma vaga extra. */
function cenarioPresenca(array $membros = []): array
{
    $c = cenarioEscala();

    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id,
        'ebi_id' => $c['ebi']->id,
        'igreja_id' => $c['igreja']->id,
        'estimativa_participantes' => 0,
        'participantes_por_auxiliar' => 4,
        'status' => Escala::STATUS_APROVADA,
    ]);

    $vagas = $membros === [] ? [
        EscalaMembro::factory()->for($escala)->create([
            'usuario_id' => $c['auxi']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
            'status' => EscalaMembro::STATUS_CONFIRMADO, 'confirmado_em' => now(),
        ]),
        EscalaMembro::factory()->for($escala)->create([
            'usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA,
        ]),
    ] : [];

    return $c + ['escala' => $escala, 'membros' => $vagas];
}

/** Deixa a sessao como ficou depois do encerramento pela rota de sessoes. */
function encerrarSessao(array $c): void
{
    $c['sessao']->forceFill(['status' => Sessao::STATUS_ENCERRADA, 'realizada' => true, 'fim_real' => now()])->save();
}

function urlPresenca(array $c): string
{
    return "/api/v1/schedules/{$c['escala']->id}/team-attendance";
}

it('sem envio, todos ficam sem presenca e a equipe aparece pendente', function () {
    $c = cenarioPresenca();
    encerrarSessao($c);

    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.presenca_equipe_pendente', true)
        ->assertJsonPath('escala.membros.0.presente', null)
        ->assertJsonPath('escala.membros.1.presente', null);
});

it('a pendencia nao existe com a sessao ainda prevista', function () {
    $c = cenarioPresenca();

    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.presenca_equipe_pendente', false);
});

it('envio parcial grava so o que veio e zera a pendencia quando todos tem resposta', function () {
    $c = cenarioPresenca();
    encerrarSessao($c);
    $primeira = $c['membros'][0]->id;

    $this->putJson(urlPresenca($c), ['membros' => [['id' => $primeira, 'presente' => true]]], authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.presenca_equipe_pendente', true)
        ->assertJsonPath('escala.membros.0.presente', true)
        ->assertJsonPath('escala.membros.1.presente', null);

    $this->putJson(urlPresenca($c), ['membros' => [['id' => $c['membros'][1]->id, 'presente' => false]]], authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.presenca_equipe_pendente', false)
        ->assertJsonPath('escala.membros.1.presente', false);

    expect($c['membros'][0]->refresh()->presente)->toBeTrue()
        ->and($c['membros'][0]->presenca_registrada_por)->not->toBeNull()
        ->and($c['membros'][0]->presenca_registrada_em)->not->toBeNull();
});

it('reenviar o mesmo corpo nao muda o estado nem cria evento novo', function () {
    $c = cenarioPresenca();
    encerrarSessao($c);
    $corpo = ['membros' => [
        ['id' => $c['membros'][0]->id, 'presente' => true],
        ['id' => $c['membros'][1]->id, 'presente' => false],
    ]];

    $this->putJson(urlPresenca($c), $corpo, authEscala($c['coord']))->assertStatus(200);
    $this->putJson(urlPresenca($c), $corpo, authEscala($c['coord']))->assertStatus(200);

    expect(EventoAuditoria::query()->where('acao', 'escala.presenca_equipe')->count())->toBe(1);
});

it('correcao gera evento com antes e depois, sem nome', function () {
    $c = cenarioPresenca();
    encerrarSessao($c);
    $membro = $c['membros'][0]->id;

    $this->putJson(urlPresenca($c), ['membros' => [['id' => $membro, 'presente' => true]]], authEscala($c['coord']))
        ->assertStatus(200);
    $this->putJson(urlPresenca($c), ['membros' => [['id' => $membro, 'presente' => false]]], authEscala($c['coord']))
        ->assertStatus(200);

    $eventos = EventoAuditoria::query()->where('acao', 'escala.presenca_equipe')->orderBy('created_at')->get();
    $ultimo = $eventos->last();

    expect($eventos)->toHaveCount(2)
        ->and($ultimo?->metadados_minimizados['antes'])->toBe([$membro => true])
        ->and($ultimo?->metadados_minimizados['depois'])->toBe([$membro => false])
        ->and($ultimo?->metadados_minimizados)->not->toHaveKey('nome');
});

it('recusa id que nao pertence a escala sem gravar nada', function () {
    $c = cenarioPresenca();
    encerrarSessao($c);
    $outra = EscalaMembro::factory()->create();
    $membro = $c['membros'][0]->id;

    $this->putJson(urlPresenca($c), ['membros' => [
        ['id' => $membro, 'presente' => true],
        ['id' => $outra->id, 'presente' => true],
    ]], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_invalido');

    expect($c['membros'][0]->refresh()->presente)->toBeNull()
        ->and(EventoAuditoria::query()->where('acao', 'escala.presenca_equipe')->exists())->toBeFalse();
});

it('recusa membro substituido', function () {
    $c = cenarioPresenca();
    encerrarSessao($c);
    $membro = $c['membros'][0];
    $membro->forceFill(['status' => EscalaMembro::STATUS_SUBSTITUIDO])->save();

    $this->putJson(urlPresenca($c), ['membros' => [['id' => $membro->id, 'presente' => true]]], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_invalido');

    expect($membro->refresh()->presente)->toBeNull();
});

it('recusa com a sessao aberta', function () {
    $c = cenarioPresenca();
    $c['sessao']->forceFill(['status' => Sessao::STATUS_ABERTA])->save();

    $this->putJson(urlPresenca($c), ['membros' => [['id' => $c['membros'][0]->id, 'presente' => true]]], authEscala($c['coord']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'sessao_em_estado_invalido');

    expect($c['membros'][0]->refresh()->presente)->toBeNull();
});

it('valida o corpo', function () {
    $c = cenarioPresenca();
    encerrarSessao($c);

    $this->putJson(urlPresenca($c), ['membros' => []], authEscala($c['coord']))->assertStatus(422);
    $this->putJson(urlPresenca($c), ['membros' => [['id' => $c['membros'][0]->id]]], authEscala($c['coord']))->assertStatus(422);
});

it('encerrar a sessao sem presenca deixa pendente e o envio depois zera', function () {
    $c = cenarioEscala();
    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/open", [], authEscala($c['coord']))->assertStatus(200);

    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
        'estimativa_participantes' => 0, 'participantes_por_auxiliar' => 4, 'status' => Escala::STATUS_APROVADA,
    ]);
    $vaga = EscalaMembro::factory()->for($escala)->create(['usuario_id' => $c['auxi']->id]);

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/close", ['realizada' => true], authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('status', 'encerrada');

    $this->getJson("/api/v1/schedules/{$escala->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.situacao', 'aprovada')
        ->assertJsonPath('escala.presenca_equipe_pendente', true)
        ->assertJsonPath('escala.membros.0.presente', null)
        ->assertJsonPath('escala.sessao.status', 'encerrada');

    $this->putJson("/api/v1/schedules/{$escala->id}/team-attendance", ['membros' => [['id' => $vaga->id, 'presente' => true]]], authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.presenca_equipe_pendente', false)
        ->assertJsonPath('escala.membros.0.presente', true);
});

it('a auxiliar nao registra presenca da equipe', function () {
    $c = cenarioPresenca();
    encerrarSessao($c);

    $this->putJson(urlPresenca($c), ['membros' => [['id' => $c['membros'][0]->id, 'presente' => true]]], authEscala($c['auxi']))
        ->assertStatus(403);
});

beforeEach(fn () => $this->seed(PapelSeeder::class));
