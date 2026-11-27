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

function alertasComo(string $papel, string $tipo, string $escopoId, string $ebiId, string $query = '?finalidade=aviso_12_anos')
{
    app('auth')->forgetGuards();
    $u = criarUsuarioComPapel($papel, $tipo, $escopoId);

    return test()->getJson("/api/v1/ebis/{$ebiId}/age-alerts{$query}", [
        'Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel={$papel};escopo={$tipo}:{$escopoId}",
    ]);
}

it('CT-11 coordenadora e chefe veem quem faz 12 anos, com leitura auditada', function (string $papel) {
    $p = participanteNaIgreja($this->igreja, ['data_nascimento' => '2014-12-10']);

    alertasComo($papel, 'ebi', $this->ebi->id, $this->ebi->id)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.participante_id', $p->id)
        ->assertJsonPath('data.0.matricula', $p->matricula)
        ->assertJsonPath('data.0.faz_12_em', '2026-12-10');

    $evento = EventoAuditoria::query()->where('acao', 'participante.lido')->sole();
    expect($evento->escopo)->toBe("ebi:{$this->ebi->id}")
        ->and($evento->motivo)->toBe('aviso_12_anos')
        ->and($evento->metadados_minimizados['origem_leitura'])->toBe('aviso_12_anos')
        ->and($evento->metadados_minimizados['participante_ids'])->toBe([$p->id]);
})->with(['coordenadora', 'coordenadora_chefe']);

it('CT-12 sem finalidade responde 422 e nao audita', function () {
    alertasComo('coordenadora', 'ebi', $this->ebi->id, $this->ebi->id, '')
        ->assertStatus(422)->assertJsonPath('code', 'finalidade_obrigatoria');

    expect(EventoAuditoria::query()->where('acao', 'participante.lido')->exists())->toBeFalse();
});

it('CT-13 fora do escopo, sem papel ou EBI inexistente responde 403 sem vazar nomes', function () {
    participanteNaIgreja($this->igreja, ['data_nascimento' => '2014-12-10', 'nome' => 'Nome Secreto']);
    ['ebi' => $outro] = igrejaComEbi();
    $arvore = arvoreRelatorio();
    participanteNaIgreja($arvore['igreja'], ['data_nascimento' => '2014-12-10', 'nome' => 'Nome Secreto']);

    $respostas = [
        alertasComo('coordenadora', 'ebi', $this->ebi->id, $outro->id),
        alertasComo('auxiliar', 'ebi', $this->ebi->id, $this->ebi->id),
        alertasComo('coordenadora', 'ebi', $this->ebi->id, (string) Str::ulid()),
        alertasComo('adm_regional', 'regional', $arvore['regional']->id, $arvore['ebi']->id),
    ];

    foreach ($respostas as $resposta) {
        $resposta->assertForbidden();
        expect($resposta->getContent())->not->toContain('Nome Secreto');
    }
    expect(EventoAuditoria::query()->where('acao', 'participante.lido')->exists())->toBeFalse();
});

it('CT-14 super_admin recebe 404 para EBI inexistente', function () {
    alertasComo('super_admin', 'central', 'c1', (string) Str::ulid())->assertNotFound();
});
