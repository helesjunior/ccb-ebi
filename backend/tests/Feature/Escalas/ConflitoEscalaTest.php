<?php

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use App\Domain\Sessoes\Models\Sessao;
use Database\Seeders\PapelSeeder;

/**
 * CA-12: a mesma pessoa escalada em duas igrejas em horarios que se sobrepoem.
 * O aviso nao bloqueia a escala e os nomes so aparecem para quem alcanca o
 * outro EBI (ou para a propria pessoa).
 *
 * `cenarioEscala()`/`membroEscala()`/`authEscala()` vem de EscalaGestaoTest.
 *
 * @return array<string, mixed>
 */
function cenarioConflito(array $segundaSessao = []): array
{
    $a = cenarioEscala();

    $igrejaB = Igreja::factory()->create(['nome' => 'Igreja B', 'timezone' => 'America/Sao_Paulo']);
    $ebiB = Ebi::factory()->create(['igreja_id' => $igrejaB->id]);
    $sessaoB = Sessao::factory()->create($segundaSessao + [
        'ebi_id' => $ebiB->id, 'igreja_id' => $igrejaB->id,
        'inicio_previsto' => '2026-12-06 00:00:00', 'fim_previsto' => '2026-12-06 01:30:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);

    $escalaA = Escala::factory()->create(['sessao_id' => $a['sessao']->id, 'ebi_id' => $a['ebi']->id, 'igreja_id' => $a['igreja']->id]);
    EscalaMembro::factory()->for($escalaA)->create(['usuario_id' => $a['auxi']->id]);

    // A dona da escala B e a coordenadora do EBI B; a escala B nasce da equipe dela.
    $coordB = membroEscala('coordenadora', 'ebi', $ebiB->id);
    $equipeB = Equipe::query()->create([
        'ebi_id' => $ebiB->id, 'igreja_id' => $igrejaB->id,
        'coordenadora_id' => $coordB->id, 'criado_por' => $coordB->id,
    ]);

    $escalaB = Escala::factory()->create([
        'sessao_id' => $sessaoB->id, 'ebi_id' => $ebiB->id, 'igreja_id' => $igrejaB->id,
        'equipe_id' => $equipeB->id,
    ]);
    EscalaMembro::factory()->for($escalaB)->create(['usuario_id' => $a['auxi']->id]);

    return $a + [
        'igreja_b' => $igrejaB,
        'ebi_b' => $ebiB,
        'sessao_b' => $sessaoB,
        'escala_a' => $escalaA,
        'escala_b' => $escalaB,
        'equipe_b' => $equipeB,
        'coord_b' => $coordB,
    ];
}

/** Pessoa com papel cujo escopo alcanca os dois EBIs. */
function gestorDosDoisEbis(array $c): array
{
    $regional = Regional::factory()->create();
    $setor = Setor::factory()->create(['regional_id' => $regional->id]);
    foreach ([$c['igreja'], $c['igreja_b']] as $igreja) {
        IgrejaVinculacao::query()->create([
            'igreja_id' => $igreja->id, 'setor_id' => $setor->id,
            'inicio' => now()->subMonth()->toDateString(), 'fim' => null,
        ]);
    }

    $gestor = membroEscala('adm_setor', 'setor', $setor->id);

    return ['auth' => authEscala($gestor), 'usuario' => $gestor];
}

/** Vinculo adicional de coordenadora no outro EBI, com o token da pessoa. */
function tambemCoordena(Usuario $pessoa, string $ebiId): array
{
    VinculoAcesso::query()->create([
        'usuario_id' => $pessoa->id,
        'papel_id' => Papel::query()->where('codigo', 'coordenadora')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $ebiId,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
        'status' => 'ativo',
    ]);

    return authEscala($pessoa);
}

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('quem gere so o EBI de A ve o conflito com a igreja oculta', function () {
    $c = cenarioConflito();
    // A coordenadora de A nao alcanca o EBI B.

    $this->getJson("/api/v1/schedules/{$c['escala_a']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.membros.0.conflitos.0.sessao_id', null)
        ->assertJsonPath('escala.membros.0.conflitos.0.inicio', $c['sessao_b']->inicio_previsto->toIso8601String())
        ->assertJsonPath('escala.membros.0.conflitos.0.igreja_nome', null)
        ->assertJsonPath('escala.membros.0.conflitos.0.ebi_nome', null);
});

it('quem alcanca os dois EBIs ve os nomes do conflito', function () {
    $c = cenarioConflito();
    $gestor = gestorDosDoisEbis($c);

    $this->getJson("/api/v1/schedules/{$c['escala_a']->id}", $gestor['auth'])
        ->assertStatus(200)
        ->assertJsonPath('escala.membros.0.conflitos.0.sessao_id', $c['sessao_b']->id)
        ->assertJsonPath('escala.membros.0.conflitos.0.igreja_nome', 'Igreja B')
        ->assertJsonPath('escala.membros.0.conflitos.0.ebi_nome', $c['ebi_b']->nome);
});

it('sessoes que apenas se encostam nao sao conflito', function () {
    $c = cenarioConflito([
        'inicio_previsto' => '2026-12-06 01:00:00', 'fim_previsto' => '2026-12-06 02:30:00',
    ]);

    $this->getJson("/api/v1/schedules/{$c['escala_a']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.membros.0.conflitos', []);
});

it('sessao cancelada nao gera conflito', function () {
    $c = cenarioConflito(['status' => Sessao::STATUS_CANCELADA]);

    $this->getJson("/api/v1/schedules/{$c['escala_a']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.membros.0.conflitos', []);
});

it('membro substituido nao gera conflito', function () {
    $c = cenarioConflito();
    EscalaMembro::query()->where('escala_id', $c['escala_b']->id)
        ->update(['status' => EscalaMembro::STATUS_SUBSTITUIDO]);

    $this->getJson("/api/v1/schedules/{$c['escala_a']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.membros.0.conflitos', []);
});

it('a propria pessoa ve os nomes do conflito', function () {
    $c = cenarioConflito();
    // A auxiliar de A tambem esta escalada em B (a chefe de B a escalou) e,
    // como coordenadora de A, abre a escala de A para ver o proprio conflito.
    EscalaMembro::factory()->for($c['escala_b'])->create(['usuario_id' => $c['auxi']->id]);
    tambemCoordena($c['auxi'], $c['ebi']->id);
    $pessoa = $c['auxi'];

    $this->getJson("/api/v1/schedules/{$c['escala_a']->id}", authEscala($pessoa) + [
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$c['ebi']->id}",
    ])
        ->assertStatus(200)
        ->assertJsonCount(1, 'escala.membros')
        ->assertJsonPath('escala.membros.0.usuario_id', $pessoa->id)
        ->assertJsonPath('escala.membros.0.conflitos.0.sessao_id', $c['sessao_b']->id)
        ->assertJsonPath('escala.membros.0.conflitos.0.igreja_nome', $c['igreja_b']->nome)
        ->assertJsonPath('escala.membros.0.conflitos.0.ebi_nome', $c['ebi_b']->nome);
});

it('incluir a pessoa com conflito nao bloqueia', function () {
    $c = cenarioConflito();
    // A pessoa integra a equipe do EBI B (a coordenadora de B a inclui)...
    $pessoa = membroEscala('auxiliar', 'ebi', $c['ebi_b']->id);
    // ...e ja esta escalada em A no mesmo horario da sessao de B.
    EscalaMembro::factory()->for($c['escala_a'])->create([
        'usuario_id' => $pessoa->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ]);

    $resposta = $this->postJson("/api/v1/schedules/{$c['escala_b']->id}/members", [
        'usuario_id' => $pessoa->id,
        'funcao' => 'auxiliar',
    ], authEscala($c['coord_b']))->assertStatus(200);

    $incluso = collect($resposta->json('escala.membros'))->firstWhere('usuario_id', $pessoa->id);

    expect($incluso)->not->toBeNull()
        ->and($incluso['conflitos'])->toHaveCount(1)
        ->and($incluso['conflitos'][0]['inicio'])->toBe($c['sessao']->inicio_previsto->toIso8601String())
        ->and($incluso['conflitos'][0]['sessao_id'])->toBeNull();

    expect(EscalaMembro::query()
        ->where('escala_id', $c['escala_b']->id)
        ->where('usuario_id', $pessoa->id)
        ->exists())->toBeTrue();
});
