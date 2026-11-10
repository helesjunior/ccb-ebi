<?php

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

function auditoriaUsuarioCom(string $papel, string $tipo, string $escopoId): Usuario
{
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create([
        'usuario_id' => $u->id, 'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => $tipo, 'escopo_id' => $escopoId,
    ]);

    return $u;
}

/**
 * @return array<string, string>
 */
function auditoriaAuth(Usuario $usuario, string $papel, string $tipo, string $escopoId): array
{
    return [
        'Authorization' => 'Bearer '.$usuario->createToken('t', ['*'])->plainTextToken,
        'X-Acting-Scope' => "papel={$papel};escopo={$tipo}:{$escopoId}",
    ];
}

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->igreja = Igreja::factory()->create();
});

it('lista eventos para super_admin', function () {
    app(RegistrarEvento::class)->registrar(['acao' => 'x', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => 'igreja:'.$this->igreja->id]);
    $central = CentralNacional::factory()->create();
    $admin = auditoriaUsuarioCom('super_admin', 'central', $central->id);

    $this->getJson('/api/v1/audit-events', auditoriaAuth($admin, 'super_admin', 'central', $central->id))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['proximo_cursor']])
        ->assertJsonCount(1, 'data');
});

it('nao mostra a papel territorial evento sem escopo territorial', function () {
    app(RegistrarEvento::class)->registrar(['acao' => 'x', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => null]);
    $regional = Regional::factory()->create();
    $adm = auditoriaUsuarioCom('adm_regional', 'regional', $regional->id);

    $this->getJson('/api/v1/audit-events', auditoriaAuth($adm, 'adm_regional', 'regional', $regional->id))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('nega a papel sem permissao', function () {
    $u = auditoriaUsuarioCom('auxiliar', 'igreja', $this->igreja->id);

    $this->getJson('/api/v1/audit-events', auditoriaAuth($u, 'auxiliar', 'igreja', $this->igreja->id))
        ->assertStatus(403);
});

it('nao concede auditoria a adm_igreja', function () {
    $u = auditoriaUsuarioCom('adm_igreja', 'igreja', $this->igreja->id);

    $this->getJson('/api/v1/audit-events', auditoriaAuth($u, 'adm_igreja', 'igreja', $this->igreja->id))
        ->assertStatus(403);
});

it('papel territorial ve apenas eventos da propria subarvore', function () {
    $regional = Regional::factory()->create();
    $outra = Regional::factory()->create();
    app(RegistrarEvento::class)->registrar(['acao' => 'dentro', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => 'regional:'.$regional->id]);
    app(RegistrarEvento::class)->registrar(['acao' => 'fora', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => 'regional:'.$outra->id]);
    app(RegistrarEvento::class)->registrar(['acao' => 'sem-escopo', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => null]);
    $adm = auditoriaUsuarioCom('adm_regional', 'regional', $regional->id);

    $this->getJson('/api/v1/audit-events', auditoriaAuth($adm, 'adm_regional', 'regional', $regional->id))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.acao', 'dentro');
});

it('papel territorial ve eventos da subarvore descendente (setor -> igreja/ebi)', function () {
    $regional = Regional::factory()->create();
    $setor = Setor::factory()->create(['regional_id' => $regional->id]);
    $igreja = Igreja::factory()->create();
    $igreja->vinculacoes()->create(['setor_id' => $setor->id, 'inicio' => now()->toDateString(), 'fim' => null]);
    $ebi = Ebi::factory()->create(['igreja_id' => $igreja->id]);

    app(RegistrarEvento::class)->registrar(['acao' => 'igreja', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => 'igreja:'.$igreja->id]);
    app(RegistrarEvento::class)->registrar(['acao' => 'ebi', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => 'ebi:'.$ebi->id]);
    // Outra igreja, fora do setor: nao desce do vinculo.
    $fora = Igreja::factory()->create();
    app(RegistrarEvento::class)->registrar(['acao' => 'fora', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => 'igreja:'.$fora->id]);

    $adm = auditoriaUsuarioCom('adm_setor', 'setor', $setor->id);

    $this->getJson('/api/v1/audit-events', auditoriaAuth($adm, 'adm_setor', 'setor', $setor->id))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.acao', 'igreja')
        ->assertJsonPath('data.1.acao', 'ebi');
});

it('trata escopo de tipo desconhecido como nao coberto', function () {
    // Tipo fora da hierarquia: `ResolverEscopo` lanca RuntimeException e o
    // evento e negado por seguranca (nao aparece para papel territorial).
    app(RegistrarEvento::class)->registrar(['acao' => 'desconhecido', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => 'departamento:qualquer']);
    $regional = Regional::factory()->create();
    $adm = auditoriaUsuarioCom('adm_regional', 'regional', $regional->id);

    $this->getJson('/api/v1/audit-events', auditoriaAuth($adm, 'adm_regional', 'regional', $regional->id))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('filtra por recurso, ator e intervalo de ocorrencia', function () {
    $central = CentralNacional::factory()->create();
    $admin = auditoriaUsuarioCom('super_admin', 'central', $central->id);

    $registrar = app(RegistrarEvento::class);
    $registrar->registrar(['acao' => 'a', 'recurso' => 'usuario', 'ator_id' => 'ator-1', 'origem' => 'api', 'ocorrido_em' => now()->subDays(2)]);
    $registrar->registrar(['acao' => 'b', 'recurso' => 'usuario', 'ator_id' => 'ator-1', 'origem' => 'api', 'ocorrido_em' => now()]);
    $registrar->registrar(['acao' => 'c', 'recurso' => 'igreja', 'ator_id' => 'ator-2', 'origem' => 'api', 'ocorrido_em' => now()]);

    $auth = auditoriaAuth($admin, 'super_admin', 'central', $central->id);

    $this->getJson('/api/v1/audit-events?recurso=usuario&ator_id=ator-1&de='.now()->subDay()->toDateString(), $auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.acao', 'b');
});
