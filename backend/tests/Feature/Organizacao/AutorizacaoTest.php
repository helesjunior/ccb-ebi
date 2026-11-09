<?php

use App\Domain\Identidade\Models\Ator;
use Illuminate\Support\Facades\Gate;

it('autoriza gestao para o papel administrativo', function () {
    $ator = Ator::factory()->create(['papel' => 'coordenadora']);
    expect(Gate::forUser($ator)->allows('gerir-organizacao'))->toBeTrue();
});

it('nega gestao para papel sem permissao', function () {
    $ator = Ator::factory()->create(['papel' => 'responsavel']);
    expect(Gate::forUser($ator)->allows('gerir-organizacao'))->toBeFalse();
});

it('nega gestao para visitante nao autenticado', function () {
    expect(Gate::allows('gerir-organizacao'))->toBeFalse();
});
