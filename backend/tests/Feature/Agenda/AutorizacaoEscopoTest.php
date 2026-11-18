<?php

use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Support\Str;

beforeEach(function () {
    ['igreja' => $this->igrejaA, 'ebi' => $this->ebiA] = igrejaComEbi();
    ['igreja' => $this->igrejaB, 'ebi' => $this->ebiB] = igrejaComEbi();

    $this->auth = function (string $papel, string $tipoEscopo, string $escopoId): array {
        $usuario = criarUsuarioComPapel($papel, $tipoEscopo, $escopoId);

        return [
            'Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken,
            'X-Acting-Scope' => "papel={$papel};escopo={$tipoEscopo}:{$escopoId}",
        ];
    };
});

it('nao expoe agenda, sessoes nem excecoes de EBI fora do escopo', function () {
    $auth = ($this->auth)('coordenadora', 'ebi', $this->ebiA->id);
    $outra = $this->ebiB->id;

    $this->getJson("/api/v1/ebis/{$outra}/agenda", $auth)->assertForbidden();
    $this->postJson("/api/v1/ebis/{$outra}/agenda", [
        'dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fim' => '20:30', 'inicio_vigencia' => '2026-01-01',
    ], $auth)->assertForbidden();

    $this->getJson("/api/v1/ebis/{$outra}/agenda-exceptions", $auth)->assertForbidden();
    $this->postJson("/api/v1/ebis/{$outra}/agenda-exceptions", [
        'tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'natal',
    ], $auth)->assertForbidden();

    $this->postJson("/api/v1/ebis/{$outra}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], $auth)->assertForbidden();

    $this->getJson("/api/v1/ebis/{$outra}/sessions?de=2026-11-01&ate=2026-11-30", $auth)->assertForbidden();
});

it('mantem acesso a propria EBI (controle positivo)', function () {
    $auth = ($this->auth)('coordenadora', 'ebi', $this->ebiA->id);
    $propria = $this->ebiA->id;

    $this->getJson("/api/v1/ebis/{$propria}/agenda", $auth)->assertOk();
    $this->getJson("/api/v1/ebis/{$propria}/agenda-exceptions", $auth)->assertOk();
    $this->getJson("/api/v1/ebis/{$propria}/sessions?de=2026-11-01&ate=2026-11-30", $auth)->assertOk();
});

it('nao edita igreja fora do setor do ator', function () {
    $central = CentralNacional::factory()->create();
    $regional = Regional::factory()->create(['central_nacional_id' => $central->id]);
    $setorA = Setor::factory()->create(['regional_id' => $regional->id]);
    $setorB = Setor::factory()->create(['regional_id' => $regional->id]);
    $igrejaA = Igreja::factory()->create();
    $igrejaB = Igreja::factory()->create();

    IgrejaVinculacao::create(['igreja_id' => $igrejaA->id, 'setor_id' => $setorA->id, 'inicio' => '2020-01-01', 'fim' => null]);
    IgrejaVinculacao::create(['igreja_id' => $igrejaB->id, 'setor_id' => $setorB->id, 'inicio' => '2020-01-01', 'fim' => null]);

    $auth = ($this->auth)('adm_setor', 'setor', $setorA->id);

    $this->patchJson("/api/v1/churches/{$igrejaB->id}", ['nome' => 'Alterada'], $auth)->assertForbidden();
    $this->patchJson("/api/v1/churches/{$igrejaA->id}", ['nome' => 'Alterada'], $auth)->assertOk();

    $this->postJson("/api/v1/churches/{$igrejaB->id}/transfers", [
        'sector_id' => $setorA->id, 'date' => now()->toDateString(),
    ], $auth)->assertForbidden();
});

it('nao altera EBI de outro escopo', function () {
    $auth = ($this->auth)('adm_setor', 'setor', (string) Str::ulid());
    $ebi = Ebi::factory()->create(['igreja_id' => $this->igrejaB->id]);

    $this->patchJson("/api/v1/ebis/{$ebi->id}", ['nome' => 'X'], $auth)->assertForbidden();
});
