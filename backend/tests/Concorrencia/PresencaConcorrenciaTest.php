<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Sessoes\Models\Presenca;
use Tests\Concorrencia\Suporte;

/**
 * CT-29: dois processos lancam a mesma lista em papel na mesma sessao. A
 * unicidade da presenca valida segura o par: nada duplica, ninguem estoura.
 */
it('dois lotes iguais ao mesmo tempo gravam cada presenca uma vez', function () {
    $c = Suporte::cenarioContingencia();
    $ids = implode(',', array_map(fn ($p): string => (string) $p->id, $c['participantes']));
    $args = ['contingencia', (string) $c['sessao']->id, $ids, (string) $c['dona']->id, 'coordenadora', $c['escopo']];

    [$a, $b] = Suporte::correr($args, $args);

    expect([$a, $b])->toBe(['ok', 'ok'])
        ->and(Presenca::query()->where('sessao_id', $c['sessao']->id)->where('status', Presenca::STATUS_VALIDA)->count())->toBe(5)
        ->and(EventoAuditoria::query()->where('acao', 'presenca.contingencia')->count())->toBe(5);
});
