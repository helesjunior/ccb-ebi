<?php

// Suporte aos testes de concorrencia (tests/Concorrencia): rodam so contra
// PostgreSQL com conexoes independentes, porque o SQLite dos testes comuns nao
// aplica `lockForUpdate`. Sem TESTE_PGSQL=1 o grupo inteiro e pulado.
//
// As variaveis do phpunit.xml tem `force="true"` e o bloco abaixo do arquivo
// principal de configuracao as fixa por putenv; por isso este arquivo precisa
// ser carregado DEPOIS dele (ver tests/Pest.php).
if (getenv('TESTE_PGSQL') !== '1') {
    return;
}

foreach ([
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => getenv('DB_HOST') ?: 'postgres',
    'DB_PORT' => getenv('DB_PORT') ?: '5432',
    'DB_DATABASE' => 'ebi_teste',
    'DB_USERNAME' => getenv('DB_USERNAME') ?: 'ebi',
    'DB_PASSWORD' => getenv('DB_PASSWORD') ?: 'ebi',
    'DB_URL' => '',
] as $chave => $valor) {
    putenv("{$chave}={$valor}");
    $_ENV[$chave] = $valor;
    $_SERVER[$chave] = $valor;
}
