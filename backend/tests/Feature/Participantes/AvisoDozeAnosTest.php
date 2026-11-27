<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use Illuminate\Support\Carbon;

/**
 * Aviso de quem faz 12 anos (CA3, CA4, CA5): no responsavel, em
 * `GET /me/participants`; na coordenadora, em `GET /ebis/{id}/age-alerts`.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-11-26 12:00:00');
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
});

afterEach(fn () => Carbon::setTestNow());

it('CT-10 dependentes trazem faz_12_em sem auditar leitura', function () {
    $responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $dentro = participanteNaIgreja($this->igreja, ['data_nascimento' => '2014-12-10', 'nome' => 'Ana']);
    $fora = participanteNaIgreja($this->igreja, ['data_nascimento' => '2016-01-10', 'nome' => 'Bia']);
    foreach ([$dentro, $fora] as $p) {
        ResponsavelParticipante::factory()->aprovado()->create(['usuario_id' => $responsavel->id, 'participante_id' => $p->id]);
    }

    $dados = collect($this->getJson('/api/v1/me/participants', [
        'Authorization' => 'Bearer '.$responsavel->createToken('t')->plainTextToken,
    ])->assertOk()->json('data'))->keyBy('id');

    expect($dados[$dentro->id]['faz_12_em'])->toBe('2026-12-10')
        ->and($dados[$fora->id])->toHaveKey('faz_12_em')
        ->and($dados[$fora->id]['faz_12_em'])->toBeNull()
        ->and(EventoAuditoria::query()->where('acao', 'participante.lido')->exists())->toBeFalse();
});
