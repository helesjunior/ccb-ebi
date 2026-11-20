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
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use App\Domain\Participantes\Models\Participante;
use Database\Seeders\PapelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Teste de unidade que depende do container (config(), etc.) mas nao do banco.
// Restrito a este arquivo: os demais testes de Unit seguem sem boot do app.
pest()->extend(TestCase::class)
    ->in('Unit/CofreChaveSensivelTest.php');

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

/**
 * Cria uma igreja + EBI e devolve `['igreja' => Igreja, 'ebi' => Ebi]`. Usado
 * pelos testes de escopo territorial de participante (a igreja de referencia do
 * participante resolve a EBI do ator).
 *
 * @return array{igreja: Igreja, ebi: Ebi}
 */
function igrejaComEbi(): array
{
    $igreja = Igreja::factory()->create();
    $ebi = Ebi::factory()->create(['igreja_id' => $igreja->id]);

    return ['igreja' => $igreja, 'ebi' => $ebi];
}

/**
 * Cria um participante com igreja de referencia definida.
 *
 * @param  array<string, mixed>  $atributos
 */
function participanteNaIgreja(Igreja $igreja, array $atributos = []): Participante
{
    return Participante::factory()->create([
        'igreja_referencia_id' => $igreja->id,
        ...$atributos,
    ]);
}

/**
 * Monta uma arvore Central > Regional > Setor > Igreja > EBI para os testes de
 * relatorio. Devolve os modelos criados.
 *
 * @param  array<string, mixed>  $atributos
 * @return array{central: CentralNacional, regional: Regional, setor: Setor, igreja: Igreja, ebi: Ebi}
 */
function arvoreRelatorio(array $atributos = []): array
{
    $central = CentralNacional::factory()->create();
    $regional = Regional::factory()->create(['central_nacional_id' => $central->id]);
    $setor = Setor::factory()->create(['regional_id' => $regional->id]);
    $igreja = Igreja::factory()->create($atributos);
    IgrejaVinculacao::query()->create([
        'igreja_id' => $igreja->id,
        'setor_id' => $setor->id,
        'inicio' => '2020-01-01',
        'fim' => null,
    ]);
    $ebi = Ebi::factory()->create(['igreja_id' => $igreja->id]);

    return compact('central', 'regional', 'setor', 'igreja', 'ebi');
}

/**
 * Cria mais uma igreja dentro do setor existente (com o EBI dela).
 *
 * @param  array<string, mixed>  $atributos
 * @return array{igreja: Igreja, ebi: Ebi}
 */
function igrejaNoSetor(Setor $setor, array $atributos = []): array
{
    $igreja = Igreja::factory()->create($atributos);
    IgrejaVinculacao::query()->create([
        'igreja_id' => $igreja->id,
        'setor_id' => $setor->id,
        'inicio' => '2020-01-01',
        'fim' => null,
    ]);

    return ['igreja' => $igreja, 'ebi' => Ebi::factory()->create(['igreja_id' => $igreja->id])];
}
