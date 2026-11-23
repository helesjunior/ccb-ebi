<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Sessoes\Models\Sessao;
use Database\Seeders\PapelSeeder;

/**
 * Confirmacao do membro (RF-07) e "minhas escalas": quem foi escalado confirma
 * a propria vaga, ve a escala em que esta e enxerga os proprios conflitos.
 *
 * `cenarioEscala()`, `membroEscala()` e `authEscala()` vem de EscalaGestaoTest.
 *
 * @return array<string, mixed>
 */
function cenarioConfirmacao(array $opcoes = []): array
{
    $c = cenarioEscala();

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

/** Membro da auxiliar na escala do cenario. */
function vinculoDaAuxiliar(array $c): EscalaMembro
{
    return $c['vinculos'][(string) $c['auxi']->id];
}

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('a pessoa escala confirmada confirma a propria vaga', function () {
    $c = cenarioConfirmacao();
    $membro = vinculoDaAuxiliar($c);

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($c['auxi']))
        ->assertStatus(200)
        ->assertJsonPath('membro.status', 'confirmado')
        ->assertJsonPath('membro.id', $membro->id)
        ->assertJsonPath('conflitos', []);

    expect($membro->refresh()->confirmado_em)->not->toBeNull()
        ->and(EventoAuditoria::query()->where('acao', 'escala.membro_confirmado')->count())->toBe(1);
});

it('confirmar de novo responde 200 sem novo evento', function () {
    $c = cenarioConfirmacao();
    $membro = vinculoDaAuxiliar($c);

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($c['auxi']))->assertStatus(200);
    $confirmadoEm = $membro->refresh()->confirmado_em;

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($c['auxi']))
        ->assertStatus(200)
        ->assertJsonPath('membro.status', 'confirmado');

    expect(EventoAuditoria::query()->where('acao', 'escala.membro_confirmado')->count())->toBe(1)
        ->and($membro->refresh()->confirmado_em?->toIso8601String())->toBe($confirmadoEm?->toIso8601String());
});

it('escala prevista nao aceita confirmacao e aparece como prevista em minhas escalas', function () {
    $c = cenarioConfirmacao(['status' => Escala::STATUS_PREVISTA]);
    $membro = vinculoDaAuxiliar($c);

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($c['auxi']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'escala_em_estado_invalido');

    expect($membro->refresh()->status)->toBe(EscalaMembro::STATUS_ESCALADO);

    $this->getJson('/api/v1/me/schedules?inicio=2026-12-01&fim=2026-12-31', authEscala($c['auxi']))
        ->assertStatus(200)
        ->assertJsonPath('data.0.escala_id', $c['escala']->id)
        ->assertJsonPath('data.0.escala_status', 'prevista');
});

it('ninguem confirma a vaga de outra pessoa', function () {
    $c = cenarioConfirmacao();
    $membro = vinculoDaAuxiliar($c);
    $outro = membroEscala('auxiliar', 'ebi', $c['ebi']->id);

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($outro))
        ->assertStatus(404);

    expect($membro->refresh()->status)->toBe(EscalaMembro::STATUS_ESCALADO);
});

it('membro com vinculo revogado nao confirma', function () {
    $c = cenarioConfirmacao();
    $membro = vinculoDaAuxiliar($c);
    VinculoAcesso::query()->where('usuario_id', $c['auxi']->id)->update(['fim' => now()->subDay()->toDateString()]);

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($c['auxi']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_sem_vinculo');

    expect($membro->refresh()->status)->toBe(EscalaMembro::STATUS_ESCALADO);
});

it('sessao encerrada nao aceita confirmacao', function () {
    $c = cenarioConfirmacao();
    $membro = vinculoDaAuxiliar($c);
    Sessao::query()->whereKey($c['sessao']->id)->update(['status' => Sessao::STATUS_ENCERRADA]);

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($c['auxi']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'sessao_em_estado_invalido');
});

it('membro substituido nao confirma', function () {
    $c = cenarioConfirmacao();
    $membro = vinculoDaAuxiliar($c);
    $membro->forceFill(['status' => EscalaMembro::STATUS_SUBSTITUIDO])->save();

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($c['auxi']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'membro_em_estado_invalido');
});

/**
 * A auxiliar escalada tambem numa sessao do mesmo horario em outro EBI. Ela so
 * tem papel no EBI de A, mas o conflito e dela: ve o nome da outra igreja.
 *
 * @return array<string, mixed>
 */
function cenarioConfirmacaoEmConflito(): array
{
    $c = cenarioConfirmacao();

    $igrejaB = Igreja::factory()->create(['nome' => 'Igreja B', 'timezone' => 'America/Sao_Paulo']);
    $ebiB = Ebi::factory()->create(['igreja_id' => $igrejaB->id]);
    $sessaoB = Sessao::factory()->create([
        'ebi_id' => $ebiB->id, 'igreja_id' => $igrejaB->id,
        'inicio_previsto' => '2026-12-06 00:00:00', 'fim_previsto' => '2026-12-06 01:30:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
    $escalaB = Escala::factory()->create([
        'sessao_id' => $sessaoB->id, 'ebi_id' => $ebiB->id, 'igreja_id' => $igrejaB->id,
        'status' => Escala::STATUS_APROVADA,
    ]);
    EscalaMembro::factory()->for($escalaB)->create(['usuario_id' => $c['auxi']->id]);

    return $c + ['sessao_b' => $sessaoB, 'escala_b' => $escalaB];
}

it('a confirmacao traz o conflito com o nome da outra igreja', function () {
    $c = cenarioConfirmacaoEmConflito();
    $membro = vinculoDaAuxiliar($c);

    $this->postJson("/api/v1/schedule-members/{$membro->id}/confirm", [], authEscala($c['auxi']))
        ->assertStatus(200)
        ->assertJsonPath('membro.status', 'confirmado')
        ->assertJsonPath('conflitos.0.sessao_id', $c['sessao_b']->id)
        ->assertJsonPath('conflitos.0.inicio', $c['sessao_b']->inicio_previsto->toIso8601String())
        ->assertJsonPath('conflitos.0.igreja_nome', 'Igreja B');
});

it('minhas escalas lista a propria escala com o conflito nomeado', function () {
    $c = cenarioConfirmacaoEmConflito();
    $membro = vinculoDaAuxiliar($c);

    $this->getJson('/api/v1/me/schedules?inicio=2026-12-01&fim=2026-12-31', authEscala($c['auxi']))
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.escala_id', $c['escala']->id)
        ->assertJsonPath('data.0.membro_id', $membro->id)
        ->assertJsonPath('data.0.sessao_id', $c['sessao']->id)
        ->assertJsonPath('data.0.sessao_status', 'prevista')
        ->assertJsonPath('data.0.escala_status', 'aprovada')
        ->assertJsonPath('data.0.funcao', 'auxiliar')
        ->assertJsonPath('data.0.status', 'escalado')
        ->assertJsonPath('data.0.presente', null)
        ->assertJsonPath('data.0.conflitos.0.igreja_nome', 'Igreja B')
        // A segunda e a de outro EBI, vista tambem por ela mesma.
        ->assertJsonPath('data.1.escala_id', $c['escala_b']->id);
});

it('minhas escalas so mostra quem esta escalado', function () {
    $c = cenarioConfirmacao();
    $foraDaEscala = membroEscala('auxiliar', 'ebi', $c['ebi']->id);

    $this->getJson('/api/v1/me/schedules?inicio=2026-12-01&fim=2026-12-31', authEscala($foraDaEscala))
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('membro substituido sai de minhas escalas', function () {
    $c = cenarioConfirmacao();
    vinculoDaAuxiliar($c)->forceFill(['status' => EscalaMembro::STATUS_SUBSTITUIDO])->save();

    $this->getJson('/api/v1/me/schedules?inicio=2026-12-01&fim=2026-12-31', authEscala($c['auxi']))
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('minhas escalas recusa intervalo maior que 92 dias', function () {
    $c = cenarioConfirmacao();

    $this->getJson('/api/v1/me/schedules?inicio=2026-01-01&fim=2026-06-01', authEscala($c['auxi']))
        ->assertStatus(422);
});

it('minhas escalas exige o periodo', function () {
    $c = cenarioConfirmacao();

    $this->getJson('/api/v1/me/schedules', authEscala($c['auxi']))->assertStatus(422);
});

it('a auxiliar le a propria escala sem escala.ler', function () {
    $c = cenarioConfirmacao();

    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($c['auxi']))
        ->assertStatus(200)
        ->assertJsonPath('escala.id', $c['escala']->id)
        ->assertJsonPath('escala.status', 'aprovada');
});

it('a auxiliar nao le escala em que nao esta', function () {
    $c = cenarioConfirmacao();
    $outraEscala = Escala::factory()->create();

    $this->getJson("/api/v1/schedules/{$outraEscala->id}", authEscala($c['auxi']))
        ->assertStatus(403);
});

it('quem saiu da escala deixa de ler', function () {
    $c = cenarioConfirmacao();
    vinculoDaAuxiliar($c)->delete();

    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($c['auxi']))
        ->assertStatus(403);
});

it('a coordenadora continua lendo pelo papel', function () {
    $c = cenarioConfirmacao();

    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($c['coord']))
        ->assertStatus(200);
});

it('usuario sem escala e sem papel nao le a escala', function () {
    $c = cenarioConfirmacao();
    /** @var Usuario $estranho */
    $estranho = Usuario::factory()->create();

    $this->getJson("/api/v1/schedules/{$c['escala']->id}", authEscala($estranho))
        ->assertStatus(403);
});
