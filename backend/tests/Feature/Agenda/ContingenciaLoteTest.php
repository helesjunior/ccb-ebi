<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Support\Str;

/** Contingencia em lote (CA6, CA7): a lista em papel lancada de uma vez. */
beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-18 13:00:00',
        'fim_previsto' => '2026-11-18 14:30:00',
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
    $this->lote = fn (array $ids, string $motivo = 'lista em papel', ?array $auth = null) => $this->postJson(
        "/api/v1/sessions/{$this->sessao->id}/contingency-attendance/batch",
        ['participante_ids' => $ids, 'motivo' => $motivo],
        $auth ?? $this->auth,
    );
});

it('CT-24 lanca o lote e conta criadas e ja existentes', function () {
    [$a, $b, $c] = Participante::factory()->count(3)->create(['data_nascimento' => '2016-01-01'])->all();
    Presenca::factory()->create(['sessao_id' => $this->sessao->id, 'participante_id' => $c->id, 'status' => Presenca::STATUS_VALIDA]);

    ($this->lote)([$a->id, $b->id, $c->id], 'queda de internet')->assertOk()
        ->assertJsonPath('resumo', ['criadas' => 2, 'ja_existiam' => 1, 'erros' => 0])
        ->assertJsonPath('data.0.resultado', 'criada')
        ->assertJsonPath('data.0.presenca.origem', 'contingencia')
        ->assertJsonPath('data.2.resultado', 'ja_existia');

    expect(Presenca::query()->where('sessao_id', $this->sessao->id)->where('status', Presenca::STATUS_VALIDA)->count())->toBe(3);
    $eventos = EventoAuditoria::query()->where('acao', 'presenca.contingencia')->get();
    expect($eventos)->toHaveCount(2)
        ->and($eventos->pluck('motivo')->unique()->all())->toBe(['queda de internet'])
        ->and($eventos->pluck('escopo')->unique()->all())->toBe(["ebi:{$this->ebi->id}"]);
});

it('CT-25 cada linha falha sozinha', function () {
    $ok = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
    $velho = Participante::factory()->create(['data_nascimento' => '2010-01-01']);
    $mesclado = Participante::factory()->create(['data_nascimento' => '2016-01-01', 'status' => 'mesclado']);

    ($this->lote)([$ok->id, $velho->id, (string) Str::ulid(), $mesclado->id])->assertOk()
        ->assertJsonPath('data.0.resultado', 'criada')
        ->assertJsonPath('data.1.erro', 'inelegivel')
        ->assertJsonPath('data.1.motivo_bloqueio', 'excecao_ausente_ou_invalida')
        ->assertJsonPath('data.2.erro', 'not_found')
        ->assertJsonPath('data.3.erro', 'not_found')
        ->assertJsonPath('resumo', ['criadas' => 1, 'ja_existiam' => 0, 'erros' => 3]);

    expect(Presenca::query()->count())->toBe(1);
});

it('CT-26 sessao fora de encerrada barra o lote todo', function (string $status) {
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
    $this->sessao->update(['status' => $status]);

    ($this->lote)([$p->id])->assertStatus(409)->assertJsonPath('code', 'sessao_em_estado_invalido');
    expect(Presenca::query()->count())->toBe(0);
})->with([Sessao::STATUS_ABERTA, Sessao::STATUS_PREVISTA, Sessao::STATUS_CANCELADA]);

it('CT-27 valida lista e motivo', function () {
    $id = (string) Str::ulid();
    $ids = fn (int $n) => array_map(fn () => (string) Str::ulid(), range(1, $n));

    foreach ([[[], 'lista em papel'], [$ids(101), 'lista em papel'], [[$id, $id], 'lista em papel'], [['abc'], 'lista em papel'], [[$id], 'ab'], [[$id], str_repeat('a', 256)]] as [$lista, $motivo]) {
        ($this->lote)($lista, $motivo)->assertStatus(422);
    }

    ($this->lote)($ids(100), str_repeat('a', 255))->assertOk()->assertJsonPath('resumo.erros', 100);
});

it('CT-28 outro EBI e auxiliar recebem 403', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
    ['ebi' => $outro] = igrejaComEbi();

    foreach ([['coordenadora', $outro->id], ['auxiliar', $this->ebi->id]] as [$papel, $ebiId]) {
        app('auth')->forgetGuards();
        $u = criarUsuarioComPapel($papel, 'ebi', $ebiId);
        ($this->lote)([$p->id], 'lista em papel', [
            'Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken,
            'X-Acting-Scope' => "papel={$papel};escopo=ebi:{$ebiId}",
        ])->assertForbidden();
    }

    expect(Presenca::query()->count())->toBe(0);
});
