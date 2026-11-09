<?php

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->autorizar = app(AutorizarOperacao::class);
});

function ctx(string $papel, string $tipo, string $id): ContextoAtuacao
{
    return new ContextoAtuacao(papelCodigo: $papel, tipoEscopo: $tipo, escopoId: $id);
}

it('adm_setor gere organizacao dentro do proprio escopo', function () {
    $h = Mockery::mock(ConsultarHierarquia::class);
    $h->shouldReceive('ancestrais')->andReturn(['igreja' => 'ig1', 'setor' => 'set1', 'regional' => 'reg1', 'central' => 'c1']);
    app()->instance(ConsultarHierarquia::class, $h);

    $autz = app(AutorizarOperacao::class);
    // G(setor): gere o proprio setor e descendentes; nao ve setor irmao (horizontal).
    expect($autz->permite(ctx('adm_setor', 'setor', 'set1'), Operacoes::ORGANIZACAO_GERENCIAR, 'igreja', 'ig1'))->toBeTrue()
        ->and($autz->permite(ctx('adm_setor', 'setor', 'set2'), Operacoes::ORGANIZACAO_GERENCIAR, 'igreja', 'ig1'))->toBeFalse();
});

it('coordenadora le mas nao gere organizacao', function () {
    // Matriz: Estrutura organizacional => Coordenadora = L.
    expect($this->autorizar->permite(ctx('coordenadora', 'igreja', 'ig1'), Operacoes::ORGANIZACAO_LER))->toBeTrue()
        ->and($this->autorizar->permite(ctx('coordenadora', 'igreja', 'ig1'), Operacoes::ORGANIZACAO_GERENCIAR))->toBeFalse();
});

it('responsavel nao gere nem le organizacao', function () {
    // Matriz: Responsavel => — na Estrutura organizacional.
    expect($this->autorizar->permite(ctx('responsavel', 'ebi', 'e1'), Operacoes::ORGANIZACAO_GERENCIAR))->toBeFalse()
        ->and($this->autorizar->permite(ctx('responsavel', 'ebi', 'e1'), Operacoes::ORGANIZACAO_LER))->toBeFalse();
});

it('super_admin ignora escopo', function () {
    expect($this->autorizar->permite(ctx('super_admin', 'central', 'cX'), Operacoes::ORGANIZACAO_GERENCIAR, 'igreja', 'ig1'))->toBeTrue();
});
