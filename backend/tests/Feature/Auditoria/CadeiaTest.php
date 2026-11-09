<?php

use App\Domain\Auditoria\Contracts\RegistrarEvento;

it('encadeia os hashes', function () {
    $svc = app(RegistrarEvento::class);
    $a = $svc->registrar(['acao' => 'criar', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);
    $b = $svc->registrar(['acao' => 'editar', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);

    expect($a->hash_anterior)->toBeNull()
        ->and($b->hash_anterior)->toBe($a->hash_encadeado)
        ->and($a->hash_encadeado)->toHaveLength(64);
});

it('detecta violacao da cadeia', function () {
    $svc = app(RegistrarEvento::class);
    $svc->registrar(['acao' => 'a', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);
    $b = $svc->registrar(['acao' => 'b', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);
    $svc->registrar(['acao' => 'c', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);

    // Altera o meio da cadeia burlando a guarda via query builder.
    DB::table('evento_auditoria')->where('id', $b->id)->update(['metadados_minimizados' => json_encode(['x' => 1])]);

    expect(fn () => $svc->verificarIntegridade())->toThrow(RuntimeException::class);
});
