<?php

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\EquipeMembro;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

use Database\Seeders\PapelSeeder;

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('tabelas de equipe sobem com as FKs da decisao 1', function () {
    $equipe = Equipe::factory()->create();
    $membro = EquipeMembro::factory()->create(['equipe_id' => $equipe->id]);
    $usuarioDaEquipe = $membro->usuario_id;

    // Apagar a equipe leva os membros junto (cascade).
    $equipe->delete();
    expect(EquipeMembro::query()->whereKey($membro->id)->exists())->toBeFalse();

    // Usuario e de outro modulo: sem FK, apagar nao mexe no quadro.
    $outraEquipe = Equipe::factory()->create();
    $outroMembro = EquipeMembro::factory()->create(['equipe_id' => $outraEquipe->id]);
    Usuario::query()->whereKey($outroMembro->usuario_id)->delete();

    expect(EquipeMembro::query()->whereKey($outroMembro->id)->exists())->toBeTrue()
        ->and($usuarioDaEquipe)->not->toBeEmpty();
});

it('banco recusa escala sem equipe_id', function () {
    $escala = Escala::factory()->create();
    $linha = $escala->only(['id', 'sessao_id', 'ebi_id', 'igreja_id', 'estimativa_participantes', 'participantes_por_auxiliar', 'status', 'criado_por']);

    expect(fn () => DB::table('escala')->insert([
        ...$linha,
        'id' => (string) str()->ulid(),
        'sessao_id' => \App\Domain\Sessoes\Models\Sessao::factory()->create(['ebi_id' => $escala->ebi_id, 'igreja_id' => $escala->igreja_id])->id,
    ]))->toThrow(QueryException::class);
});

it('banco recusa equipe duplicada para a mesma coordenadora', function () {
    $equipe = Equipe::factory()->create();

    expect(fn () => Equipe::query()->create([
        'ebi_id' => $equipe->ebi_id,
        'igreja_id' => $equipe->igreja_id,
        'coordenadora_id' => $equipe->coordenadora_id,
        'criado_por' => $equipe->coordenadora_id,
    ]))->toThrow(QueryException::class);
});

it('equipe com escala nao pode ser apagada', function () {
    $escala = Escala::factory()->create();

    expect(fn () => Equipe::query()->whereKey($escala->equipe_id)->delete())
        ->toThrow(QueryException::class);
});

it('EscalaFactory cria equipe e escala com equipe_id', function () {
    $escala = Escala::factory()->create();

    expect($escala->equipe_id)->not->toBeEmpty()
        ->and(Equipe::query()->whereKey($escala->equipe_id)->exists())->toBeTrue();
});

it('criar escala sem equipe responde 422 equipe_obrigatoria', function () {
    $c = cenarioEscala();

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'estimativa_participantes' => 5,
        'membros' => [['usuario_id' => $c['coord']->id, 'funcao' => 'coordenadora']],
    ], authEscala($c['coord']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'equipe_obrigatoria');
});

it('criar escala com equipe grava equipe_id', function () {
    $c = cenarioEscala();

    $this->postJson("/api/v1/sessions/{$c['sessao']->id}/schedules", [
        'equipe_id' => $c['equipe']->id,
        'estimativa_participantes' => 5,
        'membros' => [['usuario_id' => $c['coord']->id, 'funcao' => 'coordenadora']],
    ], authEscala($c['coord']))
        ->assertStatus(201)
        ->assertJsonPath('escala.equipe_id', $c['equipe']->id);

    expect(Escala::query()->value('equipe_id'))->toBe($c['equipe']->id);
});
