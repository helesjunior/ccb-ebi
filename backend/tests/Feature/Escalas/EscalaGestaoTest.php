<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
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
 * Cenario E07: igreja com EBI, coordenadora (gere a escala), auxiliar,
 * ministro local e uma sessao prevista. Um ator por requisicao em cada teste:
 * o harness reaproveita o usuario autenticado entre chamadas.
 *
 * @return array<string, mixed>
 */
function cenarioEscala(array $sessao = []): array
{
    $igreja = Igreja::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $ebi = Ebi::factory()->create(['igreja_id' => $igreja->id, 'participantes_por_auxiliar' => 4]);

    $sessaoModel = Sessao::factory()->create($sessao + [
        'ebi_id' => $ebi->id, 'igreja_id' => $igreja->id,
        'inicio_previsto' => '2026-12-05 23:00:00', 'fim_previsto' => '2026-12-06 01:00:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);

    return [
        'igreja' => $igreja,
        'ebi' => $ebi,
        'sessao' => $sessaoModel,
        'coord' => membroEscala('coordenadora', 'ebi', $ebi->id),
        'auxi' => membroEscala('auxiliar', 'ebi', $ebi->id),
        'chefe' => membroEscala('coordenadora_chefe', 'ebi', $ebi->id),
        'ministro' => membroEscala('ministerio_local', 'ebi', $ebi->id),
    ];
}

/** Usuario com papel e escopo explicitos. */
function membroEscala(string $papel, string $tipo, string $escopoId): Usuario
{
    $u = Usuario::factory()->create();
    VinculoAcesso::query()->create([
        'usuario_id' => $u->id,
        'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => $tipo,
        'escopo_id' => $escopoId,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
        'status' => 'ativo',
    ]);

    return $u;
}

/** Cabecalho de autenticacao de um usuario. */
function authEscala(Usuario $u): array
{
    return ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
}

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('cria a escala da sessao com a equipe minima calculada', function () {
    $c = cenarioEscala();

    $resposta = $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'estimativa_participantes' => 5,
        'membros' => [
            ['usuario_id' => $c['coord']->id, 'funcao' => 'coordenadora'],
            ['usuario_id' => $c['auxi']->id, 'funcao' => 'auxiliar'],
        ],
    ], authEscala($c['coord']));

    $resposta->assertStatus(201)
        ->assertJsonPath('escala.status', 'prevista')
        ->assertJsonPath('escala.estimativa_participantes', 5)
        ->assertJsonPath('escala.participantes_por_auxiliar', 4)
        ->assertJsonPath('escala.equipe_minima.auxiliares_necessarios', 2)
        ->assertJsonPath('escala.equipe_minima.auxiliares_escalados', 1)
        ->assertJsonPath('escala.situacao', 'abaixo_do_minimo');

    $escala = Escala::query()->firstOrFail();
    expect($escala->ebi_id)->toBe($c['ebi']->id)
        ->and($escala->igreja_id)->toBe($c['igreja']->id)
        ->and($escala->membros()->count())->toBe(2);
});

it('nao cria uma segunda escala para a mesma sessao', function () {
    $c = cenarioEscala();
    Escala::factory()->create(['sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id]);

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'estimativa_participantes' => 5,
    ], authEscala($c['coord']))->assertStatus(409)->assertJsonPath('code', 'escala_existente');
});

it('nao cria escala de sessao cancelada', function () {
    $c = cenarioEscala(['status' => Sessao::STATUS_CANCELADA]);

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'estimativa_participantes' => 5,
    ], authEscala($c['coord']))->assertStatus(409)->assertJsonPath('code', 'sessao_em_estado_invalido');
});

it('o mesmo usuario nao entra duas vezes, em nenhuma funcao', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
    ]);
    EscalaMembro::factory()->for($escala)->create(['usuario_id' => $c['auxi']->id]);

    $this->postJson("/api/v1/schedules/{$escala->id}/members", [
        'usuario_id' => $c['auxi']->id, 'funcao' => 'coordenadora',
    ], authEscala($c['coord']))->assertStatus(422)->assertJsonPath('code', 'membro_ja_escalado');
});

it('reincluir quem foi substituido abre uma linha nova', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
    ]);
    $antigo = EscalaMembro::factory()->for($escala)->create([
        'usuario_id' => $c['auxi']->id, 'status' => EscalaMembro::STATUS_SUBSTITUIDO,
    ]);

    $this->postJson("/api/v1/schedules/{$escala->id}/members", [
        'usuario_id' => $c['auxi']->id, 'funcao' => 'auxiliar',
    ], authEscala($c['coord']))->assertStatus(200);

    expect($escala->membros()->where('usuario_id', $c['auxi']->id)->count())->toBe(2)
        ->and($escala->membros()->where('usuario_id', $c['auxi']->id)->where('id', '<>', $antigo->id)->first()->status)
        ->toBe(EscalaMembro::STATUS_ESCALADO);
});

it('recusa membro fora da equipe ou com papel incompativel com a funcao', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
    ]);

    // Auxiliar nao pode coordenar.
    $this->postJson("/api/v1/schedules/{$escala->id}/members", [
        'usuario_id' => $c['auxi']->id, 'funcao' => 'coordenadora',
    ], authEscala($c['coord']))->assertStatus(422)->assertJsonPath('code', 'membro_inelegivel');
});

it('recusa ministro local na escala', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
    ]);

    $this->postJson("/api/v1/schedules/{$escala->id}/members", [
        'usuario_id' => $c['ministro']->id, 'funcao' => 'auxiliar',
    ], authEscala($c['coord']))->assertStatus(422)->assertJsonPath('code', 'membro_inelegivel');
});

it('alterar escala aprovada volta para prevista e cancela as trocas pendentes', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
        'status' => Escala::STATUS_APROVADA, 'aprovado_por' => $c['chefe']->id, 'aprovado_em' => now(),
    ]);
    $membro = EscalaMembro::factory()->for($escala)->create([
        'usuario_id' => $c['auxi']->id, 'confirmado_em' => now(), 'status' => EscalaMembro::STATUS_TROCA_SOLICITADA,
    ]);
    $troca = SolicitacaoTrocaEscala::factory()->create(['escala_membro_id' => $membro->id]);

    $this->patchJson("/api/v1/schedules/{$escala->id}", [
        'estimativa_participantes' => 8,
    ], authEscala($c['coord']))->assertStatus(200);

    $escala->refresh();
    $membro->refresh();
    $troca->refresh();

    expect($escala->status)->toBe(Escala::STATUS_PREVISTA)
        ->and($escala->aprovado_por)->toBeNull()
        ->and($escala->aprovado_em)->toBeNull()
        ->and($membro->confirmado_em)->not->toBeNull()
        ->and($membro->status)->toBe(EscalaMembro::STATUS_CONFIRMADO)
        ->and($troca->status)->toBe(SolicitacaoTrocaEscala::STATUS_CANCELADA)
        ->and($troca->motivo_decisao)->toBe('escala alterada')
        ->and($troca->decidido_em)->not->toBeNull();

    $acoes = EventoAuditoria::query()->pluck('acao')->all();
    expect($acoes)->toContain('escala.alterada')
        ->and($acoes)->toContain('escala.troca_cancelada');
});

it('alterar a escala copia o divisor atual do EBI', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
        'participantes_por_auxiliar' => 4,
    ]);
    $c['ebi']->forceFill(['participantes_por_auxiliar' => 6])->save();

    $this->patchJson("/api/v1/schedules/{$escala->id}", [
        'estimativa_participantes' => 12,
    ], authEscala($c['coord']))->assertStatus(200)->assertJsonPath('escala.participantes_por_auxiliar', 6);

    expect($escala->refresh()->participantes_por_auxiliar)->toBe(6);
});

it('retirar e mudar funcao devolvem a escala com os membros atualizados', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
        'status' => Escala::STATUS_APROVADA, 'aprovado_por' => $c['chefe']->id, 'aprovado_em' => now(),
    ]);
    $membro = EscalaMembro::factory()->for($escala)->create(['usuario_id' => $c['auxi']->id]);

    // O auxiliar nao pode coordenar: a funcao e recusada e a escala volta a
    // prevista. Ocorre na pratica quando o vinculo da pessoa muda depois de ela
    // ja estar escalada.
    $this->patchJson("/api/v1/schedule-members/{$membro->id}", [
        'funcao' => 'coordenadora',
    ], authEscala($c['coord']))->assertStatus(422)->assertJsonPath('code', 'membro_inelegivel');

    // O auxiliar continua auxiliar e a escala nao muda (alteracao recusada).
    expect($membro->refresh()->funcao)->toBe(EscalaMembro::FUNCAO_AUXILIAR)
        ->and($escala->refresh()->status)->toBe(Escala::STATUS_APROVADA);
});

it('mudar funcao de quem pode coordenar e aceito', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
    ]);
    $membro = EscalaMembro::factory()->for($escala)->create([
        'usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
    ]);

    $this->patchJson("/api/v1/schedule-members/{$membro->id}", [
        'funcao' => 'coordenadora',
    ], authEscala($c['coord']))->assertStatus(200);

    expect($membro->refresh()->funcao)->toBe(EscalaMembro::FUNCAO_COORDENADORA);
});

it('auxiliar nao gerencia a escala', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
    ]);

    $this->patchJson("/api/v1/schedules/{$escala->id}", [
        'estimativa_participantes' => 10,
    ], authEscala($c['auxi']))->assertStatus(403);
});

it('coordenadora de outro EBI nao alcanca a sessao', function () {
    $c = cenarioEscala();
    $outraEbi = Ebi::factory()->create(['igreja_id' => Igreja::factory()->create()->id]);

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'estimativa_participantes' => 5,
    ], authEscala(membroEscala('coordenadora', 'ebi', $outraEbi->id)))->assertStatus(404);
});

it('ministro regional nao lista escalas do EBI', function () {
    $c = cenarioEscala();
    $regional = Regional::factory()->create();
    $setor = Setor::factory()->create(['regional_id' => $regional->id]);
    IgrejaVinculacao::query()->create(['igreja_id' => $c['igreja']->id, 'setor_id' => $setor->id, 'inicio' => now()->subYear()->toDateString(), 'fim' => null]);

    $this->getJson("/api/v1/ebis/{$c['ebi']->id}/schedules?inicio=2026-12-01&fim=2026-12-31", [
        'Authorization' => 'Bearer '.membroEscala('ministerio_regional', 'regional', $regional->id)->createToken('t')->plainTextToken,
    ])->assertStatus(403);
});

it('adm_setor que cobre o EBI lista as escalas', function () {
    $c = cenarioEscala();
    $regional = Regional::factory()->create();
    $setor = Setor::factory()->create(['regional_id' => $regional->id]);
    IgrejaVinculacao::query()->create(['igreja_id' => $c['igreja']->id, 'setor_id' => $setor->id, 'inicio' => now()->subYear()->toDateString(), 'fim' => null]);

    $this->getJson("/api/v1/ebis/{$c['ebi']->id}/schedules?inicio=2026-12-01&fim=2026-12-31", [
        'Authorization' => 'Bearer '.membroEscala('adm_setor', 'setor', $setor->id)->createToken('t')->plainTextToken,
    ])->assertStatus(200);
});
it('a listagem traz as sessoes do periodo com sem_escala', function () {
    $c = cenarioEscala();

    $this->getJson("/api/v1/ebis/{$c['ebi']->id}/schedules?inicio=2026-12-01&fim=2026-12-31", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('data.0.sessao_id', $c['sessao']->id)
        ->assertJsonPath('data.0.escala_id', null)
        ->assertJsonPath('data.0.situacao', 'sem_escala');
});

it('o detalhe traz os membros com nome e sem vinculo marcado', function () {
    $c = cenarioEscala();
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
    ]);
    EscalaMembro::factory()->for($escala)->create(['usuario_id' => $c['auxi']->id]);
    // Vinculo que termina antes da sessao nao conta.
    VinculoAcesso::query()->where('usuario_id', $c['auxi']->id)
        ->update(['fim' => '2026-11-01', 'status' => 'encerrado']);

    $this->getJson("/api/v1/schedules/{$escala->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('escala.membros.0.usuario_id', $c['auxi']->id)
        ->assertJsonPath('escala.membros.0.nome', $c['auxi']->nome)
        ->assertJsonPath('escala.membros.0.sem_vinculo', true)
        ->assertJsonPath('escala.membros.0.conflitos', []);
});

it('a auditoria da escala nao guarda nomes', function () {
    $c = cenarioEscala();

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'estimativa_participantes' => 5,
        'membros' => [['usuario_id' => $c['coord']->id, 'funcao' => 'coordenadora']],
    ], authEscala($c['coord']))->assertStatus(201);

    $evento = EventoAuditoria::query()->where('acao', 'escala.criada')->firstOrFail();

    expect($evento->metadados_minimizados)->not->toHaveKey('nome');
});
