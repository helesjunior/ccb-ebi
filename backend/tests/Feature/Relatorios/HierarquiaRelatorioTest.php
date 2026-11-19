<?php

use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;

it('devolve os filhos diretos de cada nivel e nada para a ebi', function () {
    $no = arvoreRelatorio();
    $outro = igrejaNoSetor($no['setor']);
    $hierarquia = app(ConsultarHierarquia::class);

    expect($hierarquia->filhos('central', $no['central']->id))
        ->toBe([['tipo' => 'regional', 'id' => $no['regional']->id, 'nome' => $no['regional']->nome]])
        ->and($hierarquia->filhos('regional', $no['regional']->id))
        ->toBe([['tipo' => 'setor', 'id' => $no['setor']->id, 'nome' => $no['setor']->nome]])
        ->and(collect($hierarquia->filhos('setor', $no['setor']->id))->pluck('id')->all())
        ->toEqualCanonicalizing([$no['igreja']->id, $outro['igreja']->id])
        ->and($hierarquia->filhos('igreja', $no['igreja']->id))
        ->toBe([['tipo' => 'ebi', 'id' => $no['ebi']->id, 'nome' => $no['ebi']->nome]])
        ->and($hierarquia->filhos('ebi', $no['ebi']->id))->toBe([]);
});

it('lista as igrejas da subarvore pelo vinculo vigente', function () {
    $no = arvoreRelatorio();
    $outraRegional = Regional::factory()->create(['central_nacional_id' => $no['central']->id]);
    $outroSetor = Setor::factory()->create(['regional_id' => $outraRegional->id]);
    $igrejaFora = igrejaNoSetor($outroSetor)['igreja'];
    $hierarquia = app(ConsultarHierarquia::class);

    expect($hierarquia->igrejasDaSubarvore('central', $no['central']->id))
        ->toEqualCanonicalizing([$no['igreja']->id, $igrejaFora->id])
        ->and($hierarquia->igrejasDaSubarvore('setor', $no['setor']->id))->toBe([$no['igreja']->id])
        ->and($hierarquia->igrejasDaSubarvore('igreja', $no['igreja']->id))->toBe([$no['igreja']->id])
        ->and($hierarquia->igrejasDaSubarvore('ebi', $no['ebi']->id))->toBe([]);
});

it('acompanha a transferencia de igreja para o setor vigente', function () {
    $no = arvoreRelatorio();
    $novoSetor = Setor::factory()->create(['regional_id' => $no['regional']->id]);
    $hierarquia = app(ConsultarHierarquia::class);

    IgrejaVinculacao::query()->where('igreja_id', $no['igreja']->id)->update(['fim' => '2026-11-01']);
    IgrejaVinculacao::query()->create([
        'igreja_id' => $no['igreja']->id,
        'setor_id' => $novoSetor->id,
        'inicio' => '2026-11-02',
        'fim' => null,
    ]);

    expect($hierarquia->igrejasDaSubarvore('setor', $no['setor']->id))->toBe([])
        ->and($hierarquia->igrejasDaSubarvore('setor', $novoSetor->id))->toBe([$no['igreja']->id]);
});

it('lista os ebis da subarvore, inclusive o proprio ne do ebi', function () {
    $no = arvoreRelatorio();
    $outra = igrejaNoSetor($no['setor']);
    $hierarquia = app(ConsultarHierarquia::class);

    expect($hierarquia->ebisDaSubarvore('igreja', $no['igreja']->id))->toBe([$no['ebi']->id])
        ->and($hierarquia->ebisDaSubarvore('setor', $no['setor']->id))
        ->toEqualCanonicalizing([$no['ebi']->id, $outra['ebi']->id])
        ->and($hierarquia->ebisDaSubarvore('ebi', $no['ebi']->id))->toBe([$no['ebi']->id]);
});

it('devolve o nome de cada no e o fuso das igrejas da subarvore', function () {
    $no = arvoreRelatorio(['timezone' => 'America/Manaus']);
    $organizacao = app(ConsultarOrganizacao::class);

    expect($organizacao->nomes('igreja', [$no['igreja']->id]))->toBe([$no['igreja']->id => $no['igreja']->nome])
        ->and($organizacao->nomes('regional', [$no['regional']->id]))->toBe([$no['regional']->id => $no['regional']->nome])
        ->and($organizacao->nomes('central', [$no['central']->id]))->toBe([$no['central']->id => $no['central']->nome])
        ->and($organizacao->fusosDasIgrejas([$no['igreja']->id]))->toBe([$no['igreja']->id => 'America/Manaus']);
});
