<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Privacidade\Models\TermoVersao;
use App\Support\Arquivo\ClienteAntivirus;
use App\Support\Participantes\EscopoAuditoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CA8/CA9: os eventos de participante gravam o escopo territorial, para que a
 * auditoria por territorio (`GET /audit-events`) os encontre.
 */
function escopoAuth(Usuario $usuario, ?string $papel = null, ?string $escopo = null): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken]
        + ($papel !== null ? ['X-Acting-Scope' => "papel={$papel};escopo={$escopo}"] : []);
}

function escopoDoEvento(string $acao): ?string
{
    return EventoAuditoria::query()->where('acao', $acao)->latest('ocorrido_em')->value('escopo');
}

function cadastrarComo(array $auth, ?string $igrejaId): string
{
    return (string) test()->postJson('/api/v1/participants', [
        'nome' => 'Dependente '.Str::random(4), 'data_nascimento' => '2016-05-10', 'igreja_referencia_id' => $igrejaId,
    ], $auth)->assertCreated()->json('id');
}

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->authCoord = fn () => escopoAuth($this->coord, 'coordenadora', "ebi:{$this->ebi->id}");
});

it('CT-41 daIgreja resolve o territorio da igreja', function () {
    $semEbi = Igreja::factory()->create();
    $escopos = app(EscopoAuditoria::class);

    expect($escopos->daIgreja(null))->toBeNull()
        ->and($escopos->daIgreja(''))->toBeNull()
        ->and($escopos->daIgreja((string) Str::ulid()))->toBeNull()
        ->and($escopos->daIgreja($semEbi->id))->toBe("igreja:{$semEbi->id}")
        ->and($escopos->daIgreja($this->igreja->id))->toBe("ebi:{$this->ebi->id}");
});

it('CT-15 e CT-16 cadastro pelo responsavel grava o territorio da crianca', function () {
    cadastrarComo(escopoAuth($this->responsavel), $this->igreja->id);
    expect(escopoDoEvento('participante.cadastrado'))->toBe("ebi:{$this->ebi->id}");

    EventoAuditoria::query()->delete();
    cadastrarComo(escopoAuth($this->responsavel), null);
    expect(escopoDoEvento('participante.cadastrado'))->toBeNull();

    EventoAuditoria::query()->delete();
    $semEbi = Igreja::factory()->create();
    cadastrarComo(escopoAuth($this->responsavel), $semEbi->id);
    expect(escopoDoEvento('participante.cadastrado'))->toBe("igreja:{$semEbi->id}");
});

it('operador cadastra na propria igreja; sem igreja vale o contexto; fora do escopo e 422', function () {
    cadastrarComo(($this->authCoord)(), $this->igreja->id);
    expect(escopoDoEvento('participante.cadastrado'))->toBe("ebi:{$this->ebi->id}");

    EventoAuditoria::query()->delete();
    cadastrarComo(($this->authCoord)(), null);
    expect(escopoDoEvento('participante.cadastrado'))->toBe("ebi:{$this->ebi->id}");

    ['igreja' => $outra] = igrejaComEbi();
    $antes = Participante::query()->count();
    $this->postJson('/api/v1/participants', [
        'nome' => 'Fora', 'data_nascimento' => '2016-05-10', 'igreja_referencia_id' => $outra->id,
    ], ($this->authCoord)())->assertStatus(422)->assertJsonPath('code', 'igreja_fora_do_escopo');
    expect(Participante::query()->count())->toBe($antes);
});

it('CT-17 eventos da coordenadora gravam o escopo do contexto', function () {
    Storage::fake('quarentena');
    Storage::fake('laudos');
    $this->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->andReturn(true));
    TermoVersao::factory()->create(['finalidade' => TermoVersao::FINALIDADE_CADASTRO, 'vigente_em' => now()->subDay(), 'encerrado_em' => null]);
    $esperado = "ebi:{$this->ebi->id}";

    $assistido = fn (?string $igreja) => $this->post('/api/v1/participants/assisted', array_filter([
        'nome' => 'Davi', 'data_nascimento' => '2015-04-01', 'responsavel_nome' => 'Marta', 'tipo_relacao' => 'mae',
        'igreja_referencia_id' => $igreja,
        'evidencia' => UploadedFile::fake()->createWithContent('termo.pdf', "%PDF-1.4\n%EOF\n"),
    ]), ($this->authCoord)() + ['Accept' => 'application/json']);

    $assistido($this->igreja->id)->assertCreated();
    expect(escopoDoEvento('participante.cadastrado'))->toBe($esperado);

    ['igreja' => $outra] = igrejaComEbi();
    $assistido($outra->id)->assertStatus(422)->assertJsonPath('code', 'igreja_fora_do_escopo');

    $principal = participanteNaIgreja($this->igreja, ['nome' => 'Lia Souza']);
    $duplicado = participanteNaIgreja($this->igreja);

    $this->getJson('/api/v1/participants?q=Lia&finalidade=dedup', ($this->authCoord)())->assertOk();
    $this->getJson("/api/v1/participants/{$principal->id}?finalidade=consulta", ($this->authCoord)())->assertOk();
    expect(EventoAuditoria::query()->where('acao', 'participante.lido')->pluck('escopo')->unique()->all())->toBe([$esperado]);

    $this->postJson("/api/v1/participants/{$principal->id}/merge", ['duplicado_id' => $duplicado->id], ($this->authCoord)())->assertOk();
    $this->postJson("/api/v1/participants/{$principal->id}/merge/undo", [], ($this->authCoord)())->assertOk();

    $vinculo = ResponsavelParticipante::factory()->create(['participante_id' => $principal->id, 'usuario_id' => $this->responsavel->id]);
    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], ($this->authCoord)())->assertOk();
    $this->getJson('/api/v1/guardian-requests?finalidade=triagem', ($this->authCoord)())->assertOk();

    $excecao = ExcecaoEtaria::factory()->create(['participante_id' => $principal->id, 'status' => ExcecaoEtaria::STATUS_SOLICITADA]);
    $this->patchJson("/api/v1/age-exceptions/{$excecao->id}", ['decisao' => 'aprovar'], ($this->authCoord)())->assertOk();

    foreach (['participante.mesclado', 'participante.mesclagem_desfeita', 'vinculo.decidido', 'vinculo.listado', 'excecao.decidida'] as $acao) {
        expect(escopoDoEvento($acao))->toBe($esperado, $acao);
    }
});

it('CT-18 pedidos do responsavel gravam o territorio da crianca', function () {
    $comIgreja = participanteNaIgreja($this->igreja);
    $semIgreja = Participante::factory()->create(['igreja_referencia_id' => null]);

    foreach ([[$comIgreja, "ebi:{$this->ebi->id}"], [$semIgreja, null]] as [$participante, $esperado]) {
        EventoAuditoria::query()->delete();
        $this->postJson("/api/v1/participants/{$participante->id}/guardians", ['nome' => 'Marta', 'tipo_relacao' => 'mae'], escopoAuth($this->responsavel))
            ->assertCreated();
        ResponsavelParticipante::query()->where('participante_id', $participante->id)->update(['validacao' => ResponsavelParticipante::VALIDACAO_APROVADA]);
        $this->postJson("/api/v1/participants/{$participante->id}/age-exceptions", ['tipo_necessidade' => 'neurodivergencia'], escopoAuth($this->responsavel))
            ->assertCreated();

        expect(escopoDoEvento('vinculo.solicitado'))->toBe($esperado)
            ->and(escopoDoEvento('excecao.solicitada'))->toBe($esperado);
    }
});

it('CT-19 adm_setor so ve o cadastro do proprio setor', function () {
    $a = arvoreRelatorio();
    $b = arvoreRelatorio();
    $idA = cadastrarComo(escopoAuth($this->responsavel), $a['igreja']->id);
    cadastrarComo(escopoAuth($this->responsavel), $b['igreja']->id);
    cadastrarComo(escopoAuth($this->responsavel), null);

    $cadastrados = fn (array $auth) => collect(test()->getJson('/api/v1/audit-events?recurso=participante', $auth)->assertOk()->json('data'))
        ->where('acao', 'participante.cadastrado')->values();

    $admSetor = criarUsuarioComPapel('adm_setor', 'setor', $a['setor']->id);
    $vistos = $cadastrados(escopoAuth($admSetor, 'adm_setor', "setor:{$a['setor']->id}"));
    expect($vistos)->toHaveCount(1)
        ->and($vistos[0]['metadados_minimizados']['participante_id'])->toBe($idA);

    $admin = criarUsuarioComPapel('super_admin', 'central', $a['central']->id);
    expect($cadastrados(escopoAuth($admin, 'super_admin', "central:{$a['central']->id}")))->toHaveCount(3);
});
