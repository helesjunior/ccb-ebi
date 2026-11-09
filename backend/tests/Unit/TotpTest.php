<?php

use App\Domain\Identidade\Support\Totp;

it('gera codigos compativeis com os vetores RFC 6238 (SHA1)', function () {
    // Segredo ASCII "12345678901234567890" em base32.
    $segredo = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    expect(Totp::codigoAtual($segredo, 59))->toBe('287082')
        ->and(Totp::codigoAtual($segredo, 1111111109))->toBe('081804');
});

it('valida dentro da janela e rejeita fora', function () {
    $segredo = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    $codigo = Totp::codigoAtual($segredo, 1000);

    expect(Totp::validar($segredo, $codigo, 1000))->toBeTrue()
        ->and(Totp::validar($segredo, '000000', 1000))->toBeFalse();
});
