<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

/** Cria um vinculo de responsavel ja aprovado entre usuario e participante. */
function vinculoAprovado(string $usuarioId, string $participanteId): ResponsavelParticipante
{
    return ResponsavelParticipante::factory()->aprovado()->create([
        'usuario_id' => $usuarioId,
        'participante_id' => $participanteId,
    ]);
}

/**
 * Autorizacao das escritas do responsavel e da coordenadora contra um
 * participante (H2/H3 do review): criar vinculo de responsavel, solicitar
 * excecao e ler participante so podem ocorrer quando o ator **tem vinculo
 * aprovado** (propriedade) **ou** quando seu escopo territorial cobre a igreja
 * de referencia do participante. Sem isso, qualquer autenticado criava vinculo
 * ou excecao contra qualquer participante.
 */
beforeEach(function () {
    $this->seed(PapelSeeder::class);

    // Igreja A (com setor/regional/central) e igreja B (independente).
    $this->igrejaA = Igreja::factory()->create();
    $this->ebiA = Ebi::factory()->create(['igreja_id' => $this->igrejaA->id]);

    $this->igrejaB = Igreja::factory()->create();
});

/**
 * Cria um usuario com papel e escopo explicitos (o helper padrao gera um
 * escopo aleatorio que nao casa com a igreja do participante).
 */
function usuarioComEscopo(string $papel, string $tipoEscopo, string $escopoId): Usuario
{
    $usuario = Usuario::factory()->create([
        'senha' => Hash::make('senha-bem-longa-123'),
        'email_verificado_em' => now(),
    ]);

    VinculoAcesso::factory()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => $tipoEscopo,
        'escopo_id' => $escopoId,
    ]);

    return $usuario;
}

it('papel sem vinculo.gerenciar nem responsavel nao cria vinculo (H2)', function () {
    // `ministerio_local` nao tem `vinculo.gerenciar` e nao e responsavel: nao
    // pode criar vinculo contra um participante qualquer.
    $intruso = usuarioComEscopo('ministerio_local', 'central', 'c1');
    $auth = ['Authorization' => 'Bearer '.$intruso->createToken('t')->plainTextToken];

    $participante = participanteNaIgreja($this->igrejaA);

    $this->postJson("/api/v1/participants/{$participante->id}/guardians", [
        'nome' => 'Intruso', 'tipo_relacao' => 'responsavel',
    ], $auth + ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
});

it('responsavel abre a solicitacao de vinculo (RF-03: sem acesso aos dados ainda)', function () {
    $responsavel = usuarioComEscopo('responsavel', 'central', 'c1');
    $auth = ['Authorization' => 'Bearer '.$responsavel->createToken('t')->plainTextToken];

    $participante = participanteNaIgreja($this->igrejaA);

    // A solicitacao nasce `pendente` e nao da acesso: a coordenadora decide.
    $this->postJson("/api/v1/participants/{$participante->id}/guardians", [
        'nome' => 'Maria Souza', 'tipo_relacao' => 'mae',
    ], $auth + ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('validacao', 'pendente');
});

it('coordenadora com escopo sobre a igreja cria vinculo para o participante', function () {
    $coordenadora = usuarioComEscopo('coordenadora', 'ebi', $this->ebiA->id);
    $auth = [
        'Authorization' => 'Bearer '.$coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebiA->id}",
    ];

    $participante = participanteNaIgreja($this->igrejaA);

    $this->postJson("/api/v1/participants/{$participante->id}/guardians", [
        'nome' => 'Maria Souza', 'tipo_relacao' => 'mae',
    ], $auth + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
});

it('coordenadora fora do escopo nao cria vinculo (H2)', function () {
    $coordenadora = usuarioComEscopo('coordenadora', 'ebi', $this->ebiA->id);
    $auth = [
        'Authorization' => 'Bearer '.$coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebiA->id}",
    ];

    // Participante em outra igreja, fora da subarvore da coordenadora.
    $participante = participanteNaIgreja($this->igrejaB);

    $this->postJson("/api/v1/participants/{$participante->id}/guardians", [
        'nome' => 'Maria Souza', 'tipo_relacao' => 'mae',
    ], $auth + ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
});

it('responsavel aprovado pode solicitar excecao para seu dependente', function () {
    $responsavel = usuarioComEscopo('responsavel', 'central', 'c1');
    $auth = ['Authorization' => 'Bearer '.$responsavel->createToken('t')->plainTextToken];

    $participante = participanteNaIgreja($this->igrejaA);
    vinculoAprovado($responsavel->id, $participante->id);

    $this->postJson("/api/v1/participants/{$participante->id}/age-exceptions", [
        'tipo_necessidade' => 'neurodivergencia',
    ], $auth + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
});

it('responsavel sem vinculo nao solicita excecao (H2)', function () {
    $intruso = usuarioComEscopo('responsavel', 'central', 'c1');
    $auth = ['Authorization' => 'Bearer '.$intruso->createToken('t')->plainTextToken];

    $participante = participanteNaIgreja($this->igrejaA);

    $this->postJson("/api/v1/participants/{$participante->id}/age-exceptions", [
        'tipo_necessidade' => 'neurodivergencia',
    ], $auth + ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
});

it('coordenadora le participante apenas dentro do proprio escopo (H3)', function () {
    $coordenadora = usuarioComEscopo('coordenadora', 'ebi', $this->ebiA->id);
    $auth = [
        'Authorization' => 'Bearer '.$coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebiA->id}",
    ];

    $dentro = participanteNaIgreja($this->igrejaA);
    $fora = participanteNaIgreja($this->igrejaB);

    $this->getJson("/api/v1/participants/{$dentro->id}?finalidade=atendimento", $auth)->assertOk();
    $this->getJson("/api/v1/participants/{$fora->id}?finalidade=atendimento", $auth)->assertForbidden();
});

it('a busca de participantes so devolve candidatos dentro do escopo (H3)', function () {
    $coordenadora = usuarioComEscopo('coordenadora', 'ebi', $this->ebiA->id);
    $auth = [
        'Authorization' => 'Bearer '.$coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebiA->id}",
    ];

    $dentro = participanteNaIgreja($this->igrejaA, ['nome' => 'Patota']);
    participanteNaIgreja($this->igrejaB, ['nome' => 'Patota']);

    $this->getJson('/api/v1/participants?q=patota&finalidade=deduplicacao', $auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $dentro->id);
});
