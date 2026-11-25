<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Models\Equipe;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use Database\Seeders\PapelSeeder;

/**
 * Usuario com papel e escopo escolhidos e vigencia de hoje (`$periodo`
 * sobrescreve `inicio`/`fim`).
 */
function novoMembroEquipe(string $papel, string $tipo, string $escopoId, array $periodo = []): Usuario
{
    return membroEscalaComPeriodo($papel, $tipo, $escopoId, $periodo);
}

/** `membroEscala` com periodo de vigencia controlado pelo teste. */
function membroEscalaComPeriodo(string $papel, string $tipo, string $escopoId, array $periodo = []): Usuario
{
    $u = Usuario::factory()->create();
    VinculoAcesso::query()->create([
        'usuario_id' => $u->id,
        'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => $tipo,
        'escopo_id' => $escopoId,
        'inicio' => $periodo['inicio'] ?? now()->subMonth()->toDateString(),
        'fim' => $periodo['fim'] ?? null,
        'status' => 'ativo',
    ]);

    return $u;
}

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('coordenadora cria a propria equipe', function () {
    $c = cenarioEscala();
    $nova = novoMembroEquipe('coordenadora', 'ebi', $c['ebi']->id);

    $resposta = $this->postJson("/api/v1/ebis/{$c['ebi']->id}/equipes", [
        'coordenadora_id' => $nova->id,
    ], authEscala($nova));

    $resposta->assertStatus(201)
        ->assertJsonPath('data.coordenadora_id', $nova->id)
        ->assertJsonPath('data.coordenadora_nome', $nova->nome)
        ->assertJsonPath('data.coordenadora_sem_vinculo', false)
        ->assertJsonPath('data.membros', []);

    $evento = EventoAuditoria::query()->where('acao', 'equipe.criada')->firstOrFail();
    expect($evento->metadados_minimizados['equipe_id'])->toBe($resposta->json('data.id'));
});

it('segunda equipe da mesma coordenadora responde 409', function () {
    $c = cenarioEscala();
    $nova = novoMembroEquipe('coordenadora', 'ebi', $c['ebi']->id);

    $this->postJson("/api/v1/ebis/{$c['ebi']->id}/equipes", ['coordenadora_id' => $nova->id], authEscala($nova))
        ->assertStatus(201);
    $this->postJson("/api/v1/ebis/{$c['ebi']->id}/equipes", ['coordenadora_id' => $nova->id], authEscala($nova))
        ->assertStatus(409)
        ->assertJsonPath('code', 'equipe_existente');
});

it('coordenadora nao cria equipe para outra', function () {
    $c = cenarioEscala();
    $nova = novoMembroEquipe('coordenadora', 'ebi', $c['ebi']->id);

    $this->postJson("/api/v1/ebis/{$c['ebi']->id}/equipes", ['coordenadora_id' => $nova->id], authEscala($c['coord']))
        ->assertStatus(403)
        ->assertJsonPath('code', 'nao_e_dona_da_equipe');
});

it('chefe e adm_igreja criam para qualquer coordenadora vigente', function (string $papel) {
    $c = cenarioEscala();
    $nova = novoMembroEquipe('coordenadora', 'ebi', $c['ebi']->id);
    $gestor = $papel === 'adm_igreja'
        ? novoMembroEquipe('adm_igreja', 'igreja', $c['igreja']->id)
        : $c['chefe'];

    $this->postJson("/api/v1/ebis/{$c['ebi']->id}/equipes", ['coordenadora_id' => $nova->id], authEscala($gestor))
        ->assertStatus(201)
        ->assertJsonPath('data.coordenadora_id', $nova->id);
})->with(['coordenadora_chefe', 'adm_igreja']);

it('coordenadora alvo sem vinculo vigente responde 422', function () {
    $c = cenarioEscala();
    $ontem = now()->subDay()->toDateString();
    $nova = novoMembroEquipe('coordenadora', 'ebi', $c['ebi']->id, ['inicio' => now()->subYear()->toDateString(), 'fim' => $ontem]);

    $this->postJson("/api/v1/ebis/{$c['ebi']->id}/equipes", ['coordenadora_id' => $nova->id], authEscala($c['chefe']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'coordenadora_invalida');
});

it('coordenadora com vinculo de igreja cria a propria equipe', function () {
    $c = cenarioEscala();
    $nova = novoMembroEquipe('coordenadora', 'igreja', $c['igreja']->id);

    $this->postJson("/api/v1/ebis/{$c['ebi']->id}/equipes", ['coordenadora_id' => $nova->id], authEscala($nova))
        ->assertStatus(201);
});

it('coordenadora inclui e retira na propria equipe', function () {
    $c = cenarioEscala();
    $equipe = $c['equipe'];

    $this->postJson("/api/v1/equipes/{$equipe->id}/members", ['usuario_id' => $c['auxi']->id], authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.membros')
        ->assertJsonPath('data.membros.0.usuario_id', $c['auxi']->id)
        ->assertJsonPath('data.membros.0.sem_vinculo', false);

    $this->deleteJson("/api/v1/equipes/{$equipe->id}/members/{$c['auxi']->id}", [], authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonCount(0, 'data.membros');

    expect(EventoAuditoria::query()->where('acao', 'equipe.membro_incluido')->exists())->toBeTrue()
        ->and(EventoAuditoria::query()->where('acao', 'equipe.membro_retirado')->exists())->toBeTrue();
});

it('coordenadora nao mexe na equipe alheia', function () {
    $c = cenarioEscala();
    $nova = novoMembroEquipe('coordenadora', 'ebi', $c['ebi']->id);

    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $c['auxi']->id], authEscala($nova))
        ->assertStatus(403)
        ->assertJsonPath('code', 'nao_e_dona_da_equipe');
});

it('pessoa sem vinculo de equipe vigente hoje nao entra', function () {
    $c = cenarioEscala();

    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $c['ministro']->id], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_inelegivel');
});

it('vinculo com inicio futuro nao entra na equipe', function () {
    $c = cenarioEscala();
    $futuro = novoMembroEquipe('auxiliar', 'ebi', $c['ebi']->id, ['inicio' => now()->addWeek()->toDateString()]);

    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $futuro->id], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membro_inelegivel');
});

it('incluir de novo responde 409', function () {
    $c = cenarioEscala();
    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $c['auxi']->id], authEscala($c['coord']))
        ->assertStatus(200);

    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $c['auxi']->id], authEscala($c['coord']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'membro_ja_na_equipe');
});

it('retirar quem nao e membro responde 404', function () {
    $c = cenarioEscala();

    $this->deleteJson("/api/v1/equipes/{$c['equipe']->id}/members/{$c['auxi']->id}", [], authEscala($c['coord']))
        ->assertStatus(404);
});

it('revogada aparece sem_vinculo', function () {
    $c = cenarioEscala();
    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $c['auxi']->id], authEscala($c['coord']))
        ->assertStatus(200);

    VinculoAcesso::query()->where('usuario_id', $c['auxi']->id)->update(['fim' => now()->subDay()->toDateString()]);

    $this->getJson("/api/v1/equipes/{$c['equipe']->id}", authEscala($c['coord']))
        ->assertStatus(200)
        ->assertJsonPath('data.membros.0.sem_vinculo', true);
});

it('a mesma auxiliar fica em duas equipes', function () {
    $c = cenarioEscala();
    $outra = novoMembroEquipe('coordenadora', 'ebi', $c['ebi']->id);
    $auxiliar = novoMembroEquipe('auxiliar', 'ebi', $c['ebi']->id);

    // A segunda equipe tem sua propria dona; a chefe nao monta quadro alheio.
    $segunda = Equipe::query()->create([
        'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
        'coordenadora_id' => $outra->id, 'criado_por' => $outra->id,
    ])->id;

    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $auxiliar->id], authEscala($c['coord']))
        ->assertStatus(200);

    // O segundo request usa o mesmo cliente HTTP, cujo usuario autenticado
    // continua o anterior; o resetedor limpa para o novo Bearer valer.
    $this->app['auth']->forgetGuards();

    $this->postJson("/api/v1/equipes/{$segunda}/members", ['usuario_id' => $auxiliar->id], authEscala($outra))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.membros')
        ->assertJsonPath('data.id', $segunda);
});

it('chefe e adm_igreja nao mudam os membros da equipe', function (string $papel) {
    $c = cenarioEscala();
    $gestor = $papel === 'adm_igreja'
        ? novoMembroEquipe('adm_igreja', 'igreja', $c['igreja']->id)
        : $c['chefe'];

    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $c['auxi']->id], authEscala($gestor))
        ->assertStatus(403)
        ->assertJsonPath('code', 'nao_e_dona_da_equipe');
})->with(['coordenadora_chefe', 'adm_igreja']);

it('equipe inexistente responde 404', function () {
    $c = cenarioEscala();

    $this->getJson('/api/v1/equipes/01JZZZZZZZZZZZZZZZZZZZZZZZ', authEscala($c['coord']))
        ->assertStatus(404);
});

it('leem equipes no territorio', function (string $papel) {
    $c = cenarioEscala();
    $leitor = match ($papel) {
        'chefe' => $c['chefe'],
        'coord' => $c['coord'],
        'adm_igreja' => novoMembroEquipe('adm_igreja', 'igreja', $c['igreja']->id),
        default => $c['ministro'],
    };

    $this->getJson("/api/v1/ebis/{$c['ebi']->id}/equipes", authEscala($leitor))
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $c['equipe']->id)
        ->assertJsonPath('data.0.quantidade_membros', 0);
})->with(['ministerio_local', 'adm_igreja', 'chefe', 'coord']);

it('fora do territorio responde 404', function () {
    $c = cenarioEscala();
    $outro = cenarioEscala();

    $this->getJson("/api/v1/ebis/{$c['ebi']->id}/equipes", authEscala($outro['coord']))
        ->assertStatus(404);
});

it('sem escala.ler recebe 403 na leitura', function (string $papel) {
    $c = cenarioEscala();
    $leitor = $papel === 'auxiliar' ? $c['auxi'] : novoMembroEquipe($papel, $papel === 'ministerio_central' ? 'central' : 'ebi', $c['ebi']->id);

    $this->getJson("/api/v1/ebis/{$c['ebi']->id}/equipes", authEscala($leitor))
        ->assertStatus(403);
})->with(['auxiliar', 'ministerio_central']);

it('ministerio_local nao escreve', function () {
    $c = cenarioEscala();

    $this->postJson("/api/v1/ebis/{$c['ebi']->id}/equipes", ['coordenadora_id' => $c['coord']->id], authEscala($c['ministro']))
        ->assertStatus(403)
        ->assertJsonPath('code', 'sem_permissao');
});

it('auditoria da equipe sai sem nomes', function () {
    $c = cenarioEscala();
    $this->postJson("/api/v1/equipes/{$c['equipe']->id}/members", ['usuario_id' => $c['auxi']->id], authEscala($c['coord']))
        ->assertStatus(200);

    $evento = EventoAuditoria::query()->where('acao', 'equipe.membro_incluido')->firstOrFail();

    expect($evento->metadados_minimizados)
        ->not->toHaveKey('nome')
        ->and($evento->metadados_minimizados)
        ->toHaveKeys(['equipe_id', 'ebi_id', 'coordenadora_id', 'usuario_id']);
});
