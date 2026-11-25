<?php

use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->autorizar = app(AutorizarOperacao::class);
});

it('papeis por funcao fica no modelo da escala com os papeis exatos', function () {
    expect(EscalaMembro::PAPEIS_POR_FUNCAO)->toBe([
        'coordenadora' => ['coordenadora', 'coordenadora_chefe'],
        'auxiliar' => ['auxiliar', 'coordenadora', 'coordenadora_chefe'],
    ]);
});

it('escala e avaliador usam a constante unica', function () {
    $arquivos = collect(File::allFiles(app_path('Domain/Escalas')))
        ->filter(fn ($arquivo): bool => $arquivo->getExtension() === 'php')
        ->map(fn ($arquivo): string => $arquivo->getPathname());

    $definicoes = $arquivos->filter(function (string $caminho): bool {
        $conteudo = (string) File::get($caminho);

        // So a constante do modelo pode DECLARAR o mapa; os demais o consomem.
        return preg_match('/PAPEIS_POR_FUNCAO\s*=/', $conteudo) === 1
            && ! str_contains($caminho, 'EscalaMembro.php');
    })->values()->all();

    expect($definicoes)->toBe([])
        ->and(File::get(app_path('Domain/Escalas/Services/GerenciadorEscala.php')))
        ->toContain('EscalaMembro::PAPEIS_POR_FUNCAO')
        ->and(File::get(app_path('Domain/Escalas/Services/AvaliadorEquipe.php')))
        ->toContain('EscalaMembro::PAPEIS_POR_FUNCAO');
});

it('equipe.gerenciar esta na matriz para coordenadora, chefe e adm_igreja', function () {
    $coordenadora = new ContextoAtuacao(papelCodigo: 'coordenadora', tipoEscopo: 'ebi', escopoId: 'e1');
    $chefe = new ContextoAtuacao(papelCodigo: 'coordenadora_chefe', tipoEscopo: 'ebi', escopoId: 'e1');
    $admIgreja = new ContextoAtuacao(papelCodigo: 'adm_igreja', tipoEscopo: 'igreja', escopoId: 'i1');

    expect(Operacoes::EQUIPE_GERENCIAR)->toBe('equipe.gerenciar')
        ->and($this->autorizar->permite($coordenadora, Operacoes::EQUIPE_GERENCIAR))->toBeTrue()
        ->and($this->autorizar->permite($chefe, Operacoes::EQUIPE_GERENCIAR))->toBeTrue()
        ->and($this->autorizar->permite($admIgreja, Operacoes::EQUIPE_GERENCIAR))->toBeTrue();
});

it('equipe.gerenciar nao alcanca auxiliar nem ministerios', function () {
    foreach (['auxiliar', 'ministerio_local', 'ministerio_central', 'ministerio_regional', 'ministerio_setorial', 'adm_setor', 'adm_regional', 'responsavel'] as $papel) {
        expect($this->autorizar->permite(new ContextoAtuacao(papelCodigo: $papel, tipoEscopo: 'ebi', escopoId: 'e1'), Operacoes::EQUIPE_GERENCIAR))
            ->toBeFalse("papel {$papel} nao deve gerenciar equipe");
    }
});
