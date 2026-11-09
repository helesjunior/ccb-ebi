<?php

use App\Support\ProblemException;

it('serializa como problem+json', function () {
    $e = new ProblemException('forbidden', 'Acesso negado', 403, 'Sem permissão');
    $body = $e->toProblemArray();

    expect($body)->toMatchArray([
        'type' => 'about:blank',
        'title' => 'Acesso negado',
        'status' => 403,
        'detail' => 'Sem permissão',
        'code' => 'forbidden',
    ]);
});
