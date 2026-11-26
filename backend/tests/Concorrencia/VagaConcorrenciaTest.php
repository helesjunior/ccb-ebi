<?php

use Tests\Concorrencia\Suporte;

/**
 * Concorrencia real (decisao 12 da spec): dois processos com conexoes proprias
 * ao PostgreSQL disputam a mesma operacao. Cada par termina num estado valido,
 * sem duplicar gente na escala e com a vaga coerente com o resultado; roda so
 * com TESTE_PGSQL=1 (tests/Pest.php pula sem ele).
 */
beforeEach(function () {
    $this->c = Suporte::cenario();
    $this->esc = $this->c['escopo'];
});

/** @param  array<string, mixed>  $c */
function dona(array $c, string $acao, string $id, string $alvo = ''): array
{
    return [$acao, $id, $alvo, (string) $c['dona']->id, 'coordenadora', (string) $c['escopo']];
}

/** Nenhum processo pode ter estourado: perder a corrida e' `code:...`, nao erro. */
function semErro(string ...$saidas): void
{
    foreach ($saidas as $saida) {
        expect($saida)->not->toStartWith('erro:', substr($saida, 0, 400));
    }
}

it('aceite x cancelamento da mesma vaga', function () {
    $c = $this->c;

    [$a, $b] = Suporte::correr(
        dona($c, 'escolher', (string) $c['ofertaOriginal']->id),
        dona($c, 'cancelar', (string) $c['vagaOriginal']->id),
    );

    semErro($a, $b);
    expect([$a, $b])->toContain('ok');

    $vaga = Suporte::statusDaVaga((string) $c['vagaOriginal']->id);

    if ($vaga === 'preenchida') {
        expect(Suporte::statusDaOferta((string) $c['ofertaOriginal']->id))->toBe('aceita');
        expect(Suporte::membrosAtivos((string) $c['escala']->id, (string) $c['ofertante']->id))->toBe(1);
        expect(Suporte::membro((string) $c['original']->id)?->status)->toBe('substituido');
    } else {
        expect($vaga)->toBe('cancelada');
        expect(Suporte::statusDaOferta((string) $c['ofertaOriginal']->id))->toBe('descartada');
        expect(Suporte::membrosAtivos((string) $c['escala']->id, (string) $c['ofertante']->id))->toBe(0);
        expect(Suporte::membro((string) $c['original']->id)?->status)->toBe('confirmado');
    }
});

it('aceite x aprovacao de troca do original', function () {
    $c = $this->c;

    [$a, $b] = Suporte::correr(
        dona($c, 'escolher', (string) $c['ofertaOriginal']->id),
        dona($c, 'decidir', (string) $c['trocaDoOriginal']->id, (string) $c['substitutoDaTroca']->id),
    );

    semErro($a, $b);

    $troca = Suporte::statusDaTroca((string) $c['trocaDoOriginal']->id);
    $vaga = Suporte::statusDaVaga((string) $c['vagaOriginal']->id);
    $original = Suporte::membro((string) $c['original']->id);

    // O original sai uma vez so; a vaga nunca fica aberta com o dono fora.
    expect($original)->not->toBeNull();
    expect($original->status)->toBe('substituido');
    expect($vaga)->not->toBe('aberta');

    $substituto = Suporte::membrosAtivos((string) $c['escala']->id, (string) $c['substitutoDaTroca']->id);
    $ofertante = Suporte::membrosAtivos((string) $c['escala']->id, (string) $c['ofertante']->id);

    if ($vaga === 'preenchida') {
        // O aceite chegou primeiro; a troca entao perde.
        expect($a)->toBe('ok');
        expect($ofertante)->toBe(1);
        expect($substituto)->toBe(0);
        expect(Suporte::membroDaOferta((string) $c['ofertaOriginal']->id))->not->toBeNull();
    } else {
        // A troca aprovou e matou a vaga daquele membro.
        expect($vaga)->toBe('cancelada');
        expect($b)->toBe('ok');
        expect($troca)->toBe('aprovada');
        expect($substituto)->toBe(1);
        expect($ofertante)->toBe(0);
        expect(Suporte::statusDaOferta((string) $c['ofertaOriginal']->id))->toBe('descartada');
    }
});

it('aceite x retirada do original', function () {
    $c = $this->c;

    [$a, $b] = Suporte::correr(
        dona($c, 'escolher', (string) $c['ofertaOriginal']->id),
        dona($c, 'retirar', (string) $c['original']->id),
    );

    semErro($a, $b);

    $vaga = Suporte::statusDaVaga((string) $c['vagaOriginal']->id);

    if ($b === 'ok') {
        // A retirada venceu: o membro saiu e a vaga dele morreu junto.
        expect(Suporte::membro((string) $c['original']->id))->toBeNull();
        expect($a)->toStartWith('code:');
        expect($vaga)->toBe('cancelada');
        expect(Suporte::statusDaOferta((string) $c['ofertaOriginal']->id))->toBe('descartada');
        expect(Suporte::membrosAtivos((string) $c['escala']->id, (string) $c['ofertante']->id))->toBe(0);
    } else {
        // O aceite venceu: o original virou `substituido`, e retirar quem ja foi
        // substituido e 409 `membro_em_estado_invalido` (nada some). A vaga
        // fica preenchida e a ofertante entra — a retirada nao desfaz o aceite.
        expect($b)->toBe('code:membro_em_estado_invalido');
        expect($a)->toBe('ok');
        expect($vaga)->toBe('preenchida');
        expect(Suporte::membrosAtivos((string) $c['escala']->id, (string) $c['ofertante']->id))->toBe(1);
    }
});

it('aceite x inclusao manual da mesma pessoa', function () {
    $c = $this->c;

    [$a, $b] = Suporte::correr(
        dona($c, 'escolher', (string) $c['ofertaAcrescimo']->id),
        dona($c, 'incluir', (string) $c['escala']->id, (string) $c['ofertante']->id),
    );

    semErro($a, $b);

    // Uma pessoa, uma vez so: quem perdeu leva 422 em vez de duplicar.
    expect(Suporte::membrosAtivos((string) $c['escala']->id, (string) $c['ofertante']->id))->toBe(1);
    expect([$a, $b])->toContain('ok');
    expect([$a, $b])->toContain('code:membro_ja_escalado');
});

it('dois aceites de ofertas da mesma vaga', function () {
    $c = $this->c;

    [$a, $b] = Suporte::correr(
        dona($c, 'escolher', (string) $c['ofertaAcrescimo']->id),
        dona($c, 'escolher', (string) $c['ofertaAcrescimoOutra']->id),
    );

    semErro($a, $b);

    // Exatamente um vence; o outro acha a vaga/oferta ja decidida.
    expect([$a, $b])->toContain('ok');
    expect("{$a}|{$b}")->toBeIn([
        'ok|code:oferta_ja_decidida',
        'code:oferta_ja_decidida|ok',
        'ok|code:vaga_indisponivel',
        'code:vaga_indisponivel|ok',
    ]);

    $vencedor = $a === 'ok' ? $c['ofertaAcrescimo'] : $c['ofertaAcrescimoOutra'];
    $perdedor = $a === 'ok' ? $c['ofertaAcrescimoOutra'] : $c['ofertaAcrescimo'];

    expect(Suporte::statusDaVaga((string) $c['vagaAcrescimo']->id))->toBe('preenchida');
    expect(Suporte::statusDaOferta((string) $vencedor->id))->toBe('aceita');
    expect(Suporte::statusDaOferta((string) $perdedor->id))->toBe('descartada');
    expect(Suporte::membrosAtivos((string) $c['escala']->id, (string) $vencedor->usuario_id))->toBe(1);
    expect(Suporte::membrosAtivos((string) $c['escala']->id, (string) $perdedor->usuario_id))->toBe(0);
});
