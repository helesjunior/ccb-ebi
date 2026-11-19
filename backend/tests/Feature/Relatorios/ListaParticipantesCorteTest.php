<?php

use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Setor;
use App\Domain\Participantes\Models\Participante;

beforeEach(function () {
    $this->no = arvoreRelatorio(['nome' => 'Igreja Central do Setor']);
    ['igreja' => $this->outraIgreja] = igrejaNoSetor($this->no['setor']);
    $this->finalidade = 'finalidade=busca_reconhecimento';

    $this->auth = function (string $papel, string $tipoEscopo, string $escopoId): array {
        $usuario = criarUsuarioComPapel($papel, $tipoEscopo, $escopoId);

        return [
            'Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken,
            'X-Acting-Scope' => "papel={$papel};escopo={$tipoEscopo}:{$escopoId}",
        ];
    };
});

function variosParticipantes(Igreja $igreja, string $nome, int $quantos, string $status = Participante::STATUS_ATIVO): void
{
    for ($i = 0; $i < $quantos; $i++) {
        participanteNaIgreja($igreja, ['nome' => "{$nome} {$i}", 'status' => $status]);
    }
}

it('deixa grupos com 5 ou mais ativos e retira os de 1 a 4', function () {
    variosParticipantes($this->no['igreja'], 'Ana', 3);
    variosParticipantes($this->outraIgreja, 'Ana', 6);

    $auth = ($this->auth)('adm_setor', 'setor', $this->no['setor']->id);

    $resposta = $this->getJson("/api/v1/participants?q=Ana&{$this->finalidade}", $auth)->assertOk();

    expect($resposta->json('data'))->toHaveCount(6)
        ->and(collect($resposta->json('data'))->pluck('igreja_referencia_id')->unique()->all())
        ->toBe([$this->outraIgreja->id])
        ->and($resposta->json('suprimido'))->toBeTrue()
        ->and($resposta->json('motivo'))->toBe('limiar');
});

it('so conta ativos para o tamanho do grupo', function () {
    variosParticipantes($this->no['igreja'], 'Ana', 4);
    variosParticipantes($this->no['igreja'], 'Ana', 3, 'inativo');

    $auth = ($this->auth)('adm_setor', 'setor', $this->no['setor']->id);

    $resposta = $this->getJson("/api/v1/participants?q=Ana&{$this->finalidade}", $auth)->assertOk();

    expect($resposta->json('data'))->toBe([])
        ->and($resposta->json('suprimido'))->toBeTrue()
        ->and($resposta->json('motivo'))->toBe('limiar');
});

it('nunca mostra participante sem igreja de referencia', function () {
    Participante::factory()->count(6)->create(['nome' => 'Ana Sem Igreja', 'igreja_referencia_id' => null]);

    $auth = ($this->auth)('adm_setor', 'setor', $this->no['setor']->id);

    $resposta = $this->getJson("/api/v1/participants?q=Ana&{$this->finalidade}", $auth)->assertOk();

    // Sem referencia, eles nao formam grupo: nao entram em nenhum no do
    // relatorio. A entrada some sem suprimido, porque nada foi escondido.
    expect($resposta->json('data'))->toBe([])
        ->and($resposta->json())->not->toHaveKey('suprimido');
});

it('devolve lista vazia sem suprimido quando a busca nao acha ninguem', function () {
    $auth = ($this->auth)('adm_setor', 'setor', $this->no['setor']->id);

    $resposta = $this->getJson("/api/v1/participants?q=Zzz&{$this->finalidade}", $auth)->assertOk();

    expect($resposta->json('data'))->toBe([])
        ->and($resposta->json())->not->toHaveKey('suprimido');
});

it('mostra a propria igreja para quem atua na igreja', function () {
    variosParticipantes($this->no['igreja'], 'Ana', 3);

    $auth = ($this->auth)('coordenadora', 'igreja', $this->no['igreja']->id);

    $resposta = $this->getJson("/api/v1/participants?q=Ana&{$this->finalidade}", $auth)->assertOk();

    expect($resposta->json('data'))->toHaveCount(3)
        ->and($resposta->json())->not->toHaveKey('suprimido');
});

it('mostra a propria igreja para o adm_igreja', function () {
    variosParticipantes($this->no['igreja'], 'Ana', 2);

    $auth = ($this->auth)('adm_igreja', 'igreja', $this->no['igreja']->id);

    expect($this->getJson("/api/v1/participants?q=Ana&{$this->finalidade}", $auth)->assertOk()->json('data'))
        ->toHaveCount(2);
});

it('nao conta para o limite quem esta fora do territorio', function () {
    variosParticipantes($this->no['igreja'], 'Ana', 2);

    $outroSetor = Setor::factory()->create(['regional_id' => $this->no['regional']->id]);
    ['igreja' => $fora] = igrejaNoSetor($outroSetor);
    variosParticipantes($fora, 'Ana', 6);

    $auth = ($this->auth)('adm_setor', 'setor', $this->no['setor']->id);

    $resposta = $this->getJson("/api/v1/participants?q=Ana&{$this->finalidade}", $auth)->assertOk();

    // A igreja do escopo tem 2 ativos: sai inteira, mesmo com 6 fora do setor.
    expect($resposta->json('data'))->toBe([])
        ->and($resposta->json('suprimido'))->toBeTrue();
});
