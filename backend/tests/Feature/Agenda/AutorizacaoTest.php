<?php

use App\Domain\Identidade\Services\AutorizadorOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use Database\Seeders\PapelSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
        ->and($a->permite($ctx('super_admin', 'central', 'c1'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('super_admin', 'central', 'c1'), Operacoes::PRESENCA_ESTORNAR, 'ebi', 'e1'))->toBeFalse()

        ->and($a->permite($ctx('auxiliar'), Operacoes::SESSAO_LER, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('auxiliar'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('adm_regional', 'regional', 'r1'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('responsavel'), Operacoes::SESSAO_LER, 'ebi', 'e1'))->toBeFalse()

        ->and($a->permite($ctx('auxiliar'), Operacoes::PRESENCA_REGISTRAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::PRESENCA_REGISTRAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::PRESENCA_ESTORNAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('auxiliar'), Operacoes::PRESENCA_ESTORNAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('responsavel'), Operacoes::PRESENCA_LER, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('ministerio_local', 'igreja', 'i1'), Operacoes::PRESENCA_LER, 'ebi', 'e1'))->toBeTrue();
});

it('nega escopo horizontal em outra EBI', function () {
    $ctx = $this->ctx;

    expect($this->autz->permite($ctx('coordenadora', 'ebi', 'outra'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse();
});

it('autoriza via Gate (policy + contexto da request)', function () {
    $usuario = criarUsuarioComPapel('coordenadora', 'ebi', 'e1');

    $request = Request::create('/api/v1/sessions/x', 'GET');
    $request->attributes->set('contexto_atuacao', new ContextoAtuacao('coordenadora', 'ebi', 'e1'));
    $this->app->instance('request', $request);

    expect(Gate::forUser($usuario)->allows(Operacoes::AGENDA_GERENCIAR, ['ebi', 'e1']))->toBeTrue()
        ->and(Gate::forUser($usuario)->allows(Operacoes::PRESENCA_ESTORNAR, ['ebi', 'e1']))->toBeTrue();

    // Sem o atributo `contexto_atuacao`: o branch de negacao de `decidir()`.
    $this->app->instance('request', Request::create('/api/v1/sessions/x', 'GET'));

    expect(Gate::forUser($usuario)->allows(Operacoes::AGENDA_GERENCIAR, ['ebi', 'e1']))->toBeFalse()
        ->and(Gate::forUser($usuario)->allows(Operacoes::PRESENCA_ESTORNAR, ['ebi', 'e1']))->toBeFalse();

    // Auxiliar nao gere sessao (vertical, atraves do Gate).
    $auxiliar = criarUsuarioComPapel('auxiliar', 'ebi', 'e1');
    $requestAux = Request::create('/api/v1/sessions/x', 'GET');
    $requestAux->attributes->set('contexto_atuacao', new ContextoAtuacao('auxiliar', 'ebi', 'e1'));
    $this->app->instance('request', $requestAux);

    expect(Gate::forUser($auxiliar)->allows(Operacoes::SESSAO_GERENCIAR, ['ebi', 'e1']))->toBeFalse();
});
