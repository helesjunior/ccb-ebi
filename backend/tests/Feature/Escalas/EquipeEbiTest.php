<?php

use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use Database\Seeders\PapelSeeder;

/**
 * Cria um usuario com papel e escopo explicitos (permite vigencias encerradas).
 */
function membroDaEquipe(string $papel, string $tipo, string $escopoId, ?string $fim = null): Usuario
{
    $usuario = Usuario::factory()->create(['nome' => 'U '.uniqid()]);
    VinculoAcesso::query()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => $tipo,
        'escopo_id' => $escopoId,
        'inicio' => now()->subMonths(2)->toDateString(),
        'fim' => $fim,
        'status' => $fim === null ? 'ativo' : 'encerrado',
    ]);

    return $usuario;
}

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->equipe = app(ConsultarEquipeEbi::class);

    $this->igreja = Igreja::factory()->create();
    $this->ebi = Ebi::factory()->create(['igreja_id' => $this->igreja->id]);
    $this->outroEbi = Ebi::factory()->create(['igreja_id' => Igreja::factory()->create()->id]);
});

it('equipe junta auxiliar do EBI, coordenadora da igreja e chefe, e exclui o resto', function () {
    $auxiliar = membroDaEquipe('auxiliar', 'ebi', $this->ebi->id);
    $coordenadora = membroDaEquipe('coordenadora', 'igreja', $this->igreja->id);
    $chefe = membroDaEquipe('coordenadora_chefe', 'ebi', $this->ebi->id);
    membroDaEquipe('auxiliar', 'ebi', $this->outroEbi->id);
    membroDaEquipe('auxiliar', 'ebi', $this->ebi->id, now()->subWeek()->toDateString());
    membroDaEquipe('ministerio_local', 'ebi', $this->ebi->id);

    $equipe = collect($this->equipe->equipe($this->ebi->id, $this->igreja->id));

    expect($equipe->pluck('usuario_id')->sort()->values()->all())
        ->toBe(collect([$auxiliar->id, $coordenadora->id, $chefe->id])->sort()->values()->all());
});

it('equipe agrupa os papeis do mesmo usuario e ordena por nome', function () {
    $ambos = membroDaEquipe('coordenadora', 'igreja', $this->igreja->id);
    membroDaEquipe('auxiliar', 'ebi', $this->ebi->id);
    VinculoAcesso::query()->create([
        'usuario_id' => $ambos->id,
        'papel_id' => Papel::query()->where('codigo', 'auxiliar')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $this->ebi->id,
        'inicio' => now()->toDateString(),
        'fim' => null,
        'status' => 'ativo',
    ]);

    $equipe = $this->equipe->equipe($this->ebi->id, $this->igreja->id);

    expect(collect(collect($equipe)->firstWhere('usuario_id', $ambos->id)['papeis'])->sort()->values()->all())
        ->toBe(['auxiliar', 'coordenadora'])
        ->and(array_column($equipe, 'nome'))->toBe(collect($equipe)->pluck('nome')->sort()->values()->all());
});

it('vigencias trazem os periodos encerrados e chefeVigente a vaga ocupada', function () {
    $chefe = membroDaEquipe('coordenadora_chefe', 'ebi', $this->ebi->id);
    $antiga = membroDaEquipe('coordenadora', 'igreja', $this->igreja->id, now()->subMonth()->toDateString());

    expect($this->equipe->chefeVigente($this->ebi->id))->toBe($chefe->id)
        ->and($this->equipe->chefeVigente($this->outroEbi->id))->toBeNull();

    $vigencias = $this->equipe->vigencias([$antiga->id, $chefe->id], $this->ebi->id, $this->igreja->id);

    expect($vigencias[$antiga->id][0]['papel'])->toBe('coordenadora')
        ->and($vigencias[$antiga->id][0]['fim'])->not->toBeNull()
        ->and($vigencias[$chefe->id][0]['papel'])->toBe('coordenadora_chefe')
        ->and($vigencias[$chefe->id][0]['fim'])->toBeNull();
});

it('nomes devolve o nome de cada usuario pedido', function () {
    $a = Usuario::factory()->create(['nome' => 'Ana']);
    $b = Usuario::factory()->create(['nome' => 'Bia']);

    expect($this->equipe->nomes([$a->id, $b->id, 'inexistente']))->toBe([
        $a->id => 'Ana',
        $b->id => 'Bia',
    ]);
});

it('a rota de equipe devolve os membros a quem gere a escala', function () {
    $coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);

    $this->getJson("/api/v1/ebis/{$this->ebi->id}/team", [
        'Authorization' => 'Bearer '.$coord->createToken('t')->plainTextToken,
    ])->assertStatus(200)->assertJsonStructure(['data' => [['usuario_id', 'nome', 'papeis']]]);
});

it('a rota de equipe recusa quem so le a escala', function () {
    // Um usuario por teste: o harness reaproveita o usuario autenticado da
    // primeira requisicao do teste (o guard fica cacheado no app de teste).
    $auxiliar = membroDaEquipe('auxiliar', 'ebi', $this->ebi->id);

    $this->getJson("/api/v1/ebis/{$this->ebi->id}/team", [
        'Authorization' => 'Bearer '.$auxiliar->createToken('t')->plainTextToken,
    ])->assertStatus(403);
});

it('a rota de equipe esconde o EBI fora do escopo', function () {
    $coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);

    $this->getJson("/api/v1/ebis/{$this->outroEbi->id}/team", [
        'Authorization' => 'Bearer '.$coord->createToken('t')->plainTextToken,
    ])->assertStatus(404)->assertJsonPath('code', 'not_found');
});
