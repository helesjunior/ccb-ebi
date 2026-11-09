<?php

/**
 * Fronteiras do monolito modular (ADR-0002).
 *
 * Regras verificadas:
 * 1. Codigo fora de um modulo (ex.: app/Http) nao pode usar App\Domain\<X>\Models\*.
 * 2. Um modulo so pode depender da superficie publica de outro (Contracts/),
 *    nunca das classes internas.
 *
 * A checagem e feita pelo scanner abaixo, que le os `use` de cada arquivo.
 * Os nomes de tabela/modelo ficam dentro do modulo dono; a superficie publica
 * de um modulo sao suas classes em Contracts/.
 */
it('fronteiras de modulo respeitadas', function () {
    $app = dirname(__DIR__, 2).'/app';

    $moduleDirs = glob("$app/Domain/*", GLOB_ONLYDIR);
    expect($moduleDirs)->not->toBeEmpty();

    // PHP glob nao faz ** recursivo de forma confiavel; usar RecursiveIterator.
    $phpFiles = function (string $dir): array {
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }

        return $out;
    };

    $files = [];
    foreach ($phpFiles("$app/Http") as $f) {
        $files[] = [$f, null]; // null = nao pertence a modulo
    }
    foreach ($moduleDirs as $dir) {
        $modulo = basename($dir);
        foreach ($phpFiles($dir) as $f) {
            $files[] = [$f, $modulo];
        }
    }

    $violacoes = [];
    foreach ($files as [$file, $moduloDono]) {
        $code = file_get_contents($file);
        preg_match_all('/use App\\\\Domain\\\\([A-Za-z]+)\\\\(?!Contracts)([A-Za-z\\\\]+);/', $code, $m);
        foreach ($m[1] as $i => $moduloUsado) {
            $resto = $m[2][$i];
            if ($moduloDono === null && str_starts_with($resto, 'Models\\')) {
                $violacoes[] = "$file usa $moduloUsado\\$resto (app/Http nao deve ler modelo de modulo)";
            }
            if ($moduloDono !== null && $moduloUsado !== $moduloDono) {
                $violacoes[] = "$file usa $moduloUsado\\$resto (modulo $moduloDono so pode usar Contracts de outro modulo)";
            }
        }
    }

    expect($violacoes)->toBeEmpty(implode("\n", $violacoes));
});

arch('modulos nao usam App\Models compartilhado')
    ->expect('App\Domain')
    ->not->toUse(['App\Models'])
    ->ignoring('App\Domain');
