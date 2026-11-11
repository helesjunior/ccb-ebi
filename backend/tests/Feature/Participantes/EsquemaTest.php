<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Participantes\Models\SolicitacaoVinculo;
use Illuminate\Database\QueryException;

it('cria participante com matricula e origem', function () {
    $p = Participante::factory()->create(['origem_cadastro' => 'responsavel']);

    expect($p->matricula)->not->toBeEmpty();
    expect($p->origem_cadastro)->toBe('responsavel');
    expect($p->status)->not->toBeEmpty();
});

it('rejeita matricula duplicada', function () {
    Participante::factory()->create(['matricula' => 'ABCD2345X']);

    expect(fn () => Participante::factory()->create(['matricula' => 'ABCD2345X']))
        ->toThrow(QueryException::class);
});

it('responsavel pode existir sem conta', function () {
    $r = ResponsavelParticipante::factory()->create([
        'usuario_id' => null,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    expect($r->usuario_id)->toBeNull();
    expect($r->validacao)->toBe('pendente');
});

it('solicitacao de vinculo registra o tipo', function () {
    $usuario = Usuario::factory()->create();
    $s = SolicitacaoVinculo::factory()
        ->forUsuario($usuario->id)
        ->create(['tipo' => SolicitacaoVinculo::TIPO_CONTESTACAO]);

    expect($s->tipo)->toBe('contestacao');
});

it('excecao guarda responsavel concordante nullable', function () {
    $e = ExcecaoEtaria::factory()->create([
        'responsavel_concordante_id' => null,
        'status' => ExcecaoEtaria::STATUS_SOLICITADA,
    ]);

    expect($e->responsavel_concordante_id)->toBeNull();
    expect($e->status)->toBe('solicitada');
});

it('responsavel pertence a um participante', function () {
    $p = Participante::factory()->create();
    $r = ResponsavelParticipante::factory()->create(['participante_id' => $p->id]);

    expect($r->participante->is($p))->toBeTrue();
    expect($p->responsaveis)->toHaveCount(1);
});
