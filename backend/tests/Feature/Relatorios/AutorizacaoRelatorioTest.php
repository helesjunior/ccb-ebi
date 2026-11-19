<?php

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;

// A matriz e papel x operacao; a checagem aqui e sem recurso-alvo.
function relatorioPermite(string $papel, string $operacao): bool
{
    return app(AutorizarOperacao::class)->permite(
        new ContextoAtuacao(papelCodigo: $papel, tipoEscopo: 'central', escopoId: 'c1'),
        $operacao,
    );
}

it('concede relatorio.ler aos nove papeis da matriz', function (string $papel) {
    expect(relatorioPermite($papel, Operacoes::RELATORIO_LER))->toBeTrue();
})->with([
    'super_admin', 'adm_regional', 'adm_setor', 'adm_igreja', 'coordenadora',
    'ministerio_central', 'ministerio_regional', 'ministerio_setorial', 'ministerio_local',
]);

it('nega relatorio.ler a auxiliar e responsavel', function (string $papel) {
    expect(relatorioPermite($papel, Operacoes::RELATORIO_LER))->toBeFalse();
})->with(['auxiliar', 'responsavel']);

it('nao mexe nas operacoes existentes', function () {
    expect(relatorioPermite('auxiliar', Operacoes::SESSAO_LER))->toBeTrue()
        ->and(relatorioPermite('adm_regional', Operacoes::PARTICIPANTE_MESCLAR))->toBeFalse();
});
