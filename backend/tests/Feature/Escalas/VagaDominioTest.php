<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Escalas\Contracts\GerirVagaEscala;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\OfertaVagaEscala;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Escalas\Models\VagaEscala;
use App\Domain\Escalas\Services\GerenciadorVaga;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Sessoes\Models\Sessao;
use App\Support\ProblemException;
use Database\Seeders\PapelSeeder;

/**
 * Ciclo da vaga e da oferta (E07, evolucao, decisao 5): dona abre, membro da
 * equipe oferece, so a dona escolhe. Autorizacao agora x elegibilidade na data.
 *
 * `cenarioEscala()`, `membroEscala()` e `authEscala()` vem de EscalaGestaoTest;
 * `cenarioAprovacao()` vem de AprovacaoEscalaTest.
 */
beforeEach(fn () => $this->seed(PapelSeeder::class));

function gerirVaga(): GerirVagaEscala
{
    return app(GerirVagaEscala::class);
}

function ctxVaga(object $usuario, string $papel = 'coordenadora', array $c = []): ContextoDecisao
{
    return new ContextoDecisao((string) $usuario->id, $papel, 'ebi', (string) ($c['ebi']->id ?? 'ebi-1'));
}

/** Escala prevista com a dona (coordenadora) e a auxiliar, pronta para vaga. */
function escalaComEquipe(array &$c): Escala
{
    $escala = Escala::factory()->create([
        'sessao_id' => $c['sessao']->id,
        'equipe_id' => $c['equipe']->id,
        'ebi_id' => $c['ebi']->id,
        'igreja_id' => $c['igreja']->id,
        'estimativa_participantes' => 8,
        'participantes_por_auxiliar' => 4,
        'status' => Escala::STATUS_PREVISTA,
    ]);
    EscalaMembro::factory()->for($escala)->create(['usuario_id' => $c['coord']->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA]);
    EscalaMembro::factory()->for($escala)->create(['usuario_id' => $c['auxi']->id]);

    return $c['escala'] = $escala;
}

/** Pessoa de fora da escala, com papel de auxiliar no EBI. */
function pessoaDeFora(array $c, string $papel = 'auxiliar'): object
{
    return membroEscala($papel, 'ebi', $c['ebi']->id);
}

function vagaComOferta(array &$c, object $pessoa, ?EscalaMembro $substitui = null): array
{
    $escala = $c['escala'];
    $vagaId = gerirVaga()->abrir((string) $escala->id, $substitui?->id, null, ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);
    $ofertaId = gerirVaga()->oferecer($vagaId, (string) $pessoa->id);

    return [$vagaId, $ofertaId];
}

it('abre acrescimo e substituicao', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    $membro = $escala->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();

    $acrescimo = gerirVaga()->abrir((string) $escala->id, null, 'Falta gente', ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);
    $substituicao = gerirVaga()->abrir((string) $escala->id, (string) $membro->id, null, ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);

    expect(VagaEscala::query()->where('status', VagaEscala::STATUS_ABERTA)->count())->toBe(2)
        ->and((string) VagaEscala::query()->findOrFail($substituicao)->substituido_usuario_id)->toBe((string) $c['auxi']->id)
        ->and(VagaEscala::query()->findOrFail($acrescimo)->substitui_membro_id)->toBeNull();
    expect(EventoAuditoria::query()->where('acao', 'escala.vaga_aberta')->count())->toBe(2);
});

it('substituicao de membro coordenadora responde 422', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    $coordMembro = $escala->membros()->where('usuario_id', $c['coord']->id)->firstOrFail();

    try {
        gerirVaga()->abrir((string) $escala->id, (string) $coordMembro->id, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('membro_invalido')->and($e->status)->toBe(422);
    }
});

it('segunda vaga para o mesmo membro responde 409 vaga_existente', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    $membro = $escala->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();

    gerirVaga()->abrir((string) $escala->id, (string) $membro->id, null, ctxVaga($c['coord'], 'coordenadora', $c), null);

    try {
        gerirVaga()->abrir((string) $escala->id, (string) $membro->id, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('vaga_existente')->and($e->status)->toBe(409);
    }
});

it('abrir com sessao encerrada responde 409', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    $c['sessao']->forceFill(['status' => Sessao::STATUS_ENCERRADA])->save();

    try {
        gerirVaga()->abrir((string) $escala->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('sessao_em_estado_invalido')->and($e->status)->toBe(409);
    }
});

it('abrir com dona sem vinculo responde 409 dona_sem_vinculo', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    VinculoAcesso::query()->where('usuario_id', $c['coord']->id)->update(['fim' => now()->subDay()->toDateString()]);

    try {
        gerirVaga()->abrir((string) $escala->id, null, null, ctxVaga($c['chefe'], 'coordenadora_chefe', $c), (string) $c['chefe']->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('dona_sem_vinculo')->and($e->status)->toBe(409);
    }
});

it('oferecer: fora do EBI responde 404', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
    $estranho = Usuario::factory()->create();

    try {
        gerirVaga()->oferecer($vagaId, (string) $estranho->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('not_found')->and($e->status)->toBe(404);
    }
});

it('oferecer: vaga cancelada responde 409 vaga_indisponivel', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
    gerirVaga()->cancelar($vagaId, ctxVaga($c['coord'], 'coordenadora', $c), null);

    try {
        gerirVaga()->oferecer($vagaId, (string) pessoaDeFora($c)->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('vaga_indisponivel')->and($e->status)->toBe(409);
    }
});

it('oferecer: membro ativo e a dona recebem 422 membro_ja_escalado', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);

    foreach ([$c['auxi'], $c['coord']] as $pessoa) {
        try {
            gerirVaga()->oferecer($vagaId, (string) $pessoa->id);
            $this->fail('deveria recusar');
        } catch (ProblemException $e) {
            expect($e->problemCode)->toBe('membro_ja_escalado')->and($e->status)->toBe(422);
        }
    }
});

it('oferecer com conflito de horario responde 422 conflito_de_horario', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
    $pessoa = pessoaDeFora($c);

    // A pessoa ja esta noutra escala, no mesmo horario.
    $outraSessao = Sessao::factory()->create([
        'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id,
        'inicio_previsto' => '2026-12-05 23:30:00', 'fim_previsto' => '2026-12-06 01:30:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
    $outraEscala = Escala::factory()->create([
        'sessao_id' => $outraSessao->id, 'ebi_id' => $c['ebi']->id, 'igreja_id' => $c['igreja']->id, 'equipe_id' => $c['equipe']->id,
    ]);
    EscalaMembro::factory()->for($outraEscala)->create(['usuario_id' => $pessoa->id]);

    try {
        gerirVaga()->oferecer($vagaId, (string) $pessoa->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('conflito_de_horario')->and($e->status)->toBe(422)
            ->and($e->extra['conflitos'])->toHaveCount(1);
    }
});

it('segunda oferta pendente responde 409 oferta_existente', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
    $pessoa = pessoaDeFora($c);
    gerirVaga()->oferecer($vagaId, (string) $pessoa->id);

    try {
        gerirVaga()->oferecer($vagaId, (string) $pessoa->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('oferta_existente')->and($e->status)->toBe(409);
    }
});

it('vinculo de igreja vale para oferecer', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
    $pessoa = membroEscala('auxiliar', 'igreja', $c['igreja']->id);

    expect(gerirVaga()->oferecer($vagaId, (string) $pessoa->id))->not->toBe('');
});

it('vinculo com inicio futuro nao autoriza oferecer', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
    $pessoa = pessoaDeFora($c);
    VinculoAcesso::query()->where('usuario_id', $pessoa->id)->update(['inicio' => now()->addMonth()->toDateString()]);

    try {
        gerirVaga()->oferecer($vagaId, (string) $pessoa->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('not_found')->and($e->status)->toBe(404);
    }
});

it('vinculo com fim preenchido nao autoriza oferecer nem revela a vaga', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);

    $pessoa = pessoaDeFora($c);
    // Ativo e so `fim` nulo; com data de fim ja e revogacao, e revogada nao
    // descobre a vaga (404) em vez de receber um 422 que diria que ela existe.
    VinculoAcesso::query()->where('usuario_id', $pessoa->id)->update(['fim' => '2026-11-30']);

    try {
        gerirVaga()->oferecer($vagaId, (string) $pessoa->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('not_found')->and($e->status)->toBe(404);
    }
});

it('retira a propria oferta', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
    $pessoa = pessoaDeFora($c);
    $ofertaId = gerirVaga()->oferecer($vagaId, (string) $pessoa->id);

    gerirVaga()->retirarOferta($ofertaId, (string) $pessoa->id);
    expect(OfertaVagaEscala::query()->findOrFail($ofertaId)->status)->toBe(OfertaVagaEscala::STATUS_RETIRADA);

    try {
        gerirVaga()->retirarOferta($ofertaId, (string) pessoaDeFora($c)->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('not_found')->and($e->status)->toBe(404);
    }

    $outra = pessoaDeFora($c);
    $outraOferta = gerirVaga()->oferecer($vagaId, (string) $outra->id);
    gerirVaga()->escolher($outraOferta, ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);

    try {
        gerirVaga()->retirarOferta($outraOferta, (string) $outra->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('oferta_ja_decidida')->and($e->status)->toBe(409);
    }
});

it('escolher cria membro confirmado e preenche a vaga', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    $escala->forceFill(['status' => Escala::STATUS_APROVADA, 'aprovado_por' => $c['chefe']->id, 'aprovado_em' => now()])->save();
    $escala->refresh();

    $pessoa = pessoaDeFora($c);
    $outra = pessoaDeFora($c);
    [$vagaId, $ofertaId] = vagaComOferta($c, $pessoa);
    $ofertaOutra = gerirVaga()->oferecer($vagaId, (string) $outra->id);

    $membroId = gerirVaga()->escolher($ofertaId, ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);

    $membro = EscalaMembro::query()->findOrFail($membroId);
    expect($membro->funcao)->toBe(EscalaMembro::FUNCAO_AUXILIAR)
        ->and($membro->status)->toBe(EscalaMembro::STATUS_CONFIRMADO)
        ->and($membro->confirmado_em)->not->toBeNull()
        ->and((string) $membro->usuario_id)->toBe((string) $pessoa->id);

    expect(VagaEscala::query()->findOrFail($vagaId)->status)->toBe(VagaEscala::STATUS_PREENCHIDA)
        ->and(OfertaVagaEscala::query()->findOrFail($ofertaId)->status)->toBe(OfertaVagaEscala::STATUS_ACEITA)
        ->and((string) OfertaVagaEscala::query()->findOrFail($ofertaId)->membro_criado_id)->toBe($membroId)
        ->and(OfertaVagaEscala::query()->findOrFail($ofertaOutra)->status)->toBe(OfertaVagaEscala::STATUS_DESCARTADA);

    $escala->refresh();
    expect($escala->status)->toBe(Escala::STATUS_APROVADA)
        ->and((string) $escala->aprovado_por)->toBe((string) $c['chefe']->id)
        ->and($escala->participantes_por_auxiliar)->toBe(4);

    expect(EventoAuditoria::query()->where('acao', 'escala.vaga_preenchida')->count())->toBe(1);
});

it('substituicao preenchida marca o original e cancela a troca', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    $original = $escala->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();
    $original->forceFill(['status' => EscalaMembro::STATUS_CONFIRMADO])->save();
    $troca = SolicitacaoTrocaEscala::query()->create([
        'escala_membro_id' => $original->id, 'solicitante_id' => $c['auxi']->id,
        'motivo' => 'Viagem', 'status' => SolicitacaoTrocaEscala::STATUS_PENDENTE,
    ]);

    $pessoa = pessoaDeFora($c);
    [$vagaId, $ofertaId] = vagaComOferta($c, $pessoa, $original);
    $membroId = gerirVaga()->escolher($ofertaId, ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);

    expect($original->refresh()->status)->toBe(EscalaMembro::STATUS_SUBSTITUIDO)
        ->and((string) EscalaMembro::query()->findOrFail($membroId)->substitui_membro_id)->toBe((string) $original->id);

    $troca->refresh();
    expect($troca->status)->toBe(SolicitacaoTrocaEscala::STATUS_CANCELADA)
        ->and($troca->motivo_decisao)->toBe(GerenciadorVaga::MOTIVO_TROCA_VAGA_PREENCHIDA)
        ->and(VagaEscala::query()->findOrFail($vagaId)->status)->toBe(VagaEscala::STATUS_PREENCHIDA);
});

it('chefe e adm_igreja nao escolhem', function (string $papel) {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $pessoa = pessoaDeFora($c);
    [, $ofertaId] = vagaComOferta($c, $pessoa);
    $ator = $papel === 'coordenadora_chefe' ? $c['chefe'] : membroEscala('adm_igreja', 'igreja', $c['igreja']->id);

    try {
        gerirVaga()->escolher($ofertaId, ctxVaga($ator, $papel, $c), (string) $ator->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('nao_e_dona_da_escala')->and($e->status)->toBe(403);
    }

    expect(OfertaVagaEscala::query()->findOrFail($ofertaId)->status)->toBe(OfertaVagaEscala::STATUS_PENDENTE);
})->with(['coordenadora_chefe', 'adm_igreja']);

it('original indisponivel cancela a vaga e responde 409', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    $original = $escala->membros()->where('usuario_id', $c['auxi']->id)->firstOrFail();
    $pessoa = pessoaDeFora($c);
    [$vagaId, $ofertaId] = vagaComOferta($c, $pessoa, $original);

    $original->forceFill(['status' => EscalaMembro::STATUS_SUBSTITUIDO])->save();

    try {
        gerirVaga()->escolher($ofertaId, ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('vaga_indisponivel')->and($e->status)->toBe(409);
    }

    $vaga = VagaEscala::query()->findOrFail($vagaId);
    expect($vaga->status)->toBe(VagaEscala::STATUS_CANCELADA)
        ->and($vaga->motivo_encerramento)->toBe(VagaEscala::MOTIVO_INDISPONIVEL)
        ->and(OfertaVagaEscala::query()->findOrFail($ofertaId)->status)->toBe(OfertaVagaEscala::STATUS_DESCARTADA)
        ->and(EscalaMembro::query()->where('escala_id', $escala->id)->where('usuario_id', $pessoa->id)->exists())->toBeFalse();
});

it('oferta de quem ja entrou na escala nao pode ser escolhida', function () {
    $c = cenarioEscala();
    $escala = escalaComEquipe($c);
    $pessoa = pessoaDeFora($c);
    [$vagaId, $ofertaId] = vagaComOferta($c, $pessoa);

    // RF-5: a dona inclui a pessoa direto e depois tenta aceitar a oferta.
    EscalaMembro::factory()->for($escala)->create(['usuario_id' => $pessoa->id]);
    $antes = EscalaMembro::query()->where('escala_id', $escala->id)->count();

    try {
        gerirVaga()->escolher($ofertaId, ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('membro_ja_escalado')->and($e->status)->toBe(422);
    }

    expect(EscalaMembro::query()->where('escala_id', $escala->id)->count())->toBe($antes)
        ->and(OfertaVagaEscala::query()->findOrFail($ofertaId)->status)->toBe(OfertaVagaEscala::STATUS_PENDENTE)
        ->and(VagaEscala::query()->findOrFail($vagaId)->status)->toBe(VagaEscala::STATUS_ABERTA);
});

it('cancelar vaga descarta ofertas', function () {
    $c = cenarioEscala();
    escalaComEquipe($c);
    $vagaId = gerirVaga()->abrir((string) $c['escala']->id, null, null, ctxVaga($c['coord'], 'coordenadora', $c), null);
    $ofertaId = gerirVaga()->oferecer($vagaId, (string) pessoaDeFora($c)->id);

    gerirVaga()->cancelar($vagaId, ctxVaga($c['coord'], 'coordenadora', $c), (string) $c['coord']->id);

    expect(VagaEscala::query()->findOrFail($vagaId)->motivo_encerramento)->toBe(VagaEscala::MOTIVO_GESTAO)
        ->and(OfertaVagaEscala::query()->findOrFail($ofertaId)->status)->toBe(OfertaVagaEscala::STATUS_DESCARTADA);

    try {
        gerirVaga()->cancelar($vagaId, ctxVaga($c['coord'], 'coordenadora', $c), null);
        $this->fail('deveria recusar');
    } catch (ProblemException $e) {
        expect($e->problemCode)->toBe('vaga_indisponivel')->and($e->status)->toBe(409);
    }
});
