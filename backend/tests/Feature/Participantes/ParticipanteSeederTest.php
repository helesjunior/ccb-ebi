<?php

use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use Database\Seeders\ParticipanteSeeder;

/**
 * O seeder de casos-limite faz parte do conjunto de demonstração: garante que a
 * regua de idade, o vinculo de responsavel e a excecao vigente existam sem
 * erro, e que rodar de novo nao duplique (idempotencia por matricula).
 */
it('seeder de participantes cobre a regua e e idempotente', function () {
    $this->seed(ParticipanteSeeder::class);
    $this->seed(ParticipanteSeeder::class);

    expect(Participante::query()->count())->toBe(6);
    expect(ResponsavelParticipante::query()->count())->toBe(4);

    // O adolescente tem excecao aprovada com vigencia (CA-17).
    $adolescente = Participante::query()->where('nome', 'like', '%13 anos%')->firstOrFail();
    $excecao = ExcecaoEtaria::query()
        ->where('participante_id', $adolescente->id)
        ->where('status', ExcecaoEtaria::STATUS_APROVADA)
        ->first();

    expect($excecao)->not->toBeNull();
    expect($excecao->vigente_ate?->isFuture())->toBeTrue();

    // O responsavel sem conta do cadastro assistido esta sem usuario (ADR-0015).
    $assistido = Participante::query()->where('origem_cadastro', Participante::ORIGEM_ASSISTIDO)->firstOrFail();
    $vinculo = ResponsavelParticipante::query()->where('participante_id', $assistido->id)->firstOrFail();

    expect($vinculo->usuario_id)->toBeNull();
    expect($vinculo->validacao)->toBe(ResponsavelParticipante::VALIDACAO_APROVADA);
});
