<?php

use App\Support\EnvArquivo;

it('le segredo de arquivo quando o sufixo _FILE existe', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'sec');
    file_put_contents($tmp, "senha-do-arquivo\n");
    $_ENV['DB_PASSWORD_FILE'] = $_SERVER['DB_PASSWORD_FILE'] = $tmp;
    putenv("DB_PASSWORD_FILE=$tmp");

    expect(EnvArquivo::obter('DB_PASSWORD'))->toBe('senha-do-arquivo');

    unset($_ENV['DB_PASSWORD_FILE'], $_SERVER['DB_PASSWORD_FILE']);
    putenv('DB_PASSWORD_FILE');
    @unlink($tmp);
});

it('cai no env normal quando nao ha arquivo', function () {
    $original = $_ENV['DB_PASSWORD'] ?? null;
    $_ENV['DB_PASSWORD'] = $_SERVER['DB_PASSWORD'] = 'senha-direta';
    putenv('DB_PASSWORD=senha-direta');

    expect(EnvArquivo::obter('DB_PASSWORD'))->toBe('senha-direta');

    if ($original === null) {
        unset($_ENV['DB_PASSWORD'], $_SERVER['DB_PASSWORD']);
    } else {
        $_ENV['DB_PASSWORD'] = $_SERVER['DB_PASSWORD'] = $original;
    }
    putenv('DB_PASSWORD');
});
