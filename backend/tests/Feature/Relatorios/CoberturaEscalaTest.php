<?php

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Carbon\CarbonImmutable;
use Database\Seeders\PapelSeeder;

/**
 * M-06 no relatorio e no CSV (E07, RF-07 / RNF): sessoes ja ocorridas contra
 * as que tem escala aprovada com a equipe minima confirmada. Os helpers sao
 * locais porque o relatorio nao compartilha cenario com `tests/Feature/Escalas`.
 */

/** Usuario com papel e escopo do EBI, vigente desde o mes anterior. */
function membroCobertura(string $papel, string $ebiId): Usuario
{
    $u = Usuario::factory()->create();
    VinculoAcesso::query()->create([
        'usuario_id' => $u->id,
        'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $ebiId,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
        'status' => 'ativo',
    ]);

    return $u;
}

function cenarioCobertura(array $igreja = []): array
{
    $no = arvoreRelatorio();
    $no['igreja']->timezone = 'America/Sao_Paulo';
    $no['igreja']->fill($igreja)->save();

    return $no + [
        'auth' => fn (string $papel, string $tipo, string $id): array => [
            'Authorization' => 'Bearer '.criarUsuarioComPapel($papel, $tipo, $id)->createToken('t')->plainTextToken,
            'X-Acting-Scope' => "papel={$papel};escopo={$tipo}:{$id}",
        ],
    ];
}

function sessaoCobertura(array $c, string $quando, string $status = Sessao::STATUS_ENCERRADA): Sessao
{
    // `$quando` e o horario local (America/Sao_Paulo) da sessao.
    // Uma sessao por registro; contas usam `array_unique`.
    return Sessao::factory()->create([
        'ebi_id' => $c['ebi']->id,
        'igreja_id' => $c['igreja']->id,
        'inicio_previsto' => CarbonImmutable::parse($quando, 'America/Sao_Paulo')->utc()->format('Y-m-d H:i:s'),
        'fim_previsto' => CarbonImmutable::parse($quando, 'America/Sao_Paulo')->utc()->addMinutes(90)->format('Y-m-d H:i:s'),
        'status' => $status,
        'realizada' => $status === Sessao::STATUS_ENCERRADA,
    ]);
}

function escalaCobertura(array $c, Sessao $sessao, string $status = Escala::STATUS_APROVADA, int $porAuxiliar = 4, int $estimativa = 8): Escala
{
    return Escala::factory()->create([
        'sessao_id' => $sessao->id,
        'ebi_id' => $c['ebi']->id,
        'igreja_id' => $c['igreja']->id,
        'estimativa_participantes' => $estimativa,
        'participantes_por_auxiliar' => $porAuxiliar,
        'status' => $status,
    ]);
}

function vagaCobertura(Escala $escala, string $usuarioId, string $status = EscalaMembro::STATUS_CONFIRMADO, string $funcao = EscalaMembro::FUNCAO_AUXILIAR): EscalaMembro
{
    return EscalaMembro::factory()->for($escala)->create([
        'usuario_id' => $usuarioId,
        'funcao' => $funcao,
        'status' => $status,
        'confirmado_em' => $status === EscalaMembro::STATUS_CONFIRMADO ? now() : null,
    ]);
}

function paginasCobertura(array $c, array $auth, string $tipo = 'igreja'): array
{
    $resposta = test()->getJson(
        "/api/v1/reports/attendance?nivel={$tipo}&id={$c[$tipo]->id}&inicio=2026-11-01&fim=2026-11-30",
        $auth,
    )->assertOk();

    $corpo = $resposta->json();

    return [
        'totais' => $corpo['totais'],
        'filhos' => $corpo['filhos'] ?? [],
    ];
}

beforeEach(function () {
    // `membroEscala()` (Escalas) busca o papel pelo codigo e nao semeia.
    $this->seed(PapelSeeder::class);
});

it('o denominador usa o fuso da igreja e deixa sessao cancelada de fora', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-20 23:30:00', 'UTC'));
    $c = cenarioCobertura();

    sessaoCobertura($c, '2026-11-20 20:00:00');                       // entra (23h UTC)
    sessaoCobertura($c, '2026-11-21 21:00:00');                       // nao entrou ainda
    sessaoCobertura($c, '2026-11-19 21:00:00', Sessao::STATUS_CANCELADA); // cancelada
    sessaoCobertura($c, '2026-11-10 10:00:00');                       // entra

    $auth = $c['auth']('adm_igreja', 'igreja', $c['igreja']->id);
    $totais = paginasCobertura($c, $auth)['totais'];

    expect($totais['sessoes_previstas'])->toBe(['valor' => 2, 'suprimido' => false, 'motivo' => null])
        ->and($totais['cobertura_escala'])->toBe(['valor' => 0, 'suprimido' => false, 'motivo' => null]);

    CarbonImmutable::setTestNow();
});

it('conta so a escala aprovada com equipe minima confirmada', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 23:30:00', 'UTC'));
    $c = cenarioCobertura();

    $prevista = sessaoCobertura($c, '2026-11-10 10:00:00');
    escalaCobertura($c, $prevista, Escala::STATUS_PREVISTA);
    $ok = sessaoCobertura($c, '2026-11-11 10:00:00');
    $comEquipe = escalaCobertura($c, $ok);
    vagaCobertura($comEquipe, membroCobertura('coordenadora', $c['ebi']->id)->id, funcao: EscalaMembro::FUNCAO_COORDENADORA);
    vagaCobertura($comEquipe, membroCobertura('auxiliar', $c['ebi']->id)->id);
    vagaCobertura($comEquipe, membroCobertura('auxiliar', $c['ebi']->id)->id);
    $semEquipe = sessaoCobertura($c, '2026-11-12 10:00:00');
    $soDivisor = escalaCobertura($c, $semEquipe);
    vagaCobertura($soDivisor, membroCobertura('coordenadora', $c['ebi']->id)->id, funcao: EscalaMembro::FUNCAO_COORDENADORA);

    $totais = paginasCobertura($c, $c['auth']('adm_igreja', 'igreja', $c['igreja']->id))['totais'];

    expect($totais['sessoes_previstas']['valor'])->toBe(3)
        ->and($totais['sessoes_com_equipe_minima']['valor'])->toBe(1)
        ->and($totais['cobertura_escala']['valor'])->toBe(33.3);

    CarbonImmutable::setTestNow();
});

it('troca pendente tira a sessao da cobertura', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 23:30:00', 'UTC'));
    $c = cenarioCobertura();

    $sessao = sessaoCobertura($c, '2026-11-10 10:00:00');
    $escala = escalaCobertura($c, $sessao);
    vagaCobertura($escala, membroCobertura('coordenadora', $c['ebi']->id)->id, funcao: EscalaMembro::FUNCAO_COORDENADORA);
    $doAuxiliar = membroCobertura('auxiliar', $c['ebi']->id);
    $vaga = vagaCobertura($escala, $doAuxiliar->id);
    vagaCobertura($escala, membroCobertura('auxiliar', $c['ebi']->id)->id);
    // O fluxo de troca marca a vaga como `troca_solicitada`: ela deixa de
    // contar como confirmada ate a troca ser decidida.
    $vaga->update(['status' => EscalaMembro::STATUS_TROCA_SOLICITADA]);
    SolicitacaoTrocaEscala::factory()->create([
        'escala_membro_id' => $vaga->id,
        'solicitante_id' => $doAuxiliar->id,
        'status' => SolicitacaoTrocaEscala::STATUS_PENDENTE,
    ]);

    $auth = $c['auth']('adm_igreja', 'igreja', $c['igreja']->id);

    expect(paginasCobertura($c, $auth)['totais']['sessoes_com_equipe_minima']['valor'])->toBe(0);
});

it('divisor do EBI mudado depois da aprovacao nao muda a cobertura', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 23:30:00', 'UTC'));
    $c = cenarioCobertura();

    $sessao = sessaoCobertura($c, '2026-11-10 10:00:00');
    $escala = escalaCobertura($c, $sessao);
    vagaCobertura($escala, membroCobertura('coordenadora', $c['ebi']->id)->id, funcao: EscalaMembro::FUNCAO_COORDENADORA);
    vagaCobertura($escala, membroCobertura('auxiliar', $c['ebi']->id)->id);
    vagaCobertura($escala, membroCobertura('auxiliar', $c['ebi']->id)->id);

    $c['ebi']->forceFill(['participantes_por_auxiliar' => 50])->save();

    $auth = $c['auth']('adm_igreja', 'igreja', $c['igreja']->id);

    expect(paginasCobertura($c, $auth)['totais']['sessoes_com_equipe_minima']['valor'])->toBe(1);

    CarbonImmutable::setTestNow();
});

it('vinculo revogado hoje deixa de cobrir a sessao de hoje, mas nao a de ontem', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-20 23:30:00', 'UTC'));
    $c = cenarioCobertura();

    $auxiliar = membroCobertura('auxiliar', $c['ebi']->id);
    foreach (['2026-11-19 10:00:00', '2026-11-20 10:00:00'] as $quando) {
        $escala = escalaCobertura($c, sessaoCobertura($c, $quando));
        vagaCobertura($escala, membroCobertura('coordenadora', $c['ebi']->id)->id, funcao: EscalaMembro::FUNCAO_COORDENADORA);
        vagaCobertura($escala, $auxiliar->id);
        vagaCobertura($escala, membroCobertura('auxiliar', $c['ebi']->id)->id);
    }

    // Revogado antes das sessoes de hoje, mas depois da de ontem (`fim` e
    // inclusivo, entao 19/11 ainda vale para a sessao de 19).
    VinculoAcesso::query()->where('usuario_id', $auxiliar->id)->update(['fim' => '2026-11-19']);

    $auth = $c['auth']('adm_igreja', 'igreja', $c['igreja']->id);
    $totais = paginasCobertura($c, $auth)['totais'];

    expect($totais['sessoes_previstas']['valor'])->toBe(2)
        ->and($totais['sessoes_com_equipe_minima']['valor'])->toBe(1);

    CarbonImmutable::setTestNow();
});

it('nunca e suprimido: valor 3 aparece e o filho suprimido nao derruba o pai', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 23:30:00', 'UTC'));
    $c = cenarioCobertura();
    ['igreja' => $outra, 'ebi' => $ebiOutra] = igrejaNoSetor($c['setor'], ['timezone' => 'America/Sao_Paulo']);

    foreach (range(1, 3) as $i) {
        sessaoCobertura($c, "2026-11-1{$i} 10:00:00");
    }

    // Filho com 3 presencas: suprimido para quem atua acima da igreja.
    $sessaoOutra = Sessao::factory()->create([
        'igreja_id' => $outra->id,
        'ebi_id' => $ebiOutra->id,
        'inicio_previsto' => '2026-11-15 13:00:00',
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
    foreach (range(1, 3) as $i) {
        Presenca::factory()->create([
            'sessao_id' => $sessaoOutra->id,
            'participante_id' => Participante::factory()->create(['igreja_referencia_id' => $outra->id])->id,
            'status' => Presenca::STATUS_VALIDA,
        ]);
    }

    $authRegional = $c['auth']('adm_regional', 'regional', $c['regional']->id);
    $noPai = paginasCobertura($c + ['setor' => $c['setor']], $authRegional, 'setor');
    $filhosSuprimidos = collect($noPai['filhos'])->filter(fn (array $f): bool => $f['totais']['presencas']['suprimido'] === true);

    expect($noPai['totais']['sessoes_previstas'])->toBe(['valor' => 4, 'suprimido' => false, 'motivo' => null])
        ->and($noPai['totais']['sessoes_com_equipe_minima'])->toBe(['valor' => 0, 'suprimido' => false, 'motivo' => null])
        ->and(collect($noPai['filhos'])->firstWhere('id', $c['igreja']->id)['totais']['sessoes_previstas']['valor'])->toBe(3)
        ->and($filhosSuprimidos)->not->toBeEmpty();

    CarbonImmutable::setTestNow();
});

it('sem sessoes, cobertura_escala e null e continua visivel', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 23:30:00', 'UTC'));
    $c = cenarioCobertura();

    $totais = paginasCobertura($c, $c['auth']('adm_igreja', 'igreja', $c['igreja']->id))['totais'];

    expect($totais['cobertura_escala'])->toBe(['valor' => null, 'suprimido' => false, 'motivo' => null])
        ->and($totais['sessoes_previstas']['valor'])->toBe(0);

    CarbonImmutable::setTestNow();
});

it('o CSV traz as tres colunas com os mesmos valores e os criterios citam o corte', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-25 23:30:00', 'UTC'));
    $c = cenarioCobertura();

    $sessao = sessaoCobertura($c, '2026-11-10 10:00:00');
    $escala = escalaCobertura($c, $sessao);
    vagaCobertura($escala, membroCobertura('coordenadora', $c['ebi']->id)->id, funcao: EscalaMembro::FUNCAO_COORDENADORA);
    vagaCobertura($escala, membroCobertura('auxiliar', $c['ebi']->id)->id);
    vagaCobertura($escala, membroCobertura('auxiliar', $c['ebi']->id)->id);

    $auth = $c['auth']('adm_igreja', 'igreja', $c['igreja']->id);
    $consulta = "/api/v1/reports/attendance?nivel=igreja&id={$c['igreja']->id}&inicio=2026-11-01&fim=2026-11-30";
    $csv = test()->get($consulta.'&formato=csv', $auth)->assertOk()->getContent();

    $linhas = array_values(array_filter(explode("\n", trim($csv)), fn (string $l): bool => $l !== ''));
    $cabecalho = str_getcsv(array_shift($linhas));
    $linha = array_combine($cabecalho, str_getcsv($linhas[0]));

    expect($linha['sessoes_previstas'])->toBe('1')
        ->and($linha['sessoes_com_equipe_minima'])->toBe('1')
        ->and($linha['cobertura_escala'])->toBe('100.0')
        ->and($csv)->toContain('M-06');

    $relatorio = test()->getJson($consulta, $auth)
        ->assertOk()
        ->json('criterios');

    expect($relatorio)->toContain('M-06');

    CarbonImmutable::setTestNow();
});
