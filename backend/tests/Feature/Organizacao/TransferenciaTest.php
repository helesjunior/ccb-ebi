<?php

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Contracts\TransferirIgreja;
use App\Domain\Organizacao\Exceptions\SobreposicaoVigenciaException;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
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

it('rejeita transferência na data de inicio de um vinculo ja encerrado (sem vinculo ativo)', function () {
    $igreja = Igreja::factory()->create();
    $setorA = Setor::factory()->create();
    $setorB = Setor::factory()->create();

    // Vinculo ja encerrado [2024-01-01, 2024-06-01), sem vinculo ativo.
    IgrejaVinculacao::create([
        'igreja_id' => $igreja->id,
        'setor_id' => $setorA->id,
        'inicio' => '2024-01-01',
        'fim' => '2024-06-01',
    ]);

    // Data igual ao inicio do vinculo existente nao pode ser aceita.
    expect(fn () => app(TransferirIgreja::class)->executar($igreja->id, $setorB->id, new DateTimeImmutable('2024-01-01')))
        ->toThrow(SobreposicaoVigenciaException::class);
});
