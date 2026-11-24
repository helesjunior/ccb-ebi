<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Sessoes\Models\Sessao;
use Database\Seeders\PapelSeeder;

/**
 * Aprovacao da escala (RF-07): quem aprova e a coordenadora chefe vigente do
 * EBI; sem chefe, o `adm_igreja` da igreja. As demais guardas rodam antes de
 * gravar e nao alteram a escala quando recusam.
 *
 * `cenarioEscala()`, `membroEscala()` e `authEscala()` vem de EscalaGestaoTest.
 *
 * @return array<string, mixed>
 */
function cenarioAprovacao(array $opcoes = []): array
{
    $c = cenarioEscala();

    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id,
        'ebi_id' => $c['ebi']->id,
        'igreja_id' => $c['igreja']->id,
        'estimativa_participantes' => $opcoes['estimativa'] ?? 5,
        'participantes_por_auxiliar' => 4,
        'status' => Escala::STATUS_PREVISTA,
    ]);

    // A chefe entra na escala para o caso de aprovacao pela propria chefe; quem
    // derruba a chefe passa `['membros' => ...]` sem ela.
    $membros = $opcoes['membros'] ?? [
        ['usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA],
        ['usuario_id' => $c['auxi']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR],
        ['usuario_id' => $c['chefe']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR],
    ];

    foreach ($membros as $membro) {
        EscalaMembro::factory()->for($escala)->create($membro);
    }

    return $c + ['escala' => $escala];
}

/** Cabecalho de atuacao explicito para quem acumula vinculos. */
function escopoAprovacao(string $papel, string $tipo, string $id): array
{
    return ['X-Acting-Scope' => "papel={$papel};escopo={$tipo}:{$id}"];
}

/** Revoga todos os vinculos de coordenadora chefe do EBI (e libera a vaga). */
function revogarChefe(string $ebiId): void
{
    VinculoAcesso::query()
        ->where('chefe_de_ebi_id', $ebiId)
        ->update(['fim' => now()->subDay()->toDateString(), 'chefe_de_ebi_id' => null]);

    VinculoAcesso::query()
        ->where('tipo_escopo', 'ebi')
        ->where('escopo_id', $ebiId)
        ->where('fim', null)
        ->whereIn('papel_id', Papel::query()->where('codigo', 'coordenadora_chefe')->select('id'))
        ->update(['fim' => now()->subDay()->toDateString(), 'chefe_de_ebi_id' => null]);
}

/**
 * Sem chefe vigente e com a equipe minima coberta: uma coordenadora e duas
 * auxiliares (estimativa 5, divisor 4). A vaga de chefe e liberada.
 *
 * @param  array<string, mixed>  $opcoes  `estimativa` e `membros` viram do
 *                                        `cenarioAprovacao`.
 * @return array<string, mixed>
 */
function cenarioSemChefe(array $opcoes = []): array
{
    $c = cenarioAprovacao(['membros' => []] + $opcoes);
    revogarChefe($c['ebi']->id);

    $membros = [
        ['usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA],
        ['usuario_id' => membroEscala('auxiliar', 'ebi', $c['ebi']->id)->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR],
        ['usuario_id' => membroEscala('auxiliar', 'ebi', $c['ebi']->id)->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR],
    ];

    foreach ($opcoes['membros'] ?? $membros as $membro) {
        EscalaMembro::factory()->for($c['escala'])->create($membro);
    }

    $c['adm'] = membroEscala('adm_igreja', 'igreja', $c['igreja']->id);

    return $c;
}

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('a coordenadora chefe aprova a escala', function () {
    $c = cenarioAprovacao();

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['chefe']))
        ->assertStatus(200)
        ->assertJsonPath('escala.status', 'aprovada')
        ->assertJsonPath('escala.situacao', 'aprovada')
        ->assertJsonPath('escala.aprovado_por', $c['chefe']->id);

    expect($c['escala']->refresh()->aprovado_em)->not->toBeNull()
        ->and(EventoAuditoria::query()->where('acao', 'escala.aprovada')->exists())->toBeTrue();
});

it('com chefe vigente o adm_igreja nao aprova', function () {
    $c = cenarioAprovacao();
    $adm = membroEscala('adm_igreja', 'igreja', $c['igreja']->id);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($adm))
        ->assertStatus(403);

    expect($c['escala']->refresh()->status)->toBe(Escala::STATUS_PREVISTA);
});

it('coordenadora sem ser chefe nao aprova', function () {
    $c = cenarioAprovacao();

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['coord']))
        ->assertStatus(403);
});

it('a chefe aprova a escala em que ela mesma esta', function () {
    $c = cenarioAprovacao();

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['chefe']))
        ->assertStatus(200)
        ->assertJsonPath('escala.status', 'aprovada')
        ->assertJsonFragment(['usuario_id' => $c['chefe']->id]);
});

it('sem chefe vigente o adm_igreja aprova', function () {
    $c = cenarioSemChefe();

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['adm']))
        ->assertStatus(200)
        ->assertJsonPath('escala.status', 'aprovada');
});
it('aprovar de novo responde 409', function () {
    $c = cenarioAprovacao();
    $c['escala']->forceFill(['status' => Escala::STATUS_APROVADA])->save();

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['chefe']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'escala_em_estado_invalido');
});

it('abaixo do minimo responde 422 equipe_minima_nao_atingida', function () {
    $c = cenarioSemChefe(['estimativa' => 20]);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['adm']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'equipe_minima_nao_atingida');

    expect($c['escala']->refresh()->status)->toBe(Escala::STATUS_PREVISTA);
});
it('membro com vinculo revogado responde 422 membro_sem_vinculo', function () {
    $c = cenarioAprovacao();
    VinculoAcesso::query()->where('usuario_id', $c['auxi']->id)->update(['fim' => now()->subDay()->toDateString()]);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['chefe']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_sem_vinculo');

    expect($c['escala']->refresh()->status)->toBe(Escala::STATUS_PREVISTA);
});

it('sessao encerrada responde 409', function () {
    $c = cenarioAprovacao();
    Sessao::query()->whereKey($c['sessao']->id)->update(['status' => Sessao::STATUS_ENCERRADA]);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['chefe']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'sessao_em_estado_invalido');
});

it('estimativa zero com uma coordenadora aprova', function () {
    $c = cenarioAprovacao(['estimativa' => 0, 'membros' => []]);
    EscalaMembro::factory()->for($c['escala'])->create([
        'usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA,
    ]);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['chefe']))
        ->assertStatus(200)
        ->assertJsonPath('escala.status', 'aprovada');
});

it('conflito aberto nao impede e vem na resposta', function () {
    $c = cenarioAprovacao();
    // A chefia esta escalada tambem numa sessao do mesmo horario em outro EBI.
    $igrejaB = Igreja::factory()->create(['nome' => 'Igreja B', 'timezone' => 'America/Sao_Paulo']);
    $ebiB = Ebi::factory()->create(['igreja_id' => $igrejaB->id]);
    $sessaoB = Sessao::factory()->create([
        'ebi_id' => $ebiB->id, 'igreja_id' => $igrejaB->id,
        'inicio_previsto' => '2026-12-05 23:30:00', 'fim_previsto' => '2026-12-06 00:30:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
    $escalaB = Escala::factory()->create([
        'sessao_id' => $sessaoB->id, 'ebi_id' => $ebiB->id, 'igreja_id' => $igrejaB->id,
        'estimativa_participantes' => 0, 'participantes_por_auxiliar' => 4, 'status' => Escala::STATUS_PREVISTA,
    ]);
    EscalaMembro::factory()->for($escalaB)->create([
        'usuario_id' => $c['chefe']->id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ]);

    $resposta = $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['chefe']))
        ->assertStatus(200)
        ->assertJsonPath('escala.status', 'aprovada');

    // A chefe alcança o outro EBI? Aqui ela e da equipe de A: so a propria
    // pessoa ve o nome; de qualquer forma o horario vem.
    $chefe = collect($resposta->json('escala.membros'))->firstWhere('usuario_id', $c['chefe']->id);
    expect($chefe['conflitos'])->toHaveCount(1)
        ->and($chefe['conflitos'][0]['inicio'])->toBe('2026-12-05T23:30:00+00:00');
});

it('quem tem os dois papeis aprova so como chefe', function () {
    // A escala so tem a coordenadora (equipe minima de estimativa 0).
    $c = cenarioAprovacao(['estimativa' => 0, 'membros' => []]);
    EscalaMembro::factory()->for($c['escala'])->create([
        'usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA,
    ]);

    // A vaga de chefe sai da chefe do cenario para a coordenadora de A, que
    // passa a acumular os dois papeis.
    revogarChefe($c['ebi']->id);
    VinculoAcesso::query()->create([
        'usuario_id' => $c['coord']->id,
        'papel_id' => Papel::query()->where('codigo', 'coordenadora_chefe')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $c['ebi']->id,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
        'status' => 'ativo',
    ]);

    // Atuando como coordenadora nao aprova; como chefe, sim.
    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['coord']) + escopoAprovacao('coordenadora', 'ebi', $c['ebi']->id))
        ->assertStatus(403);

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['coord']) + escopoAprovacao('coordenadora_chefe', 'ebi', $c['ebi']->id))
        ->assertStatus(200)
        ->assertJsonPath('escala.aprovado_por', $c['coord']->id);
});

it('revogada a chefe, o adm_igreja aprova e a ex-chefe nao aprova mais', function () {
    $c = cenarioSemChefe();

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['adm']))
        ->assertStatus(200)
        ->assertJsonPath('escala.status', 'aprovada')
        ->assertJsonPath('escala.aprovado_por', $c['adm']->id);
});

it('com a escala ja aprovada a ex-chefe nao reabre', function () {
    $c = cenarioSemChefe();
    $c['escala']->forceFill(['status' => Escala::STATUS_APROVADA])->save();

    $this->postJson("/api/v1/schedules/{$c['escala']->id}/approve", [], authEscala($c['chefe']))
        ->assertStatus(403);
});
