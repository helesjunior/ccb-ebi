<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Participantes\Contracts\GerirMesclagem;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Support\Str;

/**
 * CA-27 (ADR-0027): escopo de mesclagem. A coordenadora precisa dos dois
 * cadastros ligados a um EBI dela; a coordenadora chefe, de um. "Ligado" =
 * igreja de referencia no escopo, ou (sem igreja) presenca valida no EBI.
 */
beforeEach(function () {
    ['igreja' => $this->igrejaA, 'ebi' => $this->ebiA] = igrejaComEbi();
    ['igreja' => $this->igrejaB, 'ebi' => $this->ebiB] = igrejaComEbi();
    $this->coordA = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebiA->id);
    $this->chefeA = criarUsuarioComPapel('coordenadora_chefe', 'ebi', $this->ebiA->id);
});

function escopoComo(Usuario $usuario, string $papel, string $escopo): array
{
    app('auth')->forgetGuards();

    return [
        'Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel={$papel};escopo={$escopo}",
    ];
}

function escopoSemIgreja(): Participante
{
    return Participante::factory()->create(['igreja_referencia_id' => null, 'data_nascimento' => '2015-03-10']);
}

function escopoPresencaEm(Participante $p, $igreja, $ebi, string $status = Presenca::STATUS_VALIDA): void
{
    $sessao = Sessao::factory()->create(['ebi_id' => $ebi->id, 'igreja_id' => $igreja->id]);
    Presenca::factory()->create([
        'sessao_id' => $sessao->id, 'participante_id' => $p->id,
        'registrado_por' => test()->coordA->id, 'status' => $status,
    ]);
}

function escopoMesclar(array $auth, Participante $principal, Participante $duplicado)
{
    return test()->postJson("/api/v1/participants/{$principal->id}/merge", ['duplicado_id' => $duplicado->id], $auth + ['Idempotency-Key' => (string) Str::uuid()]);
}

function escopoDesfazer(array $auth, Participante $principal)
{
    return test()->postJson("/api/v1/participants/{$principal->id}/merge/undo", [], $auth + ['Idempotency-Key' => (string) Str::uuid()]);
}

function escopoMesclarServico(Participante $principal, Participante $duplicado): void
{
    app(GerirMesclagem::class)->mesclar((string) Str::ulid(), $principal->id, $duplicado->id);
}

it('M1 coordenadora mescla cadastro sem igreja ligado por presenca', function () {
    $principal = escopoSemIgreja();
    escopoPresencaEm($principal, $this->igrejaA, $this->ebiA);
    $duplicado = participanteNaIgreja($this->igrejaA);

    escopoMesclar(escopoComo($this->coordA, 'coordenadora', "ebi:{$this->ebiA->id}"), $principal, $duplicado)->assertOk();

    expect($duplicado->fresh()->status)->toBe('mesclado')
        ->and(EventoAuditoria::query()->where('acao', 'participante.mesclado')->exists())->toBeTrue();
});

it('M2 coordenadora nao mescla quando so um cadastro esta ligado', function () {
    $principal = participanteNaIgreja($this->igrejaA);
    $duplicado = participanteNaIgreja($this->igrejaB);

    escopoMesclar(escopoComo($this->coordA, 'coordenadora', "ebi:{$this->ebiA->id}"), $principal, $duplicado)
        ->assertForbidden()->assertJsonPath('code', 'acesso_negado');

    expect($principal->fresh()->status)->toBe('ativo')->and($duplicado->fresh()->status)->toBe('ativo');
});

it('M3 chefe mescla quando um cadastro esta ligado', function () {
    $principal = participanteNaIgreja($this->igrejaA);
    $duplicado = participanteNaIgreja($this->igrejaB);

    escopoMesclar(escopoComo($this->chefeA, 'coordenadora_chefe', "ebi:{$this->ebiA->id}"), $principal, $duplicado)->assertOk();

    expect($duplicado->fresh()->status)->toBe('mesclado');
});

it('M4 chefe nao mescla quando nenhum cadastro esta ligado', function () {
    $principal = participanteNaIgreja($this->igrejaB);
    $duplicado = participanteNaIgreja($this->igrejaB);

    escopoMesclar(escopoComo($this->chefeA, 'coordenadora_chefe', "ebi:{$this->ebiA->id}"), $principal, $duplicado)->assertForbidden();

    expect($duplicado->fresh()->status)->toBe('ativo');
});

it('M5 presenca estornada nao liga', function () {
    $principal = participanteNaIgreja($this->igrejaA);
    $duplicado = escopoSemIgreja();
    escopoPresencaEm($duplicado, $this->igrejaA, $this->ebiA, Presenca::STATUS_ESTORNADA);

    escopoMesclar(escopoComo($this->coordA, 'coordenadora', "ebi:{$this->ebiA->id}"), $principal, $duplicado)->assertForbidden();
});

it('M5b presenca nao liga cadastro de outra igreja', function () {
    $principal = participanteNaIgreja($this->igrejaA);
    $duplicado = participanteNaIgreja($this->igrejaB);
    escopoPresencaEm($duplicado, $this->igrejaA, $this->ebiA);

    escopoMesclar(escopoComo($this->coordA, 'coordenadora', "ebi:{$this->ebiA->id}"), $principal, $duplicado)->assertForbidden();

    expect($duplicado->fresh()->status)->toBe('ativo');
});

it('M6 coordenadora nao desfaz quando so um cadastro esta ligado', function () {
    $principal = participanteNaIgreja($this->igrejaA);
    $duplicado = participanteNaIgreja($this->igrejaB);
    escopoMesclarServico($principal, $duplicado);

    escopoDesfazer(escopoComo($this->coordA, 'coordenadora', "ebi:{$this->ebiA->id}"), $principal)->assertForbidden();

    expect($duplicado->fresh()->status)->toBe('mesclado');
});

it('M7 chefe desfaz quando um cadastro esta ligado', function () {
    $principal = participanteNaIgreja($this->igrejaA);
    $duplicado = participanteNaIgreja($this->igrejaB);
    escopoMesclarServico($principal, $duplicado);

    escopoDesfazer(escopoComo($this->chefeA, 'coordenadora_chefe', "ebi:{$this->ebiA->id}"), $principal)->assertOk();

    expect($duplicado->fresh()->status)->toBe('ativo')
        ->and(EventoAuditoria::query()->where('acao', 'participante.mesclagem_desfeita')->exists())->toBeTrue();
});

it('M8 coordenadora de outro EBI nao mescla', function () {
    $coordB = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebiB->id);
    $principal = escopoSemIgreja();
    escopoPresencaEm($principal, $this->igrejaA, $this->ebiA);
    $duplicado = participanteNaIgreja($this->igrejaA);

    escopoMesclar(escopoComo($coordB, 'coordenadora', "ebi:{$this->ebiB->id}"), $principal, $duplicado)->assertForbidden();
});

it('M9 chefe nao desfaz quando nenhum cadastro esta ligado', function () {
    $principal = participanteNaIgreja($this->igrejaB);
    $duplicado = participanteNaIgreja($this->igrejaB);
    escopoMesclarServico($principal, $duplicado);

    escopoDesfazer(escopoComo($this->chefeA, 'coordenadora_chefe', "ebi:{$this->ebiA->id}"), $principal)->assertForbidden();

    expect($duplicado->fresh()->status)->toBe('mesclado');
});

it('M10 coordenadora desfaz com o duplicado ligado so por presenca', function () {
    $principal = participanteNaIgreja($this->igrejaA);
    $duplicado = escopoSemIgreja();
    escopoPresencaEm($duplicado, $this->igrejaA, $this->ebiA);
    escopoMesclarServico($principal, $duplicado);

    escopoDesfazer(escopoComo($this->coordA, 'coordenadora', "ebi:{$this->ebiA->id}"), $principal)->assertOk();

    expect($duplicado->fresh()->status)->toBe('ativo');
});

it('M11 quando o principal herda outra igreja quem desfaz e a chefe dela', function () {
    $principal = escopoSemIgreja();
    escopoPresencaEm($principal, $this->igrejaA, $this->ebiA);
    $duplicado = participanteNaIgreja($this->igrejaB);

    escopoMesclar(escopoComo($this->chefeA, 'coordenadora_chefe', "ebi:{$this->ebiA->id}"), $principal, $duplicado)->assertOk();
    expect($principal->fresh()->igreja_referencia_id)->toBe($this->igrejaB->id);

    escopoDesfazer(escopoComo($this->chefeA, 'coordenadora_chefe', "ebi:{$this->ebiA->id}"), $principal)->assertForbidden();
    expect($duplicado->fresh()->status)->toBe('mesclado');

    $chefeB = criarUsuarioComPapel('coordenadora_chefe', 'ebi', $this->ebiB->id);
    escopoDesfazer(escopoComo($chefeB, 'coordenadora_chefe', "ebi:{$this->ebiB->id}"), $principal)->assertOk();

    expect($principal->fresh()->igreja_referencia_id)->toBeNull()
        ->and($duplicado->fresh()->status)->toBe('ativo');
});

it('M12 super_admin mescla cadastros sem igreja e sem presenca', function () {
    $admin = criarUsuarioComPapel('super_admin', 'central', 'c1');
    $principal = escopoSemIgreja();
    $duplicado = escopoSemIgreja();

    escopoMesclar(escopoComo($admin, 'super_admin', 'central:c1'), $principal, $duplicado)->assertOk();

    expect($duplicado->fresh()->status)->toBe('mesclado');
});
