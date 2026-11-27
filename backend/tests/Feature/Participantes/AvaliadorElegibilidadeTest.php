<?php

use App\Domain\Participantes\Contracts\AvaliarElegibilidade;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Services\AvaliadorElegibilidade;

/**
 * Elegibilidade etaria (RN-16, CA-17, CA-19). Referencia: 11 anos a idade.
 * A data da sessao e resolvida no fuso da igreja; 29/02 completa ano em 01/03
 * nos anos nao bissextos.
 */
function avaliar(string $nascimento, string $dataSessao, string $fuso = 'America/Sao_Paulo', ?ExcecaoEtaria $excecao = null)
{
    $p = Participante::factory()->create(['data_nascimento' => $nascimento]);

    return (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable($dataSessao), $fuso, $excecao);
}

it('bloqueia menor de 3 anos mesmo com excecao aprovada', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2024-06-01']);
    $excecao = ExcecaoEtaria::factory()->aprovada()->create(['participante_id' => $p->id]);

    $r = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2026-11-11 10:00:00'), 'America/Sao_Paulo', $excecao);

    expect($r->permitido)->toBeFalse();
    expect($r->motivo)->toContain('idade_minima');
});

it('permite de 3 a 11 anos', function () {
    foreach (['2023-05-10' => true, '2015-05-10' => true] as $nasc => $esperado) {
        $r = avaliar($nasc, '2026-11-11 10:00:00');
        expect($r->permitido)->toBe($esperado);
    }
});

it('bloqueia a vespera dos 12 e permite no dia dos 12 com excecao aprovada', function () {
    // Nasce 15/06/2014: faz 12 em 15/06/2026.
    $p = Participante::factory()->create(['data_nascimento' => '2014-06-15']);

    $vespera = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2026-06-14 23:00:00'), 'America/Sao_Paulo');
    expect($vespera->permitido)->toBeTrue(); // 11 anos

    $dia = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2026-06-15 08:00:00'), 'America/Sao_Paulo');
    expect($dia->permitido)->toBeFalse(); // 12 sem excecao
    expect($dia->motivo)->toContain('excecao');

    $excecao = ExcecaoEtaria::factory()->aprovada()->create(['participante_id' => $p->id]);
    $comExcecao = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2026-06-15 08:00:00'), 'America/Sao_Paulo', $excecao);
    expect($comExcecao->permitido)->toBeTrue();
});

it('29 de fevereiro completa ano em 01/03 em ano nao bissexto (RN-16)', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2015-02-28']);

    // Nasce 28/02/2015: 3 anos em 28/02/2018 (permitido), 2 anos em 27/02/2018 (bloqueado).
    $doisAnos = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2018-02-27 12:00:00'), 'America/Sao_Paulo');
    expect($doisAnos->permitido)->toBeFalse();

    $tresAnos = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2018-02-28 12:00:00'), 'America/Sao_Paulo');
    expect($tresAnos->permitido)->toBeTrue();
});

it('fuso da igreja decide a data local na virada', function () {
    // Nasce 11/11/2015: faz 11 em 11/11/2026. Em 11/11/2026 02:00 UTC,
    // Sao Paulo (UTC-3) ainda e 10/11 23:00 (10 anos => bloqueado se <3? nao, 10 permite);
    // usamos o limite de 3 anos para uma diferenca clara:
    $p = Participante::factory()->create(['data_nascimento' => '2023-11-12']);

    // 12/11/2026 02:00 UTC = 11/11 23:00 em Sao Paulo (ainda 2 anos) => bloqueado.
    $sp = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2026-11-12 02:00:00', new DateTimeZone('UTC')), 'America/Sao_Paulo');
    expect($sp->permitido)->toBeFalse();

    // No mesmo instante, Manaus (UTC-4) e 11/11 22:00 => tambem bloqueado; mas
    // 12/11 03:00 UTC ja e 12/11 00:00 em Sao Paulo => 3 anos (permitido).
    $ok = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2026-11-12 03:00:00', new DateTimeZone('UTC')), 'America/Sao_Paulo');
    expect($ok->permitido)->toBeTrue();
});

it('excecao suspensa, revogada ou expirada bloqueia (CA-17/RN-15)', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2014-06-15']);

    foreach ([ExcecaoEtaria::STATUS_SUSPENSA, ExcecaoEtaria::STATUS_REVOGADA] as $status) {
        $excecao = ExcecaoEtaria::factory()->create([
            'participante_id' => $p->id,
            'status' => $status,
        ]);

        $r = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2026-06-15 08:00:00'), 'America/Sao_Paulo', $excecao);
        expect($r->permitido)->toBeFalse();
    }
});

it('excecao aprovada mas vencida bloqueia (vigente_ate no passado)', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2014-06-15']);
    $excecao = ExcecaoEtaria::factory()->aprovada()->create([
        'participante_id' => $p->id,
        'vigente_ate' => '2026-01-01',
    ]);

    $r = (new AvaliadorElegibilidade)->avaliar($p, new DateTimeImmutable('2026-06-15 08:00:00'), 'America/Sao_Paulo', $excecao);
    expect($r->permitido)->toBeFalse();
});

function aviso(string $nascimento, string $agora = '2026-11-26 12:00:00', string $fuso = 'America/Sao_Paulo'): ?string
{
    $p = Participante::factory()->create(['data_nascimento' => $nascimento]);

    return app(AvaliarElegibilidade::class)->aviso12Anos($p, new DateTimeImmutable($agora, new DateTimeZone('UTC')), $fuso);
}

it('CT-5 avisa quem faz 12 anos de hoje ate 30 dias', function () {
    expect(aviso('2014-11-26'))->toBe('2026-11-26')
        ->and(aviso('2014-12-26'))->toBe('2026-12-26')
        ->and(aviso('2014-12-27'))->toBeNull()
        ->and(aviso('2014-11-25'))->toBeNull();
});

it('CT-6 excecao aprovada vigente na data dos 12 suprime o aviso', function (string $status, ?string $vigenteAte, ?string $esperado) {
    $p = Participante::factory()->create(['data_nascimento' => '2014-12-10']);
    ExcecaoEtaria::factory()->create([
        'participante_id' => $p->id, 'status' => $status, 'vigente_ate' => $vigenteAte, 'decidido_em' => now(),
    ]);

    expect(app(AvaliarElegibilidade::class)->aviso12Anos($p, new DateTimeImmutable('2026-11-26 12:00:00Z'), 'America/Sao_Paulo'))
        ->toBe($esperado);
})->with([
    'aprovada sem fim' => [ExcecaoEtaria::STATUS_APROVADA, null, null],
    'aprovada ate 31/12' => [ExcecaoEtaria::STATUS_APROVADA, '2026-12-31', null],
    'aprovada vencida antes' => [ExcecaoEtaria::STATUS_APROVADA, '2026-12-01', '2026-12-10'],
    'so solicitada' => [ExcecaoEtaria::STATUS_SOLICITADA, null, '2026-12-10'],
]);

it('CT-7 o fuso decide o hoje na virada do dia', function () {
    expect(aviso('2014-12-26', '2026-11-27 02:00:00'))->toBe('2026-12-26')
        ->and(aviso('2014-12-26', '2026-11-27 02:00:00', 'UTC'))->toBe('2026-12-26')
        ->and(aviso('2014-12-27', '2026-11-27 02:00:00'))->toBeNull()
        ->and(aviso('2014-12-27', '2026-11-27 02:00:00', 'UTC'))->toBe('2026-12-27');
});

it('CT-8 nascido em 29/02', function () {
    expect(aviso('2016-02-29', '2028-02-15 12:00:00'))->toBe('2028-02-29');
});

it('CT-9 aviso da igreja traz so os ativos dela, por data', function () {
    ['igreja' => $igreja] = igrejaComEbi();
    ['igreja' => $outra] = igrejaComEbi();
    $depois = participanteNaIgreja($igreja, ['data_nascimento' => '2014-12-20', 'nome' => 'Bia']);
    $antes = participanteNaIgreja($igreja, ['data_nascimento' => '2014-12-01', 'nome' => 'Ana']);
    participanteNaIgreja($igreja, ['data_nascimento' => '2014-12-05', 'status' => 'inativo']);
    participanteNaIgreja($igreja, ['data_nascimento' => '2014-12-06', 'status' => 'mesclado']);
    participanteNaIgreja($igreja, ['data_nascimento' => '2015-03-01']);
    participanteNaIgreja($outra, ['data_nascimento' => '2014-12-02']);

    $linhas = app(AvaliarElegibilidade::class)->aviso12AnosDaIgreja($igreja->id, new DateTimeImmutable('2026-11-26 12:00:00Z'), 'America/Sao_Paulo');

    expect(array_map(fn (array $l): string => $l['participante']->id, $linhas))->toBe([$antes->id, $depois->id])
        ->and(array_column($linhas, 'faz_12_em'))->toBe(['2026-12-01', '2026-12-20']);
});
