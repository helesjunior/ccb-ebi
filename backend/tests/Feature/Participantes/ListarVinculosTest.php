<?php

use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Support\Participantes\VisibilidadeVinculoResponsavel;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    ['igreja' => $this->outraIgreja, 'ebi' => $this->outraEbi] = igrejaComEbi();

    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->contexto = new ContextoAtuacao('coordenadora', 'ebi', $this->ebi->id, $this->coordenadora->id);
    $this->servico = app(VisibilidadeVinculoResponsavel::class);
});

it('lista apenas vinculos do territorio da coordenadora', function () {
    $meu = participanteNaIgreja($this->igreja, ['nome' => 'Ana do Territorio']);
    $alheio = participanteNaIgreja($this->outraIgreja, ['nome' => 'Beto de Fora']);
    $vinculoMeu = ResponsavelParticipante::factory()->create(['participante_id' => $meu->id, 'validacao' => 'pendente']);
    ResponsavelParticipante::factory()->create(['participante_id' => $alheio->id, 'validacao' => 'pendente']);

    $lista = $this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente');

    expect(collect($lista)->pluck('id')->all())->toBe([$vinculoMeu->id]);
});

it('inclui o vinculo do proprio ator mesmo fora do territorio que gerencia', function () {
    $fora = participanteNaIgreja($this->outraIgreja);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $fora->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => 'pendente',
    ]);

    $lista = $this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente');

    expect(collect($lista)->pluck('id')->all())->toBe([$vinculo->id]);
});

it('nao duplica o vinculo que cai nos dois recortes', function () {
    $p = participanteNaIgreja($this->igreja);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => 'pendente',
    ]);

    $lista = $this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente');

    expect(collect($lista)->pluck('id')->all())->toBe([$vinculo->id]);
});

it('mostra vinculo sem dono apenas pelo territorio', function () {
    $p = participanteNaIgreja($this->igreja);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id,
        'usuario_id' => null,
        'validacao' => 'pendente',
    ]);

    $lista = $this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente');

    expect(collect($lista)->pluck('id')->all())->toBe([$vinculo->id]);
});

it('filtra por validacao e aceita todos', function () {
    $p = participanteNaIgreja($this->igreja);
    ResponsavelParticipante::factory()->create(['participante_id' => $p->id, 'validacao' => 'pendente']);
    ResponsavelParticipante::factory()->aprovado()->create(['participante_id' => $p->id]);

    expect($this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente'))->toHaveCount(1)
        ->and($this->servico->listar($this->coordenadora->id, $this->contexto, 'aprovada'))->toHaveCount(1)
        ->and($this->servico->listar($this->coordenadora->id, $this->contexto, 'todos'))->toHaveCount(2);
});

it('sem contexto territorial lista apenas os vinculos proprios', function () {
    $p = participanteNaIgreja($this->igreja);
    $meu = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id, 'usuario_id' => $this->coordenadora->id,
    ]);
    ResponsavelParticipante::factory()->create(['participante_id' => $p->id, 'usuario_id' => null]);

    $lista = $this->servico->listar($this->coordenadora->id, null, 'todos');

    expect(collect($lista)->pluck('id')->all())->toBe([$meu->id]);
});

it('nao lista vinculo encerrado sob a situacao viva que ele ocupava', function () {
    $p = participanteNaIgreja($this->igreja);
    $encerrado = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id, 'validacao' => 'pendente', 'fim' => now()->toDateString(),
    ]);

    expect($this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente'))->toHaveCount(0)
        ->and(collect($this->servico->listar($this->coordenadora->id, $this->contexto, 'todos'))->pluck('id')->all())
        ->toBe([$encerrado->id]);
});
