<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Identidade\Services\ResolvedorContextoAtuacao;
use App\Support\ProblemException;
use Database\Seeders\PapelSeeder;

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('usa o unico vinculo quando nao ha header', function () {
    $u = Usuario::factory()->create();
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => Papel::query()->where('codigo', 'coordenadora')->value('id'), 'tipo_escopo' => 'ebi', 'escopo_id' => 'e1']);

    $ctx = app(ResolvedorContextoAtuacao::class)->resolver($u, null);
    expect($ctx->papelCodigo)->toBe('coordenadora')->and($ctx->escopoId)->toBe('e1');
});

it('exige header quando ha mais de um vinculo', function () {
    $u = Usuario::factory()->create();
    $papel = Papel::query()->where('codigo', 'coordenadora')->value('id');
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel, 'tipo_escopo' => 'ebi', 'escopo_id' => 'e1']);
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel, 'tipo_escopo' => 'ebi', 'escopo_id' => 'e2']);

    expect(fn () => app(ResolvedorContextoAtuacao::class)->resolver($u, null))
        ->toThrow(ProblemException::class);
});

it('rejeita escopo que nao pertence ao usuario', function () {
    $u = Usuario::factory()->create();
    $papel = Papel::query()->where('codigo', 'coordenadora')->value('id');
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel, 'tipo_escopo' => 'ebi', 'escopo_id' => 'e1']);

    expect(fn () => app(ResolvedorContextoAtuacao::class)->resolver($u, 'papel=coordenadora;escopo=ebi:e9'))
        ->toThrow(ProblemException::class);
});
