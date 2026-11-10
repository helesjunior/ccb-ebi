<?php

use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use Illuminate\Support\Facades\Schema;

it('cria a tabela dado_sensivel_participante com as colunas esperadas', function () {
    expect(Schema::hasTable('dado_sensivel_participante'))->toBeTrue();
    foreach (['id', 'participante_id', 'tipo', 'conteudo_cifrado', 'referencia_segura',
        'finalidade', 'base_legal', 'controle_acesso', 'prazo_retencao', 'expurgado_em'] as $col) {
        expect(Schema::hasColumn('dado_sensivel_participante', $col))->toBeTrue();
    }
});

it('scope expiraveis so traz prazos vencidos e nao expurgados', function () {
    $vencido = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'expurgado_em' => null,
    ]);
    $futuro = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->addYear(),
        'expurgado_em' => null,
    ]);
    $jaExpurgado = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'expurgado_em' => now(),
    ]);

    $ids = DadoSensivelParticipante::query()
        ->expiraveis(now())
        ->pluck('id');

    expect($ids)->toContain($vencido->id);
    expect($ids)->not->toContain($futuro->id, $jaExpurgado->id);
});
