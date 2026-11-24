<?php

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use Illuminate\Database\UniqueConstraintViolationException;

it('as factories criam a cadeia escala, membro e troca', function () {
    $troca = SolicitacaoTrocaEscala::factory()->create();
    $membro = $troca->membro;
    $escala = $membro->escala;

    expect($escala)->toBeInstanceOf(Escala::class)
        ->and($escala->status)->toBe(Escala::STATUS_PREVISTA)
        ->and($escala->sessao_id)->not->toBeEmpty()
        ->and($membro->escala_id)->toBe($escala->id)
        ->and($membro->status)->toBe(EscalaMembro::STATUS_ESCALADO)
        ->and($membro->funcao)->toBeIn([EscalaMembro::FUNCAO_COORDENADORA, EscalaMembro::FUNCAO_AUXILIAR])
        ->and($troca->status)->toBe(SolicitacaoTrocaEscala::STATUS_PENDENTE)
        ->and($troca->solicitante_id)->not->toBeEmpty()
        ->and($escala->membros)->toHaveCount(1);
});

it('uma escala por sessao: a segunda viola a unicidade', function () {
    $primeira = Escala::factory()->create();

    Escala::factory()->create(['sessao_id' => $primeira->sessao_id]);
})->throws(UniqueConstraintViolationException::class);

it('o banco aceita o mesmo usuario em dois membros: a unicidade e do servico', function () {
    $escala = Escala::factory()->create();
    $outraEscala = Escala::factory()->create();

    EscalaMembro::factory()->for($escala)->create(['usuario_id' => 'u1']);
    EscalaMembro::factory()->for($outraEscala)->create(['usuario_id' => 'u1']);

    expect(EscalaMembro::query()->where('usuario_id', 'u1')->count())->toBe(2);
});

it('ativos exclui o membro substituido', function () {
    $escala = Escala::factory()->create();
    $ativo = EscalaMembro::factory()->for($escala)->create();
    EscalaMembro::factory()->for($escala)->create(['status' => EscalaMembro::STATUS_SUBSTITUIDO]);

    expect($escala->membros()->ativos()->pluck('id')->all())->toBe([$ativo->id]);
});

it('statusAnterior devolve confirmado so depois da confirmacao', function () {
    $escala = Escala::factory()->create();
    $escalado = EscalaMembro::factory()->for($escala)->create();
    $confirmado = EscalaMembro::factory()->for($escala)->create(['confirmado_em' => now()]);

    expect($escalado->statusAnterior())->toBe(EscalaMembro::STATUS_ESCALADO)
        ->and($confirmado->statusAnterior())->toBe(EscalaMembro::STATUS_CONFIRMADO)
        ->and($confirmado->confirmado_em)->toBeInstanceOf(DateTimeImmutable::class);
});
