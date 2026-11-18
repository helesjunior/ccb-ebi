<?php

use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Agenda\Models\AgendaExcecao;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('preserva auxiliares escalados como inteiro', function () {
    $sessao = Sessao::factory()->create(['auxiliares_escalados' => 2]);

    expect($sessao->fresh()->auxiliares_escalados)->toBe(2);
    expect(Schema::getColumnType('sessao', 'auxiliares_escalados'))->not->toBe('json');
});

it('nao permite duas presencas validas do mesmo participante na sessao', function () {
    $sessao = Sessao::factory()->create();

    $presenca = Presenca::factory()->create(['sessao_id' => $sessao->id, 'status' => Presenca::STATUS_VALIDA]);

    expect(fn () => Presenca::factory()->create([
        'sessao_id' => $sessao->id,
        'participante_id' => $presenca->participante_id,
        'status' => Presenca::STATUS_VALIDA,
    ]))
        ->toThrow(QueryException::class);
});

it('permite nova presenca depois do estorno', function () {
    $sessao = Sessao::factory()->create();
    $primeira = Presenca::factory()->create(['sessao_id' => $sessao->id, 'status' => Presenca::STATUS_VALIDA]);

    $primeira->update(['status' => Presenca::STATUS_ESTORNADA, 'motivo_estorno' => 'registro em duplicidade']);

    Presenca::factory()->create([
        'sessao_id' => $sessao->id,
        'participante_id' => $primeira->participante_id,
        'status' => Presenca::STATUS_VALIDA,
    ]);

    expect(Presenca::query()->where('sessao_id', $sessao->id)->count())->toBe(2);
});

it('nao permite duas sessoes no mesmo horario e permite depois de cancelar', function () {
    $sessao = Sessao::factory()->create();

    expect(fn () => Sessao::factory()->create([
        'ebi_id' => $sessao->ebi_id,
        'inicio_previsto' => $sessao->inicio_previsto,
        'status' => Sessao::STATUS_PREVISTA,
    ]))->toThrow(QueryException::class);

    $sessao->update(['status' => Sessao::STATUS_CANCELADA]);

    expect(Sessao::factory()->create([
        'ebi_id' => $sessao->ebi_id,
        'inicio_previsto' => $sessao->inicio_previsto,
        'status' => Sessao::STATUS_PREVISTA,
    ])->exists)->toBeTrue();
});

it('limita uma excecao de horario por faixa e dia', function () {
    $faixa = AgendaEbi::factory()->create();

    AgendaExcecao::factory()->create([
        'ebi_id' => $faixa->ebi_id,
        'agenda_ebi_id' => $faixa->id,
        'data' => '2026-12-24',
        'tipo' => AgendaExcecao::TIPO_HORARIO_ESPECIAL,
        'hora_inicio' => '09:00',
        'hora_fim' => '10:30',
    ]);

    expect(fn () => AgendaExcecao::factory()->create([
        'ebi_id' => $faixa->ebi_id,
        'agenda_ebi_id' => $faixa->id,
        'data' => '2026-12-24',
        'tipo' => AgendaExcecao::TIPO_HORARIO_ESPECIAL,
        'hora_inicio' => '11:00',
        'hora_fim' => '12:00',
    ]))->toThrow(QueryException::class);
});
