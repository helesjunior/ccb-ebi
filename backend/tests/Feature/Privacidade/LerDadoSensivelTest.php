<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\DadosSensiveis\Contracts\ArmazenarDadoSensivel;
use App\Domain\DadosSensiveis\Contracts\LerDadoSensivel;
use App\Support\ProblemException;

beforeEach(function () {
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);
});

it('decifra e grava evento de auditoria com ator e finalidade', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'CID F90.0', null, 'excecao_etaria', 'consentimento', 'coordenador', null,
    );

    $texto = app(LerDadoSensivel::class)->ler($d->id, 'usuario-1', 'coordenador', 'igreja:1');

    expect($texto)->toBe('CID F90.0');

    $evento = EventoAuditoria::query()->where('acao', 'dado_sensivel.ler')->latest('sequencia')->first();
    expect($evento)->not->toBeNull();
    expect($evento->ator_id)->toBe('usuario-1');
    expect($evento->recurso)->toBe('dado_sensivel_participante');
    expect($evento->metadados_minimizados['finalidade'])->toBe('excecao_etaria');
});

it('falha ao ler sem ator (nao existe leitura anonima)', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'x', null, 'f', 'b', 'coordenador', null,
    );
    expect(fn () => app(LerDadoSensivel::class)->ler($d->id, '', 'coordenador'))
        ->toThrow(RuntimeException::class);
});

it('nega leitura a papel fora do controle de acesso', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'x', null, 'f', 'b', 'coordenador', null,
    );
    expect(fn () => app(LerDadoSensivel::class)->ler($d->id, 'u2', 'adm_setor'))
        ->toThrow(ProblemException::class);
});

it('404 para dado sensivel inexistente', function () {
    expect(fn () => app(LerDadoSensivel::class)->ler('nao-existe', 'u1', 'coordenador'))
        ->toThrow(ProblemException::class);
});
