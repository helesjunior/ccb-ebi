<?php

use App\Domain\Identidade\Services\AutorizadorOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);

    $h = Mockery::mock(ConsultarHierarquia::class);
    $h->shouldReceive('ancestrais')->andReturn([
        'ebi' => 'e1', 'igreja' => 'i1', 'setor' => 's1', 'regional' => 'r1', 'central' => 'c1',
    ]);
    app()->instance(ConsultarHierarquia::class, $h);

    $this->autz = app(AutorizadorOperacao::class);
    $this->ctx = fn (string $papel, string $tipo = 'ebi', string $id = 'e1') => new ContextoAtuacao(papelCodigo: $papel, tipoEscopo: $tipo, escopoId: $id);
});

it('aplica a matriz de agenda, sessão e presença', function () {
    $ctx = $this->ctx;
    $a = $this->autz;

    expect($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::AGENDA_GERENCIAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('coordenadora'), Operacoes::AGENDA_GERENCIAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('auxiliar'), Operacoes::AGENDA_GERENCIAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('super_admin', 'central', 'c1'), Operacoes::AGENDA_GERENCIAR, 'ebi', 'e1'))->toBeFalse()

        ->and($a->permite($ctx('auxiliar'), Operacoes::SESSAO_LER, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('auxiliar'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('adm_regional', 'regional', 'r1'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse()

        ->and($a->permite($ctx('auxiliar'), Operacoes::PRESENCA_REGISTRAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::PRESENCA_REGISTRAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::PRESENCA_ESTORNAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('auxiliar'), Operacoes::PRESENCA_ESTORNAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('ministerio_local', 'igreja', 'i1'), Operacoes::PRESENCA_LER, 'ebi', 'e1'))->toBeTrue();
});

it('nega escopo horizontal em outra EBI', function () {
    $ctx = $this->ctx;

    expect($this->autz->permite($ctx('coordenadora', 'ebi', 'outra'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse();
});
