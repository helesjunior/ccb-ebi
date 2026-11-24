<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Sessoes\Models\Sessao;
use Database\Seeders\PapelSeeder;

/**
 * Troca de membro na escala (RF-07): a pessoa pede, quem decide e a chefe
 * vigente (ou o `adm_igreja` quando o pedido e da propria chefe).
 *
 * `cenarioEscala()`, `membroEscala()` e `authEscala()` vem de EscalaGestaoTest;
 * `revogarChefe()` e `escopoAprovacao()` vem de AprovacaoEscalaTest.
 *
 * @param  array<string, mixed>  $opcoes  `status` da escala, `sessao` e os
 *                                        `membros` iniciais.
 * @return array<string, mixed>
 */
function cenarioTroca(array $opcoes = []): array
{
    $c = cenarioEscala($opcoes['sessao'] ?? []);

    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id,
        'ebi_id' => $c['ebi']->id,
        'igreja_id' => $c['igreja']->id,
        'estimativa_participantes' => 0,
        'participantes_por_auxiliar' => 4,
        'status' => $opcoes['status'] ?? Escala::STATUS_APROVADA,
    ]);

    $membros = $opcoes['membros'] ?? [
        ['usuario_id' => $c['auxi']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR],
    ];

    $vinculos = [];
    foreach ($membros as $membro) {
        $vinculos[(string) $membro['usuario_id']] = EscalaMembro::factory()->for($escala)->create($membro);
    }

    return $c + ['escala' => $escala, 'vinculos' => $vinculos];
}

/** Vaga da auxiliar no cenario. */
function vagaDaAuxiliar(array $c): EscalaMembro
{
    return $c['vinculos'][(string) $c['auxi']->id];
}

beforeEach(fn () => $this->seed(PapelSeeder::class));

/** Pessoa da equipe do EBI do cenario, pronta para assumir a vaga. */
function futuroSubstituto(array $c, string $papel = 'auxiliar'): string
{
    return (string) membroEscala($papel, 'ebi', $c['ebi']->id)->id;
}

/**
 * Pessoa que e so `adm_igreja` da igreja. `membroEscala()` acumula o papel a
 * uma coordenadora, que nunca e a chefe do EBI e por isso nao decide troca.
 */
function pessoaAdmIgreja(string $igrejaId): Usuario
{
    $usuario = Usuario::factory()->create();

    VinculoAcesso::factory()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', 'adm_igreja')->value('id'),
        'tipo_escopo' => 'igreja',
        'escopo_id' => $igrejaId,
        'inicio' => now()->subDays(30)->toDateString(),
        'fim' => null,
    ]);

    return $usuario;
}

it('a pessoa escalada pede a troca e a vaga sai da contagem de confirmados', function () {
    $c = cenarioTroca();
    $vaga = vagaDaAuxiliar($c);

    $resposta = $this->postJson('/api/v1/schedule-swaps', [
        'escala_membro_id' => $vaga->id,
        'motivo' => 'Viagem',
    ], authEscala($c['auxi']));

    $resposta->assertStatus(201)
        ->assertJsonPath('troca.escala_membro_id', $vaga->id)
        ->assertJsonPath('troca.solicitante_id', $c['auxi']->id)
        ->assertJsonPath('troca.motivo', 'Viagem')
        ->assertJsonPath('troca.status', 'pendente')
        ->assertJsonPath('troca.situacao', 'pendente');

    expect($vaga->refresh()->status)->toBe(EscalaMembro::STATUS_TROCA_SOLICITADA)
        ->and(EventoAuditoria::query()->where('acao', 'escala.troca_solicitada')->exists())->toBeTrue();
});

it('ninguem pede troca pela vaga de outra pessoa', function () {
    $c = cenarioTroca();
    $vaga = vagaDaAuxiliar($c);

    $this->postJson('/api/v1/schedule-swaps', ['escala_membro_id' => $vaga->id], authEscala($c['coord']))
        ->assertStatus(404);

    expect($vaga->refresh()->status)->toBe(EscalaMembro::STATUS_ESCALADO);
});

it('escala prevista nao aceita pedido de troca', function () {
    $c = cenarioTroca(['status' => Escala::STATUS_PREVISTA]);

    $this->postJson('/api/v1/schedule-swaps', ['escala_membro_id' => vagaDaAuxiliar($c)->id], authEscala($c['auxi']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'escala_em_estado_invalido');
});

it('sessao aberta nao aceita pedido de troca', function () {
    $c = cenarioTroca();
    Sessao::query()->whereKey($c['sessao']->id)->update(['status' => Sessao::STATUS_ABERTA]);

    $this->postJson('/api/v1/schedule-swaps', ['escala_membro_id' => vagaDaAuxiliar($c)->id], authEscala($c['auxi']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'sessao_em_estado_invalido');
});

it('o segundo pedido do mesmo membro responde 409', function () {
    $c = cenarioTroca();
    $vaga = vagaDaAuxiliar($c);
    SolicitacaoTrocaEscala::factory()->create(['escala_membro_id' => $vaga->id]);
    $vaga->forceFill(['status' => EscalaMembro::STATUS_TROCA_SOLICITADA])->save();

    $this->postJson('/api/v1/schedule-swaps', ['escala_membro_id' => $vaga->id], authEscala($c['auxi']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'membro_em_estado_invalido');
});

it('membro substituido nao pede troca', function () {
    $c = cenarioTroca();
    $vaga = vagaDaAuxiliar($c);
    $vaga->forceFill(['status' => EscalaMembro::STATUS_SUBSTITUIDO])->save();

    $this->postJson('/api/v1/schedule-swaps', ['escala_membro_id' => $vaga->id], authEscala($c['auxi']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'membro_em_estado_invalido');
});

it('a solicitante cancela o proprio pedido e a vaga volta a confirmada', function () {
    $c = cenarioTroca();
    $vaga = vagaDaAuxiliar($c);
    $vaga->forceFill(['status' => EscalaMembro::STATUS_CONFIRMADO, 'confirmado_em' => now()])->save();

    $trocaId = $this->postJson('/api/v1/schedule-swaps', ['escala_membro_id' => $vaga->id], authEscala($c['auxi']))
        ->assertStatus(201)
        ->json('troca.id');

    $this->postJson("/api/v1/schedule-swaps/{$trocaId}/cancel", [], authEscala($c['auxi']))
        ->assertStatus(200)
        ->assertJsonPath('troca.status', 'cancelada')
        ->assertJsonPath('troca.situacao', 'cancelada');

    expect($vaga->refresh()->status)->toBe(EscalaMembro::STATUS_CONFIRMADO);
});

it('outra pessoa nao cancela o pedido', function () {
    $c = cenarioTroca();
    $vaga = vagaDaAuxiliar($c);
    $troca = SolicitacaoTrocaEscala::factory()->create(['escala_membro_id' => $vaga->id]);

    $this->postJson("/api/v1/schedule-swaps/{$troca->id}/cancel", [], authEscala($c['coord']))
        ->assertStatus(404);

    expect($troca->refresh()->status)->toBe(SolicitacaoTrocaEscala::STATUS_PENDENTE);
});

it('cancelar troca ja decidida responde 409', function () {
    $c = cenarioTroca();
    $troca = SolicitacaoTrocaEscala::factory()->create([
        'escala_membro_id' => vagaDaAuxiliar($c)->id,
        'status' => SolicitacaoTrocaEscala::STATUS_RECUSADA,
    ]);

    $this->postJson("/api/v1/schedule-swaps/{$troca->id}/cancel", [], authEscala($c['auxi']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'troca_ja_decidida');
});

/** Troca pendente para a vaga da auxiliar do cenario. */
function trocaPendente(array $c, ?string $solicitanteId = null): SolicitacaoTrocaEscala
{
    $vaga = vagaDaAuxiliar($c);
    $vaga->forceFill(['status' => EscalaMembro::STATUS_TROCA_SOLICITADA])->save();

    return SolicitacaoTrocaEscala::factory()->create([
        'escala_membro_id' => $vaga->id,
        'solicitante_id' => $solicitanteId ?? (string) $c['auxi']->id,
    ]);
}

it('a chefe aprova com substituto: original substituido e membro novo escalado', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);
    $substituto = futuroSubstituto($c);

    $resposta = $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'aprovar',
        'substituto_usuario_id' => $substituto,
    ], authEscala($c['chefe']));

    $resposta->assertStatus(200)
        ->assertJsonPath('troca.status', 'aprovada')
        ->assertJsonPath('troca.decidido_por', $c['chefe']->id);

    $c['escala']->refresh();
    expect($c['escala']->status)->toBe(Escala::STATUS_APROVADA)
        ->and($troca->refresh()->substituto_membro_id)->not->toBeNull();

    $novo = EscalaMembro::query()->whereKey($troca->refresh()->substituto_membro_id)->firstOrFail();
    expect($novo->usuario_id)->toBe($substituto)
        ->and($novo->funcao)->toBe(EscalaMembro::FUNCAO_AUXILIAR)
        ->and($novo->status)->toBe(EscalaMembro::STATUS_ESCALADO)
        ->and($novo->substitui_membro_id)->toBe((string) vagaDaAuxiliar($c)->id)
        ->and(vagaDaAuxiliar($c)->refresh()->status)->toBe(EscalaMembro::STATUS_SUBSTITUIDO);

    expect(EventoAuditoria::query()->where('acao', 'escala.troca_decidida')->exists())->toBeTrue();
});

it('a chefe recusa e o membro volta ao status anterior', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'recusar',
        'motivo' => 'Sem substituto',
    ], authEscala($c['chefe']))
        ->assertStatus(200)
        ->assertJsonPath('troca.status', 'recusada')
        ->assertJsonPath('troca.motivo_decisao', 'Sem substituto');

    expect(vagaDaAuxiliar($c)->refresh()->status)->toBe(EscalaMembro::STATUS_ESCALADO);
});

it('decidir duas vezes responde 409', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", ['decisao' => 'recusar'], authEscala($c['chefe']))->assertStatus(200);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", ['decisao' => 'recusar'], authEscala($c['chefe']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'troca_ja_decidida');
});

it('substituto ja ativo na escala responde 422 membro_ja_escalado', function () {
    $c = cenarioTroca();
    $jaNaEscala = membroEscala('auxiliar', 'ebi', $c['ebi']->id);
    EscalaMembro::factory()->for($c['escala'])->create([
        'usuario_id' => $jaNaEscala->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ]);
    $troca = trocaPendente($c);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'aprovar',
        'substituto_usuario_id' => $jaNaEscala->id,
    ], authEscala($c['chefe']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_ja_escalado');
});

it('substituto sem vinculo responde 422 membro_inelegivel', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);
    $semVinculo = (string) membroEscala('auxiliar', 'ebi', $c['ebi']->id)->id;
    VinculoAcesso::query()->where('usuario_id', $semVinculo)
        ->update(['fim' => now()->subDay()->toDateString()]);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'aprovar',
        'substituto_usuario_id' => $semVinculo,
    ], authEscala($c['chefe']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_inelegivel');
});

it('substituto ministerio_local responde 422 membro_inelegivel', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'aprovar',
        'substituto_usuario_id' => $c['ministro']->id,
    ], authEscala($c['chefe']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_inelegivel');
});

it('aprovar sem substituto responde 422 membro_inelegivel', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", ['decisao' => 'aprovar'], authEscala($c['chefe']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_inelegivel');
});

it('a chefe que pediu nao decide o proprio pedido', function () {
    $c = cenarioTroca();
    $vagaDaChefe = EscalaMembro::factory()->for($c['escala'])->create([
        'usuario_id' => $c['chefe']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ]);
    $vagaDaChefe->forceFill(['status' => EscalaMembro::STATUS_TROCA_SOLICITADA])->save();
    $troca = SolicitacaoTrocaEscala::factory()->create([
        'escala_membro_id' => $vagaDaChefe->id, 'solicitante_id' => $c['chefe']->id,
    ]);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'aprovar',
        'substituto_usuario_id' => futuroSubstituto($c, 'coordenadora'),
    ], authEscala($c['chefe']))
        ->assertStatus(403)
        ->assertJsonPath('code', 'sem_permissao');
});

it('pedido da chefe: quem decide passa a ser o adm_igreja', function () {
    $c = cenarioTroca();
    $vagaDaChefe = EscalaMembro::factory()->for($c['escala'])->create([
        'usuario_id' => $c['chefe']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ]);
    $vagaDaChefe->forceFill(['status' => EscalaMembro::STATUS_TROCA_SOLICITADA])->save();
    $troca = SolicitacaoTrocaEscala::factory()->create([
        'escala_membro_id' => $vagaDaChefe->id, 'solicitante_id' => $c['chefe']->id,
    ]);
    $adm = pessoaAdmIgreja($c['igreja']->id);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'aprovar',
        'substituto_usuario_id' => futuroSubstituto($c, 'coordenadora'),
    ], authEscala($adm) + escopoAprovacao('adm_igreja', 'igreja', $c['igreja']->id))
        ->assertStatus(200)
        ->assertJsonPath('troca.status', 'aprovada');
});

it('revogada a chefe, o adm_igreja decide e a ex-chefe nao', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);
    revogarChefe($c['ebi']->id);
    $adm = pessoaAdmIgreja($c['igreja']->id);
    $corpo = ['decisao' => 'recusar'];

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", $corpo, authEscala($adm) + escopoAprovacao('adm_igreja', 'igreja', $c['igreja']->id))
        ->assertStatus(200)
        ->assertJsonPath('troca.status', 'recusada');
});

it('revogada a chefe, a ex-chefe nao decide mais', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);
    revogarChefe($c['ebi']->id);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", ['decisao' => 'recusar'], authEscala($c['chefe']))
        ->assertStatus(403);

    expect($troca->refresh()->status)->toBe(SolicitacaoTrocaEscala::STATUS_PENDENTE);
});

it('quem nao decide (coordenadora) recebe 403', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", ['decisao' => 'recusar'], authEscala($c['coord']))
        ->assertStatus(403);
});

it('decidir com a sessao encerrada responde 409 e a lista mostra expirada', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);
    Sessao::query()->whereKey($c['sessao']->id)->update(['status' => Sessao::STATUS_ENCERRADA]);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", ['decisao' => 'recusar'], authEscala($c['chefe']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'sessao_em_estado_invalido');

    $this->getJson("/api/v1/schedule-swaps?ebi_id={$c['ebi']->id}", authEscala($c['chefe']))
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $troca->id)
        ->assertJsonPath('data.0.status', 'pendente')
        ->assertJsonPath('data.0.situacao', 'expirada');
});

it('a chefe lista as trocas pendentes do EBI e filtra por status', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);
    $outra = SolicitacaoTrocaEscala::factory()->create([
        'escala_membro_id' => EscalaMembro::factory()->for($c['escala'])->create([
            'usuario_id' => futuroSubstituto($c), 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
        ])->id,
        'status' => SolicitacaoTrocaEscala::STATUS_RECUSADA,
    ]);

    $this->getJson("/api/v1/schedule-swaps?ebi_id={$c['ebi']->id}", authEscala($c['chefe']))
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/v1/schedule-swaps?ebi_id={$c['ebi']->id}&status=pendente", authEscala($c['chefe']))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $troca->id)
        ->assertJsonPath('data.0.situacao', 'pendente');

    expect($outra->refresh()->status)->toBe(SolicitacaoTrocaEscala::STATUS_RECUSADA);
});

it('a auxiliar nao lista as trocas do EBI', function () {
    $c = cenarioTroca();

    $this->getJson("/api/v1/schedule-swaps?ebi_id={$c['ebi']->id}", authEscala($c['auxi']))
        ->assertStatus(403);
});

it('alteracao seguida de decisao: a alteracao cancela e a decisao responde 409', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);
    $vaga = vagaDaAuxiliar($c);
    $membrosAntes = $c['escala']->membros()->count();

    // Mesmo ator nas duas chamadas: a chefe, que decide troca.
    $this->patchJson("/api/v1/schedules/{$c['escala']->id}", ['estimativa_participantes' => 10], authEscala($c['chefe']))
        ->assertStatus(200);

    expect($troca->refresh()->status)->toBe(SolicitacaoTrocaEscala::STATUS_CANCELADA)
        ->and($c['escala']->refresh()->status)->toBe(Escala::STATUS_PREVISTA)
        ->and($vaga->refresh()->status)->toBe(EscalaMembro::STATUS_ESCALADO);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'aprovar',
        'substituto_usuario_id' => futuroSubstituto($c),
    ], authEscala($c['chefe']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'troca_ja_decidida');

    expect($c['escala']->membros()->count())->toBe($membrosAntes)
        ->and(EscalaMembro::query()->where('status', EscalaMembro::STATUS_SUBSTITUIDO)->count())->toBe(0);
});

it('decisao seguida de alteracao: o substituto permanece e a escala volta a prevista', function () {
    $c = cenarioTroca();
    $troca = trocaPendente($c);
    $vaga = vagaDaAuxiliar($c);

    $this->patchJson("/api/v1/schedule-swaps/{$troca->id}", [
        'decisao' => 'aprovar',
        'substituto_usuario_id' => futuroSubstituto($c),
    ], authEscala($c['chefe']))->assertStatus(200);

    $this->patchJson("/api/v1/schedules/{$c['escala']->id}", ['estimativa_participantes' => 10], authEscala($c['coord']))
        ->assertStatus(200);

    $novo = EscalaMembro::query()->whereKey($troca->refresh()->substituto_membro_id)->firstOrFail();
    expect($c['escala']->refresh()->status)->toBe(Escala::STATUS_PREVISTA)
        ->and($novo->refresh()->status)->toBe(EscalaMembro::STATUS_ESCALADO)
        ->and($vaga->refresh()->status)->toBe(EscalaMembro::STATUS_SUBSTITUIDO);
});
