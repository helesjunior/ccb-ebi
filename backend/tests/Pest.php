<?php

// Ambiente de teste determinístico: o container recebe CACHE_STORE/QUEUE/SESSION
// via env_file (.env) como variáveis de ambiente reais, e o Dotenv do Laravel é
// imutável — o <env force> do phpunit.xml não sobrescreve chaves já presentes no
// ambiente. Forçamos aqui, antes de qualquer boot, para que os testes não
// compartilhem o Redis (o rate limiter acumulava entre execuções e virava 429).
foreach ([
    'APP_ENV' => 'testing',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
    'MAIL_MAILER' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
] as $chave => $valor) {
    putenv("{$chave}={$valor}");
    $_ENV[$chave] = $valor;
    $_SERVER[$chave] = $valor;
}

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use Database\Seeders\PapelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Limpa o cache antes de cada teste para isolar o rate limiter
// (o store em memória persiste entre testes no mesmo processo).
beforeEach(function () {
    Cache::clear();
})->in('Feature');

/**
 * Cria um usuario com um vinculo de acesso vigente (papel + escopo) e devolve
 * o token Sanctum pronto para uso. Helper compartilhado pelas features de
 * identidade e autorizacao.
 */
function criarUsuarioComPapel(
    string $papelCodigo,
    string $tipoEscopo = 'central',
    ?string $escopoId = null,
    string $senha = 'senha-bem-longa-123',
): Usuario {
    if (Papel::query()->where('codigo', $papelCodigo)->doesntExist()) {
        test()->seed(PapelSeeder::class);
    }

    $usuario = Usuario::factory()->create([
        'senha' => Hash::make($senha),
        'email_verificado_em' => now(),
    ]);

    VinculoAcesso::factory()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', $papelCodigo)->value('id'),
        'tipo_escopo' => $tipoEscopo,
        'escopo_id' => $escopoId ?? (string) Str::ulid(),
    ]);

    return $usuario;
}
