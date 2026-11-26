<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Sessoes\Models\Sessao;
use Database\Seeders\PapelSeeder;

/**
 * Acrescimo (E07, evolucao): incluir membro numa escala aprovada nao desfaz a
 * aprovacao — a coordenadora apenas aumenta a equipe. O divisor de
 * participantes fica o da aprovacao, o aprovador e a data se mantem e as
 * trocas pendentes seguem de pe. Aprovar e decisao do territorio; acrescentar
 * e operacao da dona.
 *
 * `cenarioAprovacao()`, `membroEscala()` e `authEscala()` vem dos arquivos de
 * E07. `cenarioAprovacao($opcoes)` aceita `membros` e `estimativa`.
 */
beforeEach(fn () => $this->seed(PapelSeeder::class));

/**
 * Escala aprovada pela chefe, com estimativa que pede 2 auxiliares e o divisor
 * de aprovacao gravado em 4.
 *
 * @return array<string, mixed>
 */
function cenarioAcrescimo(): array
{
    $c = cenarioAprovacao(['estimativa' => 8]);

    $c['escala']->forceFill([
        'status' => Escala::STATUS_APROVADA,
        'aprovado_por' => $c['chefe']->id,
        'aprovado_em' => now()->subDay(),
        'participantes_por_auxiliar' => 4,
    ])->save();

    // Depois da aprovacao o EBI muda o divisor: o acrescimo nao pode recopia-lo.
    Ebi::query()->whereKey($c['ebi']->id)->update(['participantes_por_auxiliar' => 2]);

    return $c;
}

it('incluir membro em escala aprovada mantem a aprovacao inteira', function () {
    $c = cenarioAcrescimo();
    $novo = membroEscala('auxiliar', 'ebi', $c['ebi']->id);
    $aprovadoEm = $c['escala']->aprovado_em;

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/members", [
        'usuario_id' => $novo->id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ], authEscala($c['coord']))->assertStatus(200);

    $escala = $c['escala']->refresh();

    expect($escala->status)->toBe(Escala::STATUS_APROVADA)
        ->and((string) $escala->aprovado_por)->toBe((string) $c['chefe']->id)
        ->and($escala->aprovado_em->equalTo($aprovadoEm))->toBeTrue()
        ->and((int) $escala->participantes_por_auxiliar)->toBe(4);

    $evento = EventoAuditoria::query()->where('acao', 'escala.alterada')->latest('id')->firstOrFail();

    expect($evento->metadados_minimizados['voltou_prevista'])->toBeFalse();
});

it('incluir nao cancela troca pendente', function () {
    $c = cenarioAcrescimo();
    $vaga = $c['escala']->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();

    $this->postJson('/api/v1/schedule-swaps', [
        'escala_membro_id' => $vaga->id,
        'motivo' => 'Viagem',
    ], authEscala($c['auxi']))->assertStatus(201);

    // O cliente HTTP reaproveita o usuario autenticado anterior entre requests.
    $this->app['auth']->forgetGuards();

    $novo = membroEscala('auxiliar', 'ebi', $c['ebi']->id);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/members", [
        'usuario_id' => $novo->id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ], authEscala($c['coord']))->assertStatus(200);

    expect(SolicitacaoTrocaEscala::query()->where('escala_membro_id', $vaga->id)->value('status'))
        ->toBe(SolicitacaoTrocaEscala::STATUS_PENDENTE)
        ->and($vaga->refresh()->status)->toBe(EscalaMembro::STATUS_TROCA_SOLICITADA);
});

it('membro incluido entra escalado', function () {
    $c = cenarioAcrescimo();
    $novo = membroEscala('auxiliar', 'ebi', $c['ebi']->id);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/members", [
        'usuario_id' => $novo->id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ], authEscala($c['coord']))->assertStatus(200);

    expect(EscalaMembro::query()->where('escala_id', $c['escala']->id)->where('usuario_id', $novo->id)->value('status'))
        ->toBe(EscalaMembro::STATUS_ESCALADO);
});

it('retirar, mudar funcao e estimativa voltam a prevista', function (string $acao) {
    $c = cenarioAcrescimo();
    $vaga = $c['escala']->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();
    $novaCoord = membroEscala('coordenadora', 'ebi', $c['ebi']->id);
    $this->postJson('/api/v1/schedule-swaps', ['escala_membro_id' => $vaga->id], authEscala($c['auxi']))
        ->assertStatus(201);

    $this->app['auth']->forgetGuards();

    $resposta = match ($acao) {
        'retirar' => $this->deleteJson("/api/v1/schedule-members/{$vaga->id}", [], authEscala($c['coord'])),
        'funcao' => $this->patchJson("/api/v1/schedule-members/{$vaga->id}", ['funcao' => EscalaMembro::FUNCAO_AUXILIAR], authEscala($c['coord'])),
        'estimativa' => $this->patchJson("/api/v1/schedules/{$c['escala']->id}", ['estimativa_participantes' => 7], authEscala($c['coord'])),
    };

    $resposta->assertStatus(200)->assertJsonPath('escala.status', 'prevista');

    $escala = $c['escala']->refresh();

    expect($escala->status)->toBe(Escala::STATUS_PREVISTA)
        ->and($escala->aprovado_por)->toBeNull()
        ->and($escala->aprovado_em)->toBeNull()
        ->and((int) $escala->participantes_por_auxiliar)->toBe(2);

    // Retirar apaga a vaga e leva a troca junto (cascade); as demais alteracoes
    // cancelam a troca pendente.
    expect(SolicitacaoTrocaEscala::query()->where('escala_membro_id', $vaga->id)->value('status'))
        ->toBe($acao === 'retirar' ? null : SolicitacaoTrocaEscala::STATUS_CANCELADA);
})->with(['retirar', 'funcao', 'estimativa']);

it('incluir com sessao encerrada responde 409', function () {
    $c = cenarioAcrescimo();
    Sessao::query()->whereKey($c['sessao']->id)->update(['status' => Sessao::STATUS_ENCERRADA]);
    $novo = membroEscala('auxiliar', 'ebi', $c['ebi']->id);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/members", [
        'usuario_id' => $novo->id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ], authEscala($c['coord']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'sessao_em_estado_invalido');
});
