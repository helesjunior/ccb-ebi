<?php

use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Services\AutoridadeDePapel;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Support\Relatorios\VisibilidadeIgreja;
use Database\Seeders\PapelSeeder;

function ctxChefe(string $papel, string $tipo = 'ebi', string $id = 'e1'): ContextoAtuacao
{
    return new ContextoAtuacao(papelCodigo: $papel, tipoEscopo: $tipo, escopoId: $id);
}

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->autorizar = app(AutorizarOperacao::class);
});

it('a coordenadora chefe tem todas as operacoes da coordenadora', function () {
    $operacoes = (new ReflectionClass(Operacoes::class))->getConstants();

    $faltando = [];
    foreach ($operacoes as $operacao) {
        if ($this->autorizar->permite(ctxChefe('coordenadora', 'ebi', 'e1'), $operacao)
            && ! $this->autorizar->permite(ctxChefe('coordenadora_chefe', 'ebi', 'e1'), $operacao)) {
            $faltando[] = $operacao;
        }
    }

    expect($faltando)->toBe([]);
});

it('apenas a chefe aprova escala e decide troca', function () {
    expect($this->autorizar->permite(ctxChefe('coordenadora_chefe'), Operacoes::ESCALA_APROVAR))->toBeTrue()
        ->and($this->autorizar->permite(ctxChefe('coordenadora_chefe'), Operacoes::TROCA_DECIDIR))->toBeTrue()
        ->and($this->autorizar->permite(ctxChefe('coordenadora'), Operacoes::ESCALA_APROVAR))->toBeFalse()
        ->and($this->autorizar->permite(ctxChefe('coordenadora'), Operacoes::TROCA_DECIDIR))->toBeFalse()
        ->and($this->autorizar->permite(ctxChefe('auxiliar'), Operacoes::ESCALA_APROVAR))->toBeFalse();
});

it('ler escala alcanca os papeis da matriz e nega os ministerios acima e o auxiliar', function () {
    foreach (['super_admin', 'adm_regional', 'adm_setor', 'adm_igreja', 'ministerio_local', 'coordenadora', 'coordenadora_chefe'] as $papel) {
        expect($this->autorizar->permite(ctxChefe($papel), Operacoes::ESCALA_LER))->toBeTrue();
    }

    foreach (['ministerio_central', 'ministerio_regional', 'ministerio_setorial', 'auxiliar', 'responsavel'] as $papel) {
        expect($this->autorizar->permite(ctxChefe($papel), Operacoes::ESCALA_LER))->toBeFalse();
    }
});

it('gerenciar escala fica com adm_igreja, coordenadora e chefe', function () {
    expect($this->autorizar->permite(ctxChefe('adm_igreja', 'igreja', 'ig1'), Operacoes::ESCALA_GERENCIAR))->toBeTrue()
        ->and($this->autorizar->permite(ctxChefe('coordenadora'), Operacoes::ESCALA_GERENCIAR))->toBeTrue()
        ->and($this->autorizar->permite(ctxChefe('coordenadora_chefe'), Operacoes::ESCALA_GERENCIAR))->toBeTrue()
        ->and($this->autorizar->permite(ctxChefe('auxiliar'), Operacoes::ESCALA_GERENCIAR))->toBeFalse()
        ->and($this->autorizar->permite(ctxChefe('ministerio_local'), Operacoes::ESCALA_GERENCIAR))->toBeFalse();
});

it('a coordenadora nao nomeia a chefe, mas o adm_igreja nomeia', function () {
    $autoridade = app(AutoridadeDePapel::class);

    expect($autoridade->podeSobrePapel(ctxChefe('coordenadora'), 'coordenadora_chefe'))->toBeFalse()
        ->and($autoridade->podeSobrePapel(ctxChefe('adm_igreja', 'igreja', 'ig1'), 'coordenadora_chefe'))->toBeTrue()
        ->and($autoridade->podeSobrePapel(ctxChefe('auxiliar'), 'coordenadora_chefe'))->toBeFalse();
});

it('a chefe ve os numeros reais da igreja como a coordenadora', function () {
    expect(VisibilidadeIgreja::veAIgreja(ctxChefe('coordenadora_chefe')))->toBeTrue()
        ->and(VisibilidadeIgreja::veAIgrejaNaLista(ctxChefe('coordenadora_chefe')))->toBeTrue()
        ->and(VisibilidadeIgreja::suprime(ctxChefe('coordenadora_chefe')))->toBeFalse();
});

it('semeia o papel da coordenadora chefe', function () {
    expect(Papel::query()->where('codigo', 'coordenadora_chefe')->exists())->toBeTrue();
});
