<?php

use App\Support\Idempotencia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

function requisicaoComChave(string $chave): Request
{
    return Request::create('/x', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $chave]);
}

it('executa a acao quando nao ha chave de idempotencia', function () {
    $execucoes = 0;
    $resposta = Idempotencia::executar(Request::create('/x', 'POST'), function () use (&$execucoes) {
        $execucoes++;

        return response()->json(['ok' => true], 201);
    });

    expect($execucoes)->toBe(1)->and($resposta->getStatusCode())->toBe(201);
});

it('replay devolve a mesma resposta e nao executa duas vezes', function () {
    $chave = (string) Str::uuid();
    $execucoes = 0;
    $acao = function () use (&$execucoes) {
        $execucoes++;

        return response()->json(['ok' => true], 201);
    };

    Idempotencia::executar(requisicaoComChave($chave), $acao);
    $segunda = Idempotencia::executar(requisicaoComChave($chave), $acao);

    expect($execucoes)->toBe(1)->and($segunda->getStatusCode())->toBe(201);
});

it('chaves diferentes executam separadamente', function () {
    $execucoes = 0;
    $acao = function () use (&$execucoes) {
        $execucoes++;

        return response()->json(['ok' => true], 201);
    };

    Idempotencia::executar(requisicaoComChave((string) Str::uuid()), $acao);
    Idempotencia::executar(requisicaoComChave((string) Str::uuid()), $acao);

    expect($execucoes)->toBe(2);
});
