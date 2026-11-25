<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\OfertaVagaEscala;
use App\Domain\Escalas\Models\VagaEscala;
use Database\Seeders\PapelSeeder;

/**
 * Schema da vaga e encerramento automatico (E07, evolucao): retirar o membro,
 * mudar a funcao ou trocar o membro cancela a vaga aberta dele antes que a
 * linha suma; alteracao que so volta a escala a `prevista` nao mexe em vaga.
 *
 * `cenarioEscala()`, `membroEscala()` e `authEscala()` vem de EscalaGestaoTest;
 * `cenarioAprovacao()` vem de AprovacaoEscalaTest.
 */
beforeEach(fn () => $this->seed(PapelSeeder::class));

/** Vaga aberta de substituicao sobre o membro da pessoa. */
function vagaDoMembro(EscalaMembro $membro): VagaEscala
{
    return VagaEscala::query()->create([
        'escala_id' => $membro->escala_id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
        'substitui_membro_id' => $membro->id,
        'substituido_usuario_id' => $membro->usuario_id,
        'observacao' => 'Viagem',
        'status' => VagaEscala::STATUS_ABERTA,
    ]);
}

function ofertaPendente(VagaEscala $vaga): OfertaVagaEscala
{
    return OfertaVagaEscala::query()->create([
        'vaga_id' => $vaga->id,
        'usuario_id' => (string) str()->ulid(),
        'status' => OfertaVagaEscala::STATUS_PENDENTE,
    ]);
}

/** Escala da sessao do cenario (cria se ainda nao existe) com a pessoa dentro. */
function membroDaEscala(array $c, string $usuarioId): EscalaMembro
{
    $escala = Escala::query()->where('sessao_id', $c['sessao']->id)->first();

    $escala ??= Escala::factory()->create([
        'sessao_id' => $c['sessao']->id,
        'ebi_id' => $c['ebi']->id,
        'igreja_id' => $c['igreja']->id,
        'equipe_id' => $c['equipe']->id,
    ]);

    $c['escala'] = $escala;

    return EscalaMembro::factory()->for($escala)->create([
        'usuario_id' => $usuarioId,
        'status' => EscalaMembro::STATUS_ESCALADO,
    ]);
}

it('tabelas de vaga sobem com as FKs da decisao 1', function () {
    $c = cenarioEscala();
    $membro = membroDaEscala($c, $c['auxi']->id);
    $vaga = vagaDoMembro($membro);
    $oferta = ofertaPendente($vaga);

    // Apagar o membro original solta a vaga, que segue sabendo quem era.
    $membro->delete();
    expect($vaga->refresh()->substitui_membro_id)->toBeNull()
        ->and((string) $vaga->substituido_usuario_id)->toBe((string) $c['auxi']->id);

    // Apagar o membro criado pela oferta nao apaga a oferta aceita.
    $criado = membroDaEscala($c, (string) str()->ulid());
    $oferta->forceFill(['status' => OfertaVagaEscala::STATUS_ACEITA, 'membro_criado_id' => $criado->id])->save();
    $criado->delete();
    expect($oferta->refresh()->status)->toBe(OfertaVagaEscala::STATUS_ACEITA)
        ->and($oferta->membro_criado_id)->toBeNull();

    // Apagar a escala leva vagas e ofertas.
    Escala::query()->whereKey($vaga->escala_id)->delete();
    expect(VagaEscala::query()->count())->toBe(0)
        ->and(OfertaVagaEscala::query()->count())->toBe(0);
});

it('retirar membro cancela a vaga aberta dele antes do delete', function () {
    $c = cenarioEscala();
    $membro = membroDaEscala($c, $c['auxi']->id);
    $vaga = vagaDoMembro($membro);
    $oferta = ofertaPendente($vaga);

    $this->deleteJson("/api/v1/schedule-members/{$membro->id}", [], authEscala($c['chefe']))
        ->assertStatus(200);

    $vaga->refresh();
    expect($vaga->status)->toBe(VagaEscala::STATUS_CANCELADA)
        ->and($vaga->motivo_encerramento)->toBe(VagaEscala::MOTIVO_RETIRADO)
        ->and($vaga->substitui_membro_id)->toBeNull()
        ->and((string) $vaga->substituido_usuario_id)->toBe((string) $c['auxi']->id)
        ->and($oferta->refresh()->status)->toBe(OfertaVagaEscala::STATUS_DESCARTADA);
});

it('mudar funcao cancela a vaga aberta', function () {
    $c = cenarioEscala();
    $membro = membroDaEscala($c, $c['auxi']->id);
    $vaga = vagaDoMembro($membro);
    $membro->forceFill(['usuario_id' => membroEscala('coordenadora', 'ebi', $c['ebi']->id)->id])->save();

    $this->patchJson("/api/v1/schedule-members/{$membro->id}", ['funcao' => EscalaMembro::FUNCAO_COORDENADORA], authEscala($c['chefe']))
        ->assertStatus(200);

    expect($vaga->refresh()->status)->toBe(VagaEscala::STATUS_CANCELADA)
        ->and($vaga->motivo_encerramento)->toBe(VagaEscala::MOTIVO_FUNCAO);
});

it('troca aprovada cancela a vaga aberta do original', function () {
    $c = cenarioAprovacao();

    $c['escala']->forceFill(['status' => Escala::STATUS_APROVADA, 'aprovado_por' => $c['chefe']->id, 'aprovado_em' => now()])->save();

    $auxi = $c['escala']->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();

    $this->postJson('/api/v1/schedule-swaps', ['escala_membro_id' => $auxi->id, 'motivo' => 'Viagem'], authEscala($c['auxi']))
        ->assertStatus(201);
    $this->app['auth']->forgetGuards();

    $vaga = vagaDoMembro($auxi);
    $substituto = membroEscala('auxiliar', 'ebi', $c['ebi']->id);
    $trocaId = (string) \App\Domain\Escalas\Models\SolicitacaoTrocaEscala::query()->where('escala_membro_id', $auxi->id)->value('id');

    $this->patchJson("/api/v1/schedule-swaps/{$trocaId}", ['decisao' => 'aprovar', 'substituto_usuario_id' => $substituto->id], authEscala($c['chefe']))
        ->assertStatus(200);

    expect($vaga->refresh()->status)->toBe(VagaEscala::STATUS_CANCELADA)
        ->and($vaga->motivo_encerramento)->toBe(VagaEscala::MOTIVO_TROCADO);
});

it('alteracao que volta a prevista nao cancela vaga', function () {
    $c = cenarioAprovacao();
    $auxi = $c['escala']->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();
    $vaga = vagaDoMembro($auxi);

    $this->patchJson("/api/v1/schedules/{$c['escala']->id}", ['estimativa_participantes' => 7], authEscala($c['coord']))
        ->assertStatus(200);

    expect($vaga->refresh()->status)->toBe(VagaEscala::STATUS_ABERTA);
});

it('evento de cancelamento leva so ids e motivo', function () {
    $c = cenarioEscala();
    $membro = membroDaEscala($c, $c['auxi']->id);
    $vaga = vagaDoMembro($membro);

    $this->deleteJson("/api/v1/schedule-members/{$membro->id}", [], authEscala($c['chefe']))
        ->assertStatus(200);

    $evento = EventoAuditoria::query()->where('acao', 'escala.vaga_cancelada')->firstOrFail();
    $meta = $evento->metadados_minimizados;

    expect(array_keys($meta))->toEqualCanonicalizing(['vaga_id', 'escala_id', 'motivo_encerramento', 'status'])
        ->and($meta['motivo_encerramento'])->toBe(VagaEscala::MOTIVO_RETIRADO)
        ->and($evento->motivo)->toBe(VagaEscala::MOTIVO_RETIRADO);
    expect(json_encode($meta))->not->toContain('Viagem');
});
