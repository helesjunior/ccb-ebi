<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Contracts\ConsultarPresenca;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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
        'status' => Sessao::STATUS_ABERTA,
    ]);
});

it('busca exige finalidade e termo com tres caracteres', function () {
    $this->getJson("/api/v1/sessions/{$this->sessao->id}/participants?busca=Ana", $this->auth)
        ->assertStatus(422)->assertJsonPath('code', 'finalidade_obrigatoria');

    $this->getJson("/api/v1/sessions/{$this->sessao->id}/participants?busca=An&finalidade=presenca", $this->auth)
        ->assertStatus(422)->assertJsonPath('code', 'busca_curta');
});

it('busca devolve elegibilidade e audita a leitura', function () {
    $elegivel = Participante::factory()->create(['nome' => 'Ana Clara', 'data_nascimento' => '2016-01-01', 'igreja_referencia_id' => $this->igreja->id]);
    $bloqueado = Participante::factory()->create(['nome' => 'Ana Paula', 'data_nascimento' => '2013-01-01', 'igreja_referencia_id' => $this->igreja->id]);
    Participante::factory()->create(['nome' => 'Ana Bebe', 'data_nascimento' => '2024-06-01', 'igreja_referencia_id' => $this->igreja->id]);

    $this->getJson("/api/v1/sessions/{$this->sessao->id}/participants?busca=Ana&finalidade=registro_presenca", $this->auth)
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['id' => $elegivel->id, 'elegivel' => true, 'motivo_bloqueio' => null])
        ->assertJsonFragment(['id' => $bloqueado->id, 'elegivel' => false, 'motivo_bloqueio' => 'excecao_ausente_ou_invalida']);

    $evento = EventoAuditoria::query()->where('acao', 'participante.buscado')->firstOrFail();
    expect($evento->motivo)->toBe('registro_presenca')
        ->and($evento->metadados_minimizados['sessao_id'])->toBe($this->sessao->id);
});

it('registra presenca uma unica vez e devolve a existente no replay', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);

    $primeira = $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", [
        'participante_id' => $p->id,
    ], $this->auth)->assertCreated()->json('presenca.id');

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", [
        'participante_id' => $p->id,
    ], $this->auth)->assertOk()->assertJsonPath('presenca.id', $primeira);

    expect(Presenca::query()->where('sessao_id', $this->sessao->id)->count())->toBe(1);
});

it('recusa sessao prevista e bloqueia 12 anos sem excecao', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2013-01-01']);

    $this->sessao->update(['status' => Sessao::STATUS_PREVISTA]);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertStatus(409);

    $this->sessao->update(['status' => Sessao::STATUS_ABERTA]);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'inelegivel')
        ->assertJsonPath('motivo_bloqueio', 'excecao_ausente_ou_invalida');

    ExcecaoEtaria::factory()->create([
        'participante_id' => $p->id,
        'status' => ExcecaoEtaria::STATUS_APROVADA,
        'decidido_em' => now(),
        'vigente_ate' => '2027-01-01',
    ]);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertCreated();
});

it('avisa presenca simultanea em outra igreja no mesmo dia', function () {
    $outra = igrejaComEbi();
    $sessaoOutra = Sessao::factory()->create([
        'ebi_id' => $outra['ebi']->id,
        'igreja_id' => $outra['igreja']->id,
        'inicio_previsto' => '2026-11-18 18:00:00',
        'fim_previsto' => '2026-11-18 19:30:00',
        'status' => Sessao::STATUS_ABERTA,
    ]);
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertCreated();

    $coordOutra = criarUsuarioComPapel('coordenadora', 'ebi', $outra['ebi']->id);
    $authOutra = [
        'Authorization' => 'Bearer '.$coordOutra->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra['ebi']->id}",
    ];

    app('auth')->forgetGuards();
    $this->postJson("/api/v1/sessions/{$sessaoOutra->id}/attendance", ['participante_id' => $p->id], $authOutra)
        ->assertCreated()
        ->assertJsonPath('aviso', 'presenca_simultanea');
});

it('lista presencas com nome e matricula e audita apenas o primeiro registro', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
    $url = "/api/v1/sessions/{$this->sessao->id}/attendance";
    $id = $this->postJson($url, ['participante_id' => $p->id, 'origem' => 'app'], $this->auth)
        ->assertCreated()->assertJsonPath('presenca.participante_nome', $p->nome)
        ->assertJsonPath('presenca.matricula', $p->matricula)->json('presenca.id');
    $this->postJson($url, ['participante_id' => $p->id], $this->auth)->assertOk();
    $this->getJson($url, $this->auth)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.participante_nome', $p->nome)
        ->assertJsonPath('data.0.matricula', $p->matricula);
    $eventos = EventoAuditoria::query()->where('acao', 'presenca.registrada')->get();
    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->escopo)->toBe("ebi:{$this->ebi->id}")
        ->and($eventos[0]->metadados_minimizados)->toBe([
            'sessao_id' => $this->sessao->id, 'participante_id' => $p->id, 'origem' => 'app',
        ])
        ->and(app(ConsultarPresenca::class)->ebiDaPresenca($id))->toBe($this->ebi->id)
        ->and(app(ConsultarPresenca::class)->ebiDaPresenca('inexistente'))->toBeNull();
});

it('restringe busca e presencas ao escopo da sessao e resolve inexistente primeiro', function () {
    $outra = igrejaComEbi();
    $sessao = Sessao::factory()->create(['ebi_id' => $outra['ebi']->id, 'igreja_id' => $outra['igreja']->id]);
    $p = Participante::factory()->create();
    $this->getJson("/api/v1/sessions/{$sessao->id}/participants?busca=Ana&finalidade=presenca", $this->auth)->assertForbidden();
    $this->getJson("/api/v1/sessions/{$sessao->id}/attendance", $this->auth)->assertForbidden();
    $this->postJson("/api/v1/sessions/{$sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)->assertForbidden();
    $this->getJson('/api/v1/sessions/inexistente/participants', $this->auth)->assertNotFound();
    $this->getJson('/api/v1/sessions/inexistente/attendance', $this->auth)->assertNotFound();
});

it('limita candidatos e resolve igreja de referencia em dados minimizados', function () {
    Participante::factory()->count(21)->create(['nome' => 'Ana Silva', 'igreja_referencia_id' => $this->igreja->id]);
    $response = $this->getJson("/api/v1/sessions/{$this->sessao->id}/participants?busca=Ana&finalidade=presenca", $this->auth)
        ->assertOk()->assertJsonCount(20, 'data')
        ->assertJsonPath('data.0.igreja_referencia', ['id' => $this->igreja->id, 'nome' => $this->igreja->nome]);
    expect(array_keys($response->json('data.0')))->toBe(['id', 'nome', 'matricula', 'igreja_referencia', 'elegivel', 'motivo_bloqueio']);
    $evento = EventoAuditoria::query()->where('acao', 'participante.buscado')->firstOrFail();
    expect($evento->metadados_minimizados)->toBe([
        'termo' => 'Ana', 'sessao_id' => $this->sessao->id, 'participante_ids' => array_column($response->json('data'), 'id'),
    ]);
});

it('usa o dia local e ignora outra data mesma igreja e presenca estornada', function (string $inicio, bool $mesmaIgreja, string $status, ?string $aviso) {
    $this->ebi->update(['timezone' => 'America/Sao_Paulo']);
    $this->sessao->update(['inicio_previsto' => '2026-11-18 23:00:00', 'fim_previsto' => '2026-11-19 00:30:00']);
    $outra = igrejaComEbi();
    $sessao = Sessao::factory()->create([
        'ebi_id' => $outra['ebi']->id, 'igreja_id' => $mesmaIgreja ? $this->igreja->id : $outra['igreja']->id,
        'inicio_previsto' => $inicio, 'fim_previsto' => CarbonImmutable::parse($inicio)->addHour(),
    ]);
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
    Presenca::factory()->create(['sessao_id' => $sessao->id, 'participante_id' => $p->id, 'status' => $status]);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertCreated()->assertJsonPath('aviso', $aviso);
})->with([
    ['2026-11-19 02:59:59', false, 'valida', 'presenca_simultanea'],
    ['2026-11-19 03:00:00', false, 'valida', null],
    ['2026-11-18 02:59:59', false, 'valida', null],
    ['2026-11-18 03:00:00', false, 'valida', 'presenca_simultanea'],
    ['2026-11-18 18:00:00', true, 'valida', null],
    ['2026-11-18 18:00:00', false, 'estornada', null],
]);

it('valida origem e reavalia elegibilidade mesmo em replay', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
    $url = "/api/v1/sessions/{$this->sessao->id}/attendance";
    $this->postJson($url, ['participante_id' => $p->id, 'origem' => 'contingencia'], $this->auth)->assertUnprocessable();
    $this->postJson($url, ['participante_id' => $p->id], $this->auth)->assertCreated();
    $p->update(['data_nascimento' => '2024-06-01']);
    $this->postJson($url, ['participante_id' => $p->id], $this->auth)->assertUnprocessable()
        ->assertJsonPath('motivo_bloqueio', 'idade_minima');
});

it('recupera a presenca vencedora quando o indice unico rejeita a insercao', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
    $vencedora = null;
    $sessaoId = $this->sessao->id;
    $usuarioId = $this->coord->id;
    // Insere entre o SELECT vazio e o INSERT para exercer a violação real do índice.
    DB::listen(function ($query) use (&$vencedora, $sessaoId, $usuarioId, $p) {
        if ($vencedora === null && str_starts_with($query->sql, 'select * from "presenca"') && in_array($sessaoId, $query->bindings, true)) {
            $vencedora = Presenca::factory()->create([
                'sessao_id' => $sessaoId, 'participante_id' => $p->id,
                'registrado_por' => $usuarioId, 'status' => Presenca::STATUS_VALIDA,
            ]);
        }
    });
    $response = $this->postJson("/api/v1/sessions/{$sessaoId}/attendance", ['participante_id' => $p->id], $this->auth)->assertOk();
    expect($vencedora)->not->toBeNull();
    $response->assertJsonPath('presenca.id', $vencedora->id);
    expect(Presenca::query()->where('sessao_id', $sessaoId)->count())->toBe(1)
        ->and(EventoAuditoria::query()->where('acao', 'presenca.registrada')->count())->toBe(0);
});

it('reproduz a resposta com a mesma chave de idempotencia', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
    $auth = $this->auth + ['Idempotency-Key' => 'presenca-task-11'];
    $url = "/api/v1/sessions/{$this->sessao->id}/attendance";
    $primeira = $this->postJson($url, ['participante_id' => $p->id], $auth)->assertCreated()->json();
    $this->postJson($url, ['participante_id' => $p->id], $auth)->assertCreated()->assertExactJson($primeira);
    expect(EventoAuditoria::query()->where('acao', 'presenca.registrada')->count())->toBe(1);
});
