<?php

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Contracts\TransferirIgreja;
use App\Domain\Organizacao\Exceptions\SobreposicaoVigenciaException;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Setor;

it('transfere e o setor vigente passa a ser o novo (sem data de corte)', function () {
    $igreja = Igreja::factory()->create();
    $setorA = Setor::factory()->create();
    $setorB = Setor::factory()->create();
    app(TransferirIgreja::class)->executar($igreja->id, $setorA->id, now()->subYear());
    app(TransferirIgreja::class)->executar($igreja->id, $setorB->id, now());

    // A leitura VIGENTE — inclusive para um período anterior — resolve o setor NOVO.
    expect(app(ConsultarOrganizacao::class)->setorVigente($igreja->id)?->id)->toBe($setorB->id);
});

it('rejeita transferência que sobrepõe vigência', function () {
    $igreja = Igreja::factory()->create();
    $setorA = Setor::factory()->create();
    $setorB = Setor::factory()->create();
    app(TransferirIgreja::class)->executar($igreja->id, $setorA->id, now()->subMonth());
    expect(fn () => app(TransferirIgreja::class)->executar($igreja->id, $setorB->id, now()->subMonth()))
        ->toThrow(SobreposicaoVigenciaException::class);
});

it('registra evento de auditoria na transferência', function () {
    $igreja = Igreja::factory()->create();
    $setor = Setor::factory()->create();
    app(TransferirIgreja::class)->executar($igreja->id, $setor->id, now());
    expect(DB::table('evento_auditoria')->where('acao', 'transferir')->where('recurso', 'igreja')->count())->toBeGreaterThan(0);
});
