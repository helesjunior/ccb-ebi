<?php

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\SessaoCorrecao;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Carbon;

/**
 * Aviso de equipe minima na sessao (E07, RF-07): quando ha escala aprovada,
 * quem conta sao os auxiliares da escala (confirmados e vigentes na data) e o
 * campo antigo deixa de ser editavel. Os helpers de cenario vem de
 * `EscalaGestaoTest` (Pest inclui o diretorio).
 */
function cenarioAviso(array $opcoes = []): array
{
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
        'estimativa_participantes' => 0, 'participantes_por_auxiliar' => 4,
        'status' => $opcoes['status'] ?? Escala::STATUS_APROVADA,
    ]);

    EscalaMembro::factory()->for($escala)->create([
        'usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA,
        'status' => EscalaMembro::STATUS_CONFIRMADO, 'confirmado_em' => now(),
    ]);
    EscalaMembro::factory()->for($escala)->create([
        'usuario_id' => $c['auxi']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
        'status' => EscalaMembro::STATUS_CONFIRMADO, 'confirmado_em' => now(),
    ]);
    EscalaMembro::factory()->for($escala)->create([
        'usuario_id' => membroEscala('auxiliar', 'ebi', $c['ebi']->id)->id,
        'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
        'status' => EscalaMembro::STATUS_CONFIRMADO, 'confirmado_em' => now(),
    ]);

    // Presentes vem das presencas validas, nao de um campo: 4 presencas = 1 auxiliar.
    foreach (range(1, 4) as $i) {
        Presenca::factory()->create(['sessao_id' => $c['sessao']->id, 'status' => Presenca::STATUS_VALIDA]);
    }
    $c['sessao']->forceFill(['auxiliares_escalados' => 7])->save();

    return $c + ['escala' => $escala];
}

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('com escala aprovada, o aviso vem da escala e o campo antigo e recusado', function () {
    $c = cenarioAviso();

    $this->getJson("/api/v1/sessions/{$c['sessao']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('equipe_minima.origem', 'escala')
        ->assertJsonPath('equipe_minima.auxiliares_necessarios', 1)
        ->assertJsonPath('equipe_minima.auxiliares_informados', 2)
        ->assertJsonPath('equipe_minima.alerta', false);

    $this->patchJson("/api/v1/sessions/{$c['sessao']->id}", ['auxiliares_escalados' => 7], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'escala_aprovada_define_equipe');

    expect($c['sessao']->refresh()->auxiliares_escalados)->toBe(7)
        ->and(SessaoCorrecao::query()->count())->toBe(0);
});

it('auxiliar revogado antes da data nao conta na escala', function () {
    $c = cenarioAviso();
    $revogado = $c['escala']->membros()->where('funcao', EscalaMembro::FUNCAO_AUXILIAR)->orderBy('created_at')->firstOrFail();
    VinculoAcesso::query()->where('usuario_id', $revogado->usuario_id)->update(['fim' => '2026-12-04']);

    $this->getJson("/api/v1/sessions/{$c['sessao']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('equipe_minima.origem', 'escala')
        ->assertJsonPath('equipe_minima.auxiliares_informados', 1);
});

it('sem escala, usa o campo antigo e o PATCH segue aceito', function () {
    $c = cenarioEscala();
    Presenca::factory()->create(['sessao_id' => $c['sessao']->id, 'status' => Presenca::STATUS_VALIDA]);
    $c['sessao']->forceFill(['auxiliares_escalados' => 3])->save();

    $this->getJson("/api/v1/sessions/{$c['sessao']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('equipe_minima.origem', 'informado')
        ->assertJsonPath('equipe_minima.auxiliares_informados', 3);

    $this->patchJson("/api/v1/sessions/{$c['sessao']->id}", ['auxiliares_escalados' => 5], authEscala($c['coord']))
        ->assertStatus(200);

    expect($c['sessao']->refresh()->auxiliares_escalados)->toBe(5);
});

it('escala prevista nao define a equipe', function () {
    $c = cenarioAviso(['status' => Escala::STATUS_PREVISTA]);

    $this->getJson("/api/v1/sessions/{$c['sessao']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('equipe_minima.origem', 'informado')
        ->assertJsonPath('equipe_minima.auxiliares_informados', 7);

    $this->patchJson("/api/v1/sessions/{$c['sessao']->id}", ['auxiliares_escalados' => 2], authEscala($c['coord']))
        ->assertStatus(200);
});

it('a escala nao atrapalha abrir a sessao nem corrigir a sessao', function () {
    Carbon::setTestNow('2026-12-05 23:00:00');
    $c = cenarioAviso();

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/open", [], authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('status', 'aberta');

    $this->patchJson("/api/v1/sessions/{$c['sessao']->id}", ['tema' => 'Mensagem aberta'], authEscala($c['coord']))
        ->assertStatus(200);

    $this->getJson("/api/v1/sessions/{$c['sessao']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('equipe_minima.origem', 'escala')
        ->assertJsonPath('equipe_minima.auxiliares_necessarios', 1);

    Carbon::setTestNow();
});
