<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Participantes\Contracts\GerirMesclagem;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Support\Str;

/** CA-27: `PATCH /participants/{id}` (correcao de cadastro com motivo). */
beforeEach(function () {
    ['igreja' => $this->igrejaA, 'ebi' => $this->ebiA] = igrejaComEbi();
    ['igreja' => $this->igrejaB, 'ebi' => $this->ebiB] = igrejaComEbi();
    $this->coordA = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebiA->id);
    $this->authA = correcaoComo($this->coordA, 'coordenadora', "ebi:{$this->ebiA->id}");
});

function correcaoComo(Usuario $usuario, ?string $papel = null, ?string $escopo = null): array
{
    app('auth')->forgetGuards();
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    return $papel === null ? $auth : $auth + ['X-Acting-Scope' => "papel={$papel};escopo={$escopo}"];
}

function correcaoPatch(array $auth, string $id, array $corpo)
{
    return test()->patchJson("/api/v1/participants/{$id}", $corpo, $auth + ['Idempotency-Key' => (string) Str::uuid()]);
}

function correcaoPresencaValida(Participante $p, $igreja, $ebi): void
{
    $sessao = Sessao::factory()->create(['ebi_id' => $ebi->id, 'igreja_id' => $igreja->id]);
    Presenca::factory()->create([
        'sessao_id' => $sessao->id, 'participante_id' => $p->id,
        'registrado_por' => test()->coordA->id, 'status' => Presenca::STATUS_VALIDA,
    ]);
}

function eventosCorrecao(): int
{
    return EventoAuditoria::query()->where('acao', 'participante.corrigido')->count();
}

it('C1 corrige cadastro sem igreja ligado por presenca e audita so o que mudou', function () {
    $p = Participante::factory()->create(['igreja_referencia_id' => null, 'nome' => 'Ana', 'nome_social' => 'Aninha']);
    correcaoPresencaValida($p, $this->igrejaA, $this->ebiA);

    correcaoPatch($this->authA, $p->id, ['nome' => 'Ana Maria', 'nome_social' => 'Aninha', 'motivo' => 'nome digitado errado'])
        ->assertOk()->assertJsonPath('nome', 'Ana Maria');

    $evento = EventoAuditoria::query()->where('acao', 'participante.corrigido')->sole();
    expect($evento->motivo)->toBe('nome digitado errado')
        ->and($evento->escopo)->toBe("ebi:{$this->ebiA->id}")
        ->and($evento->metadados_minimizados['campos'])->toBe(['nome'])
        ->and($evento->metadados_minimizados['antes']['nome'])->toBe('Ana');
});

it('C2 chefe corrige participante da propria igreja', function () {
    $chefe = criarUsuarioComPapel('coordenadora_chefe', 'ebi', $this->ebiA->id);
    $p = participanteNaIgreja($this->igrejaA);

    correcaoPatch(correcaoComo($chefe, 'coordenadora_chefe', "ebi:{$this->ebiA->id}"), $p->id, ['nome' => 'Novo Nome', 'motivo' => 'ajuste'])
        ->assertOk();
});

it('C3 coordenadora nao corrige participante de outra igreja', function () {
    $p = participanteNaIgreja($this->igrejaB, ['nome' => 'Bia']);

    correcaoPatch($this->authA, $p->id, ['nome' => 'Bianca', 'motivo' => 'ajuste'])->assertForbidden();

    expect($p->fresh()->nome)->toBe('Bia')->and(eventosCorrecao())->toBe(0);
});

it('C4 corpo invalido responde 422', function (array $corpo) {
    $p = participanteNaIgreja($this->igrejaA, ['nome' => 'Ana']);

    correcaoPatch($this->authA, $p->id, $corpo)->assertUnprocessable();

    expect($p->fresh()->nome)->toBe('Ana')->and(eventosCorrecao())->toBe(0);
})->with([
    'sem motivo' => [['nome' => 'Outra']],
    'motivo longo' => [['nome' => 'Outra', 'motivo' => str_repeat('a', 256)]],
    'data futura' => [['data_nascimento' => now()->addDay()->toDateString(), 'motivo' => 'ajuste']],
    'igreja inexistente' => [['igreja_referencia_id' => 'nao-existe', 'motivo' => 'ajuste']],
]);

it('C4b sem mudanca real responde nada_a_corrigir', function () {
    $p = participanteNaIgreja($this->igrejaA, ['nome' => 'Ana']);

    correcaoPatch($this->authA, $p->id, ['motivo' => 'ajuste'])->assertUnprocessable()->assertJsonPath('code', 'nada_a_corrigir');
    correcaoPatch($this->authA, $p->id, ['nome' => 'Ana', 'motivo' => 'ajuste'])->assertUnprocessable()->assertJsonPath('code', 'nada_a_corrigir');

    expect(eventosCorrecao())->toBe(0);
});

it('C5 nova igreja fora do escopo responde 422', function () {
    $p = participanteNaIgreja($this->igrejaA);

    correcaoPatch($this->authA, $p->id, ['igreja_referencia_id' => $this->igrejaB->id, 'motivo' => 'mudou'])
        ->assertUnprocessable()->assertJsonPath('code', 'igreja_fora_do_escopo');

    expect($p->fresh()->igreja_referencia_id)->toBe($this->igrejaA->id)->and(eventosCorrecao())->toBe(0);
});

it('C6 cadastro mesclado responde 409', function () {
    $principal = participanteNaIgreja($this->igrejaA);
    $duplicado = participanteNaIgreja($this->igrejaA);
    app(GerirMesclagem::class)->mesclar($this->coordA->id, $principal->id, $duplicado->id);

    correcaoPatch($this->authA, $duplicado->id, ['nome' => 'Outro', 'motivo' => 'ajuste'])
        ->assertStatus(409)->assertJsonPath('code', 'participante_mesclado');

    expect(eventosCorrecao())->toBe(0);
});

it('C7 papeis sem participante.corrigir recebem 403', function () {
    $p = participanteNaIgreja($this->igrejaA, ['nome' => 'Ana']);
    $aux = criarUsuarioComPapel('auxiliar', 'ebi', $this->ebiA->id);
    $adm = criarUsuarioComPapel('adm_igreja', 'igreja', $this->igrejaA->id);
    $resp = criarUsuarioComPapel('responsavel');
    ResponsavelParticipante::factory()->aprovado()->create(['participante_id' => $p->id, 'usuario_id' => $resp->id]);

    correcaoPatch(correcaoComo($aux, 'auxiliar', "ebi:{$this->ebiA->id}"), $p->id, ['nome' => 'X', 'motivo' => 'ajuste'])->assertForbidden();
    correcaoPatch(correcaoComo($adm, 'adm_igreja', "igreja:{$this->igrejaA->id}"), $p->id, ['nome' => 'X', 'motivo' => 'ajuste'])->assertForbidden();
    correcaoPatch(correcaoComo($resp), $p->id, ['nome' => 'X', 'motivo' => 'ajuste'])->assertForbidden();

    expect($p->fresh()->nome)->toBe('Ana');
});

it('C8 participante inexistente responde 404', function () {
    correcaoPatch($this->authA, (string) Str::ulid(), ['nome' => 'X', 'motivo' => 'ajuste'])->assertNotFound();
});

it('C9 presenca nao liga cadastro de outra igreja', function () {
    $p = participanteNaIgreja($this->igrejaB, ['nome' => 'Bia']);
    correcaoPresencaValida($p, $this->igrejaA, $this->ebiA);

    correcaoPatch($this->authA, $p->id, ['nome' => 'Bianca', 'motivo' => 'ajuste'])->assertForbidden();
    correcaoPatch($this->authA, $p->id, ['igreja_referencia_id' => null, 'motivo' => 'ajuste'])->assertForbidden();

    expect($p->fresh()->nome)->toBe('Bia')->and($p->fresh()->igreja_referencia_id)->toBe($this->igrejaB->id);
});

it('C10 super_admin corrige cadastro sem igreja e sem presenca', function () {
    $admin = criarUsuarioComPapel('super_admin', 'central', 'c1');
    $p = Participante::factory()->create(['igreja_referencia_id' => null]);

    correcaoPatch(correcaoComo($admin, 'super_admin', 'central:c1'), $p->id, ['nome' => 'Corrigido', 'motivo' => 'ajuste'])->assertOk();

    expect(EventoAuditoria::query()->where('acao', 'participante.corrigido')->sole()->escopo)->toBe('central:c1');
});

it('C11 correcao do principal sobrevive ao desfazer da mesclagem', function () {
    $principal = participanteNaIgreja($this->igrejaA, ['nome' => 'Ana']);
    $duplicado = participanteNaIgreja($this->igrejaA);
    app(GerirMesclagem::class)->mesclar($this->coordA->id, $principal->id, $duplicado->id);

    correcaoPatch($this->authA, $principal->id, ['nome' => 'Ana Corrigida', 'motivo' => 'ajuste'])->assertOk();
    test()->postJson("/api/v1/participants/{$principal->id}/merge/undo", [], $this->authA + ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    expect($principal->fresh()->nome)->toBe('Ana Corrigida')->and($duplicado->fresh()->status)->toBe('ativo');
});
