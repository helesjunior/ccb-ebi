<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Services\DonaDaEscala;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use Database\Seeders\PapelSeeder;

/**
 * Regra de dona (E07, evolucao): a escala nasce da equipe de uma coordenadora e
 * so ela escreve; a chefe e o `adm_igreja` supervisionam, sem escolher oferta.
 *
 * `cenarioEscala()`, `membroEscala()` e `authEscala()` vem de EscalaGestaoTest;
 * `cenarioAprovacao()` vem de AprovacaoEscalaTest.
 */
beforeEach(fn () => $this->seed(PapelSeeder::class));

/** Outra coordenadora do EBI, com a propria equipe. */
function outraCoordenadora(array $c): array
{
    $coord = membroEscala('coordenadora', 'ebi', $c['ebi']->id);
    $equipe = Equipe::query()->create([
        'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
        'coordenadora_id' => $coord->id, 'criado_por' => $coord->id,
    ]);

    return ['coord' => $coord, 'equipe' => $equipe];
}

function contextoDona(string $usuarioId, string $papel = 'coordenadora'): ContextoDecisao
{
    return new ContextoDecisao($usuarioId, $papel, 'ebi', 'ebi-1');
}

/** A pessoa ganha um segundo vinculo `auxiliar` no mesmo EBI (RF-1). */
function vinculoAuxiliar(object $usuario, array $c): void
{
    VinculoAcesso::query()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', 'auxiliar')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $c['ebi']->id,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
        'status' => 'ativo',
    ]);
}

function escopoEscala(string $papel, array $c, object $usuario, ?string $tipo = null): array
{
    return ['X-Acting-Scope' => "papel={$papel};escopo=".($tipo ?? 'ebi').':'.($tipo === 'igreja' ? $c['igreja']->id : $c['ebi']->id)];
}

/** Escala pronta para escrita (sessao prevista) com coordenadora e auxiliar. */
function escalaParaEscrita(array &$c): Escala
{
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id,
        'equipe_id' => $c['equipe']->id,
        'ebi_id' => $c['ebi']->id,
        'igreja_id' => $c['igreja']->id,
        'estimativa_participantes' => 8,
        'participantes_por_auxiliar' => 4,
        'status' => Escala::STATUS_PREVISTA,
    ]);
    EscalaMembro::factory()->for($escala)->create(['usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA]);
    EscalaMembro::factory()->for($escala)->create(['usuario_id' => $c['auxi']->id]);
    $c['escala'] = $escala;

    return $escala;
}

it('coordenadora cria escala so com a propria equipe', function () {
    $c = cenarioEscala();
    $outra = outraCoordenadora($c);

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'equipe_id' => $outra['equipe']->id,
        'estimativa_participantes' => 8,
        'membros' => [['usuario_id' => $outra['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA]],
    ], authEscala($c['coord']))->assertStatus(422)->assertJsonPath('code', 'equipe_invalida');

    expect(Escala::query()->count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'equipe_id' => $c['equipe']->id,
        'estimativa_participantes' => 8,
        'membros' => [['usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA]],
    ], authEscala($c['coord']))->assertStatus(201);
});

it('chefe cria com qualquer equipe do EBI', function () {
    $c = cenarioEscala();
    $outra = outraCoordenadora($c);

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'equipe_id' => $outra['equipe']->id,
        'estimativa_participantes' => 8,
        'membros' => [['usuario_id' => $outra['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA]],
    ], authEscala($c['chefe']))->assertStatus(201)->assertJsonPath('escala.equipe_id', $outra['equipe']->id);
});

it('equipe de outro EBI responde 422', function () {
    $c = cenarioEscala();
    $outraEbi = Ebi::factory()->create(['igreja_id' => $c['igreja']->id]);
    $equipe = Equipe::query()->create([
        'ebi_id' => $outraEbi->id, 'igreja_id' => $c['igreja']->id,
        'coordenadora_id' => $c['coord']->id, 'criado_por' => $c['coord']->id,
    ]);

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'equipe_id' => $equipe->id, 'estimativa_participantes' => 8, 'membros' => [],
    ], authEscala($c['chefe']))->assertStatus(422)->assertJsonPath('code', 'equipe_invalida');
});

it('equipe com dona sem vinculo vigente responde 422', function () {
    $c = cenarioEscala();
    VinculoAcesso::query()->where('usuario_id', $c['coord']->id)->update(['fim' => now()->subDay()->toDateString()]);

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'equipe_id' => $c['equipe']->id, 'estimativa_participantes' => 8, 'membros' => [],
    ], authEscala($c['chefe']))->assertStatus(422)->assertJsonPath('code', 'equipe_invalida');
});

it('PATCH ignora equipe_id', function () {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);
    $outra = outraCoordenadora($c);

    $this->patchJson("/api/v1/schedules/{$escala->id}", [
        'estimativa_participantes' => 9, 'equipe_id' => $outra['equipe']->id,
    ], authEscala($c['coord']))->assertStatus(200);

    expect((string) $escala->refresh()->equipe_id)->toBe((string) $c['equipe']->id);
});

it('coordenadora nao dona nao escreve', function (string $via) {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);
    $outra = outraCoordenadora($c);
    $auth = authEscala($outra['coord']);
    $membro = $escala->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();

    $resposta = match ($via) {
        'estimativa' => $this->patchJson("/api/v1/schedules/{$escala->id}", ['estimativa_participantes' => 9], $auth),
        'incluir' => $this->postJson("/api/v1/schedules/{$escala->id}/members", ['usuario_id' => $c['ministro']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR], $auth),
        'retirar' => $this->deleteJson("/api/v1/schedule-members/{$membro->id}", [], $auth),
        'funcao' => $this->patchJson("/api/v1/schedule-members/{$membro->id}", ['funcao' => EscalaMembro::FUNCAO_AUXILIAR], $auth),
    };

    $resposta->assertStatus(403)->assertJsonPath('code', 'nao_e_dona_da_escala')
        ->assertJsonPath('dona_id', $c['coord']->id);
})->with(['estimativa', 'incluir', 'retirar', 'funcao']);

it('chefe e adm_igreja escrevem em qualquer escala do EBI', function (string $papel) {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);
    $ator = $papel === 'chefe' ? $c['chefe'] : membroEscala('adm_igreja', 'igreja', $c['igreja']->id);
    // O ministro nao pertence a equipe da escala: usa a auxiliar, que pertence.
    $auth = authEscala($ator) + ($papel === 'adm_igreja' ? escopoEscala('adm_igreja', $c, $ator, 'igreja') : []);

    $this->patchJson("/api/v1/schedules/{$escala->id}", ['estimativa_participantes' => 9], $auth)
        ->assertStatus(200);
    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedules/{$escala->id}/members", [
        'usuario_id' => $c['auxi']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ], $auth)->assertStatus(422)->assertJsonPath('code', 'membro_ja_escalado');
    $this->app['auth']->forgetGuards();
    $this->patchJson("/api/v1/schedules/{$escala->id}", ['estimativa_participantes' => 10], $auth)
        ->assertStatus(200);
})->with(['chefe', 'adm_igreja']);

it('a dona atuando como auxiliar nao escreve na propria escala', function () {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);
    vinculoAuxiliar($c['coord'], $c);

    // RF-1: a matriz ja recusa `escala.gerenciar` ao auxiliar, antes do servico.
    $this->patchJson("/api/v1/schedules/{$escala->id}", ['estimativa_participantes' => 9],
        authEscala($c['coord']) + escopoEscala('auxiliar', $c, $c['coord']))
        ->assertStatus(403)->assertJsonPath('code', 'sem_permissao');
});

it('a chefe que e dona da equipe pode escolher oferta', function () {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);
    // A chefe monta a propria escala: a equipe e dela.
    $c['equipe']->forceFill(['coordenadora_id' => $c['chefe']->id])->save();

    expect(app(DonaDaEscala::class)->podeEscolherOferta((string) $escala->id, contextoDona((string) $c['chefe']->id, 'coordenadora_chefe')))->toBeTrue();
});

it('a dona atuando como auxiliar nao escolhe oferta', function () {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);

    expect(app(DonaDaEscala::class)->podeEscolherOferta((string) $escala->id, contextoDona((string) $c['coord']->id, 'auxiliar')))->toBeFalse();
});

it('chefe nao dona e adm_igreja nao escolhem oferta', function () {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);
    $adm = membroEscala('adm_igreja', 'igreja', $c['igreja']->id);
    $dona = app(DonaDaEscala::class);

    expect($dona->podeEscolherOferta((string) $escala->id, contextoDona((string) $c['chefe']->id, 'coordenadora_chefe')))->toBeFalse()
        ->and($dona->podeEscolherOferta((string) $escala->id, contextoDona((string) $adm->id, 'adm_igreja')))->toBeFalse();
});

it('dona revogada nao escolhe', function () {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);
    VinculoAcesso::query()->where('usuario_id', $c['coord']->id)->update(['fim' => now()->subDay()->toDateString()]);

    expect(app(DonaDaEscala::class)->podeEscolherOferta((string) $escala->id, contextoDona((string) $c['coord']->id)))->toBeFalse();
});

it('detalhe traz equipe, pode_gerir e pode_escolher_oferta', function () {
    $c = cenarioEscala();
    $escala = escalaParaEscrita($c);

    $comoDona = $this->getJson("/api/v1/schedules/{$escala->id}", authEscala($c['coord']))->assertStatus(200);
    expect($comoDona->json('escala.pode_gerir'))->toBeTrue()
        ->and($comoDona->json('escala.pode_escolher_oferta'))->toBeTrue()
        ->and($comoDona->json('escala.equipe.id'))->toBe((string) $c['equipe']->id)
        ->and($comoDona->json('escala.equipe.coordenadora_nome'))->toBe((string) $c['coord']->nome);

    $this->app['auth']->forgetGuards();
    $comoMinistro = $this->getJson("/api/v1/schedules/{$escala->id}", authEscala($c['ministro']))->assertStatus(200);
    expect($comoMinistro->json('escala.pode_gerir'))->toBeFalse()
        ->and($comoMinistro->json('escala.pode_escolher_oferta'))->toBeFalse();

    $this->app['auth']->forgetGuards();
    $comoChefe = $this->getJson("/api/v1/schedules/{$escala->id}", authEscala($c['chefe']))->assertStatus(200);
    expect($comoChefe->json('escala.pode_gerir'))->toBeTrue()
        ->and($comoChefe->json('escala.pode_escolher_oferta'))->toBeFalse();
});

it('escala.criada leva equipe_id', function () {
    $c = cenarioEscala();

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'equipe_id' => $c['equipe']->id,
        'estimativa_participantes' => 8,
        'membros' => [['usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA]],
    ], authEscala($c['coord']))->assertStatus(201);

    $evento = EventoAuditoria::query()->where('acao', 'escala.criada')->firstOrFail();
    expect($evento->metadados_minimizados['equipe_id'])->toBe((string) $c['equipe']->id);
});
