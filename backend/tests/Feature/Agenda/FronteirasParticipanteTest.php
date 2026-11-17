<?php

use App\Domain\Participantes\Contracts\AvaliarElegibilidade;
use App\Domain\Participantes\Contracts\ConsultarParticipante;
use App\Domain\Participantes\Contracts\VerificarPropriedadeParticipante;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;

it('busca por nome sem acento e por matricula, no maximo 20', function () {
    $ana = Participante::factory()->create(['nome' => 'Ana Conceição', 'matricula' => 'ABC12345']);
    Participante::factory()->count(25)->create(['nome' => 'Ana Maria']);

    $busca = app(ConsultarParticipante::class);

    expect($busca->buscar('conceicao'))->toHaveCount(1)
        ->and($busca->buscar('conceicao')[0]->id)->toBe($ana->id)
        ->and($busca->buscar('ABC1'))->toHaveCount(1)
        ->and($busca->buscar('ana'))->toHaveCount(20)
        ->and($busca->buscar('an'))->toBe([]);
});

it('avalia a elegibilidade pelo id usando a data local', function () {
    $avaliar = app(AvaliarElegibilidade::class);

    $onze = Participante::factory()->create(['data_nascimento' => '2015-06-10']);
    $doze = Participante::factory()->create(['data_nascimento' => '2014-06-09']);

    expect($avaliar->avaliarPorId($onze->id, new DateTimeImmutable('2026-06-09 23:00:00'), 'America/Sao_Paulo')->permitido)->toBeTrue()
        ->and($avaliar->avaliarPorId($doze->id, new DateTimeImmutable('2026-06-10 02:00:00'), 'America/Sao_Paulo')->permitido)->toBeFalse()
        ->and($avaliar->avaliarPorId($doze->id, new DateTimeImmutable('2026-06-10 02:00:00'), 'America/Sao_Paulo')->motivo)->toBe('excecao_ausente_ou_invalida');

    ExcecaoEtaria::factory()->create([
        'participante_id' => $doze->id,
        'status' => ExcecaoEtaria::STATUS_APROVADA,
        'decidido_em' => now(),
        'vigente_ate' => '2026-12-31',
    ]);

    expect($avaliar->avaliarPorId($doze->id, new DateTimeImmutable('2026-06-10 02:00:00'), 'America/Sao_Paulo')->permitido)->toBeTrue();
});

it('trata 29 de fevereiro em ano nao bissexto', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2020-02-29']);

    $avaliar = app(AvaliarElegibilidade::class);

    // Em 2023 (nao bissexto) o aniversario cai em 01/03: em 28/02 ainda tem 2 anos.
    expect($avaliar->avaliarPorId($p->id, new DateTimeImmutable('2023-02-28 12:00:00'), 'America/Sao_Paulo')->motivo)->toBe('idade_minima')
        ->and($avaliar->avaliarPorId($p->id, new DateTimeImmutable('2023-03-01 12:00:00'), 'America/Sao_Paulo')->permitido)->toBeTrue();
});

it('verifica propriedade apenas com vinculo aprovado e vigente', function () {
    $p = Participante::factory()->create();
    $responsavel = criarUsuarioComPapel('responsavel');

    $verificar = app(VerificarPropriedadeParticipante::class);
    expect($verificar->podeAcessar($responsavel->id, $p->id))->toBeFalse();

    ResponsavelParticipante::factory()->create([
        'usuario_id' => $responsavel->id,
        'participante_id' => $p->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_APROVADA,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
    ]);

    expect($verificar->podeAcessar($responsavel->id, $p->id))->toBeTrue();
});
