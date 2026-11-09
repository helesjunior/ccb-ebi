<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Privacidade\Contracts\ConsultarConsentimentos;
use App\Domain\Privacidade\Contracts\RegistrarConsentimento;
use App\Domain\Privacidade\Models\TermoVersao;
use App\Support\EvidenciaAceite;
use Illuminate\Support\Facades\Hash;

it('registra um aceite com evidencia e permite recuperar a versao do termo', function () {
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'versao' => '2026-11']);
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);

    $evidencia = new EvidenciaAceite(ip: '203.0.113.5', userAgent: 'phpunit');

    $m = app(RegistrarConsentimento::class)->registrar(
        $u->id, $termo->id, 'aceito', 'proprio', $evidencia,
    );

    expect($m->decisao)->toBe('aceito');
    expect($m->evidencia)->toContain('203.0.113.5');

    $recuperada = app(ConsultarConsentimentos::class)->encontrar($m->id);

    expect($recuperada?->termoVersao?->versao)->toBe('2026-11');
    expect($recuperada?->termoVersao?->texto_hash)->toBe($termo->texto_hash);
});

it('retirar cria uma manifestacao de retirada', function () {
    $termo = TermoVersao::factory()->create();
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);

    $m = app(RegistrarConsentimento::class)->retirar($u->id, $termo->id, new EvidenciaAceite('1.1.1.1', 'phpunit'));

    expect($m->decisao)->toBe('retirado');
});
