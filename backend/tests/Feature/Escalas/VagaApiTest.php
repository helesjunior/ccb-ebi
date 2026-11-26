<?php

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\EquipeMembro;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\OfertaVagaEscala;
use App\Domain\Escalas\Models\VagaEscala;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use App\Domain\Sessoes\Models\Sessao;
use Database\Seeders\PapelSeeder;

/**
 * API das vagas (E07, evolucao, decisao 9): a dona abre e escolhe, a pessoa
 * da equipe se oferece, e cada leitor ve so o que lhe cabe. `cenarioEscala()`,
 * `membroEscala()` e `authEscala()` vem de EscalaGestaoTest; `escalaComEquipe()`
 * vem de VagaDominioTest.
 */
beforeEach(fn () => $this->seed(PapelSeeder::class));

/** Escala com dona, auxiliar e uma vaga de acrescimo aberta. */
function cenarioVaga(): array
{
    $c = cenarioEscala();
    escalaComEquipe($c);

    $vaga = VagaEscala::query()->create([
        'escala_id' => $c['escala']->id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
        'status' => VagaEscala::STATUS_ABERTA,
        'aberta_por' => $c['coord']->id,
    ]);

    return $c + ['vaga' => $vaga];
}

/** Auxiliar vigente do EBI que nao esta na escala: o candidato natural da vaga. */
function ofertante(array $c): Usuario
{
    return membroEscala('auxiliar', 'ebi', $c['ebi']->id);
}

it('dona abre vaga e recebe o cartao de gestao', function () {
    $c = cenarioVaga();

    $resposta = $this->postJson("/api/v1/schedules/{$c['escala']->id}/vacancies", ['observacao' => 'Falta um'], authEscala($c['coord']));

    $resposta->assertStatus(201)
        ->assertJsonPath('data.tipo', 'acrescimo')
        ->assertJsonPath('data.status', 'aberta')
        ->assertJsonPath('data.observacao', 'Falta um')
        ->assertJsonPath('data.ofertas', []);
});

it('chefe abre vaga e auxiliar de fora nao abre', function () {
    $c = cenarioVaga();

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/vacancies", [], authEscala($c['chefe']))
        ->assertStatus(201);

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedules/{$c['escala']->id}/vacancies", [], authEscala($c['auxi']))
        ->assertStatus(403);
});

it('a oferta responde so o cartao publico, sem nomes alheios', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $resposta = $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem));

    $resposta->assertStatus(201)
        ->assertJsonPath('data.minha_oferta.status', 'pendente')
        ->assertJsonMissingPath('data.ofertas')
        ->assertJsonMissingPath('data.ofertas_pendentes')
        ->assertJsonMissingPath('data.escolhido_nome');
});

it('a dona ve o nome de quem se ofereceu', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem))->assertStatus(201);

    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/schedule-vacancies/{$c['vaga']->id}", authEscala($c['coord']))
        ->assertOk()
        ->assertJsonPath('data.ofertas_pendentes', 1)
        ->assertJsonPath('data.ofertas.0.nome', $quem->nome)
        ->assertJsonPath('data.ofertas.0.usuario_id', $quem->id);
});

it('dona escolhe a oferta; a pessoa entra confirmada e as outras ofertas sao descartadas', function () {
    $c = cenarioVaga();
    $escolhida = ofertante($c);
    $outra = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($escolhida))->assertStatus(201);
    $oferta = OfertaVagaEscala::query()->where('usuario_id', $escolhida->id)->firstOrFail();

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($outra))->assertStatus(201);

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/choose", ['oferta_id' => $oferta->id], authEscala($c['coord']))
        ->assertOk()
        ->assertJsonPath('data.status', 'preenchida')
        ->assertJsonPath('data.escolhido_nome', $escolhida->nome);

    expect(EscalaMembro::query()->where('escala_id', $c['escala']->id)->where('usuario_id', $escolhida->id)->value('status'))->toBe(EscalaMembro::STATUS_CONFIRMADO)
        ->and(OfertaVagaEscala::query()->where('usuario_id', $outra->id)->value('status'))->toBe(OfertaVagaEscala::STATUS_DESCARTADA);
});

it('pessoa ja escalada nao se oferece', function () {
    $c = cenarioVaga();

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($c['auxi']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_ja_escalado');
});

it('chefe nao escolhe a oferta (so a dona)', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem))->assertStatus(201);
    $oferta = OfertaVagaEscala::query()->where('usuario_id', $quem->id)->firstOrFail();

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/choose", ['oferta_id' => $oferta->id], authEscala($c['chefe']))
        ->assertStatus(403)
        ->assertJsonPath('code', 'nao_e_dona_da_escala');
});

it('leitor sem gerir ve a contagem e quem foi escolhido, nunca quem so se ofereceu', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem))->assertStatus(201);
    $this->app['auth']->forgetGuards();

    // Escolhido e fato publico do EBI; os nao escolhidos continuam sigilo.
    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/choose", ['oferta_id' => OfertaVagaEscala::query()->where('vaga_id', $c['vaga']->id)->value('id')], authEscala($c['coord']))
        ->assertOk();
    $this->app['auth']->forgetGuards();

    $this->getJson("/api/v1/schedule-vacancies/{$c['vaga']->id}", authEscala($c['ministro']))
        ->assertOk()
        ->assertJsonPath('data.status', 'preenchida')
        ->assertJsonPath('data.ofertas_pendentes', 0)
        ->assertJsonPath('data.escolhido_nome', $quem->nome)
        ->assertJsonMissingPath('data.ofertas');
});

it('sem escala.ler a pessoa so ve a propria oferta', function () {
    $c = cenarioVaga();
    $semPapel = Usuario::factory()->create();

    $resposta = $this->getJson("/api/v1/schedule-vacancies/{$c['vaga']->id}", authEscala($semPapel));

    $resposta->assertOk()
        ->assertJsonPath('data.id', $c['vaga']->id)
        ->assertJsonPath('data.minha_oferta', null)
        ->assertJsonMissingPath('data.ofertas_pendentes')
        ->assertJsonMissingPath('data.escolhido_nome');
});

it('cartao de minhas vagas lista as abertas para quem tem papel de equipe no EBI', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $resposta = $this->getJson('/api/v1/me/schedule-vacancies?inicio=2026-12-01&fim=2026-12-31', authEscala($quem));

    $resposta->assertOk()
        ->assertJsonPath('data.0.ebi_id', $c['ebi']->id)
        ->assertJsonPath('data.0.ebi_nome', $c['ebi']->nome)
        ->assertJsonPath('data.0.minha_oferta', null);

    expect($resposta->json('data'))->toBeArray()->toHaveCount(1);
    $this->assertSame((string) $c['vaga']->id, $resposta->json('data.0.id'));

    // Quem nao tem papel de equipe no EBI nao ve o cartao.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me/schedule-vacancies?inicio=2026-12-01&fim=2026-12-31', authEscala($c['ministro']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('cartao de minhas vagas esconde as escalas em que a pessoa ja esta', function () {
    $c = cenarioVaga();
    $equipeB = Equipe::factory()->create(['ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id]);
    EquipeMembro::query()->create(['equipe_id' => $equipeB->id, 'usuario_id' => $c['auxi']->id, 'incluido_por' => $c['coord']->id]);

    $this->getJson('/api/v1/me/schedule-vacancies?inicio=2026-12-01&fim=2026-12-31', authEscala($c['auxi']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('lista do EBI exige escala.ler e aceita filtro de status', function () {
    $c = cenarioVaga();

    $this->getJson("/api/v1/schedule-vacancies?ebi_id={$c['ebi']->id}&status=aberta", authEscala($c['coord']))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/schedule-vacancies?ebi_id={$c['ebi']->id}&status=preenchida", authEscala($c['coord']))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/schedule-vacancies?ebi_id={$c['ebi']->id}", authEscala(Usuario::factory()->create()))
        ->assertStatus(403);
});

it('detalhe da escala traz as vagas com os nomes para quem gere e sem eles para o leitor', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem))->assertStatus(201);

    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($c['coord']))
        ->assertOk()
        ->assertJsonPath('escala.vagas.0.id', $c['vaga']->id)
        ->assertJsonPath('escala.vagas.0.ofertas.0.usuario_id', $quem->id);

    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($c['ministro']))
        ->assertOk()
        ->assertJsonPath('escala.vagas.0.ofertas_pendentes', 1)
        ->assertJsonMissingPath('escala.vagas.0.ofertas');

    // Membros da escala entram pela excecao de propriedade: cartao publico.
    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($c['auxi']))
        ->assertOk()
        ->assertJsonPath('escala.vagas.0.id', $c['vaga']->id)
        ->assertJsonMissingPath('escala.vagas.0.ofertas_pendentes')
        ->assertJsonMissingPath('escala.vagas.0.substituido_nome')
        ->assertDontSee($quem->nome);
});

it('pessoa retira a propria oferta; oferta alheia responde 404', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem))->assertStatus(201);
    $oferta = OfertaVagaEscala::query()->where('usuario_id', $quem->id)->firstOrFail();

    $this->app['auth']->forgetGuards();
    $this->deleteJson("/api/v1/schedule-vacancy-offers/{$oferta->id}", [], authEscala($c['coord']))
        ->assertStatus(404);

    $this->app['auth']->forgetGuards();
    $this->deleteJson("/api/v1/schedule-vacancy-offers/{$oferta->id}", [], authEscala($quem))
        ->assertStatus(204);

    expect(OfertaVagaEscala::query()->findOrFail($oferta->id)->status)->toBe(OfertaVagaEscala::STATUS_RETIRADA);
});

it('dona cancela a vaga; vaga ja cancelada responde 409', function () {
    $c = cenarioVaga();

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/cancel", [], authEscala($c['coord']))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelada');

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/cancel", [], authEscala($c['coord']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'vaga_indisponivel');
});

it('vaga inexistente responde 404', function () {
    $c = cenarioVaga();

    $this->getJson('/api/v1/schedule-vacancies/vaga-inexistente', authEscala($c['coord']))
        ->assertStatus(404);
});

/**
 * Achado da revisao: o cartao decidia a projecao so pelo papel, e a chefe de
 * outro territorio (que nao alcanca a escala) recebia os nomes das ofertantes.
 */
it('chefe e adm_igreja de fora do territorio veem so o cartao publico da vaga', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem))->assertStatus(201);
    $this->app['auth']->forgetGuards();

    $irma = igrejaNoSetor(Setor::factory()->create(['regional_id' => Regional::factory()->create()->id]));
    $outraIgreja = $irma['igreja'];
    $outroEbi = $irma['ebi'];

    foreach ([['coordenadora_chefe', 'ebi', $outroEbi->id], ['adm_igreja', 'igreja', $outraIgreja->id]] as [$papel, $tipo, $escopoId]) {
        $this->app['auth']->forgetGuards();
        $forasteiro = membroEscala($papel, $tipo, $escopoId);

        $this->getJson("/api/v1/schedule-vacancies/{$c['vaga']->id}", authEscala($forasteiro))
            ->assertOk()
            ->assertJsonPath('data.escolhido_nome', null)
            ->assertJsonMissingPath('data.ofertas')
            ->assertJsonMissingPath('data.ofertas_pendentes')
            ->assertDontSee($quem->nome);
    }
});

it('a dona que perdeu o vinculo de coordenadora hoje nao recebe os nomes das ofertas', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem))->assertStatus(201);
    $this->app['auth']->forgetGuards();

    // Vinculo da dona com o EBI encerrado hoje: ela nao escolhe nem ve os nomes.
    VinculoAcesso::query()
        ->where('usuario_id', $c['coord']->id)
        ->update(['fim' => now()->toDateString()]);

    $this->getJson("/api/v1/schedule-vacancies/{$c['vaga']->id}", authEscala($c['coord']))
        ->assertOk()
        ->assertJsonPath('data.escolhido_nome', null)
        ->assertJsonMissingPath('data.ofertas')
        ->assertDontSee($quem->nome);
});

it('pessoa revogada hoje nao oferece nem e escolhida', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);
    $id = $c['vaga']->id;

    $this->postJson("/api/v1/schedule-vacancies/{$id}/offers", [], authEscala($quem))->assertStatus(201);
    $oferta = OfertaVagaEscala::query()->where('vaga_id', $id)->firstOrFail();

    // `fim = hoje` e como o revogar grava: ja nao e vinculo de equipe vigente.
    VinculoAcesso::query()
        ->where('usuario_id', $quem->id)
        ->update(['fim' => now()->toDateString()]);

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$id}/offers", [], authEscala($quem))
        ->assertStatus(404);

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$id}/choose", ['oferta_id' => $oferta->id], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_inelegivel');

    expect($c['vaga']->fresh()->status)->toBe(VagaEscala::STATUS_ABERTA)
        ->and($oferta->fresh()->status)->toBe(OfertaVagaEscala::STATUS_PENDENTE)
        ->and(EscalaMembro::query()->where('escala_id', $c['escala']->id)->where('usuario_id', $quem->id)->exists())->toBeFalse();
});

it('a dona ve quem e substituido e todas as ofertas, inclusive retiradas', function () {
    $c = cenarioVaga();
    $original = EscalaMembro::query()->where('escala_id', $c['escala']->id)->where('usuario_id', $c['auxi']->id)->firstOrFail();
    $desiste = ofertante($c);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/vacancies", ['substitui_membro_id' => $original->id], authEscala($c['coord']))
        ->assertStatus(201);
    $vaga = VagaEscala::query()->where('substitui_membro_id', $original->id)->firstOrFail();

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$vaga->id}/offers", [], authEscala($desiste))->assertStatus(201);
    $oferta = OfertaVagaEscala::query()->where('vaga_id', $vaga->id)->firstOrFail();

    $this->app['auth']->forgetGuards();
    $this->deleteJson("/api/v1/schedule-vacancy-offers/{$oferta->id}", [], authEscala($desiste))->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/schedule-vacancies/{$vaga->id}", authEscala($c['coord']))
        ->assertOk()
        ->assertJsonPath('data.substituido_nome', $c['auxi']->nome)
        ->assertJsonPath('data.ofertas.0.id', $oferta->id)
        ->assertJsonPath('data.ofertas.0.status', 'retirada');
});

it('escolher com oferta de outra vaga responde 422 e nao preenche nada', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/offers", [], authEscala($quem))->assertStatus(201);
    $oferta = OfertaVagaEscala::query()->where('vaga_id', $c['vaga']->id)->firstOrFail();

    $outra = VagaEscala::query()->create([
        'escala_id' => $c['escala']->id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
        'status' => VagaEscala::STATUS_ABERTA,
        'aberta_por' => $c['coord']->id,
    ]);

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$outra->id}/choose", ['oferta_id' => $oferta->id], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'oferta_invalida');

    expect($outra->fresh()->status)->toBe(VagaEscala::STATUS_ABERTA)
        ->and($oferta->fresh()->status)->toBe(OfertaVagaEscala::STATUS_PENDENTE);
});

it('escolher com oferta inexistente responde 404 e nao quebra', function () {
    $c = cenarioVaga();

    $this->postJson("/api/v1/schedule-vacancies/{$c['vaga']->id}/choose", ['oferta_id' => 'oferta-inexistente'], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'oferta_invalida');
});

it('substituicao escolhida marca o original como substituido', function () {
    $c = cenarioVaga();
    $original = EscalaMembro::query()->where('escala_id', $c['escala']->id)->where('usuario_id', $c['auxi']->id)->firstOrFail();
    $forasteiro = ofertante($c);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/vacancies", ['substitui_membro_id' => $original->id], authEscala($c['coord']))
        ->assertStatus(201)
        ->assertJsonPath('data.tipo', 'substituicao');

    $vaga = VagaEscala::query()->where('substitui_membro_id', $original->id)->firstOrFail();

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$vaga->id}/offers", [], authEscala($forasteiro))->assertStatus(201);
    $oferta = OfertaVagaEscala::query()->where('vaga_id', $vaga->id)->firstOrFail();

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/schedule-vacancies/{$vaga->id}/choose", ['oferta_id' => $oferta->id], authEscala($c['coord']))
        ->assertOk();

    expect(EscalaMembro::query()->findOrFail($original->id)->status)->toBe(EscalaMembro::STATUS_SUBSTITUIDO)
        ->and(EscalaMembro::query()->where('escala_id', $c['escala']->id)->where('usuario_id', $forasteiro->id)->value('substitui_membro_id'))->toBe((string) $original->id);
});

it('vaga de sessao cancelada aparece expirada; sessao prevista nao', function () {
    $c = cenarioVaga();
    $quem = ofertante($c);

    $this->getJson("/api/v1/schedule-vacancies/{$c['vaga']->id}", authEscala($quem))
        ->assertOk()
        ->assertJsonPath('data.sessao.id', (string) $c['sessao']->id)
        ->assertJsonPath('data.expirada', false);

    $c['sessao']->forceFill(['status' => Sessao::STATUS_CANCELADA])->save();

    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/schedule-vacancies/{$c['vaga']->id}", authEscala($quem))
        ->assertOk()
        ->assertJsonPath('data.expirada', true);
});

it('escala em outro EBI responde 404 para quem abre vaga', function () {
    $c = cenarioVaga();
    $outraIgreja = Igreja::factory()->create();
    $outroEbi = Ebi::factory()->create(['igreja_id' => $outraIgreja->id]);
    $outraSessao = Sessao::factory()->create(['ebi_id' => $outroEbi->id, 'igreja_id' => $outraIgreja->id]);
    $outraEscala = Escala::factory()->create(['sessao_id' => $outraSessao->id, 'ebi_id' => $outroEbi->id, 'igreja_id' => $outraIgreja->id]);

    $this->postJson("/api/v1/schedules/{$outraEscala->id}/vacancies", [], authEscala($c['coord']))
        ->assertStatus(404);
});
