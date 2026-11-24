<?php

use App\Domain\Identidade\Contracts\AceitarConvite;
use App\Domain\Identidade\Models\Convite;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Identidade\Services\GuardaChefe;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use Database\Seeders\PapelSeeder;
use Mockery\MockInterface;

/**
 * Wizard: adm_igreja da igreja, EBI da mesma igreja e um convite de chefe
 * pendente (token conhecido). A igreja e o EBI sao opcionais para reusar o
 * cenario quando o teste precisa de dois convites no mesmo EBI.
 *
 * @return array{admin: Usuario, igreja: Igreja, ebi: Ebi, convite: Convite, token: string, auth: array<string, string>}
 */
function cenarioChefe(string $email = 'chefe@example.test', ?Ebi $ebi = null): array
{
    if ($ebi === null) {
        $igreja = Igreja::factory()->create();
        $ebi = Ebi::factory()->create(['igreja_id' => $igreja->id]);
    } else {
        $igreja = Igreja::query()->findOrFail($ebi->igreja_id);
    }
    // Um admin por historia: a segunda nomeacao nao pode vir do mesmo ator para
    // a assercao de autoridade nao se confundir com idempotencia.
    $admin = Usuario::factory()->create();
    $adminId = $admin->id;
    VinculoAcesso::query()->create([
        'usuario_id' => $adminId,
        'papel_id' => Papel::query()->where('codigo', 'adm_igreja')->value('id'),
        'tipo_escopo' => 'igreja',
        'escopo_id' => $igreja->id,
        'inicio' => now()->toDateString(),
        'fim' => null,
        'status' => 'ativo',
    ]);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $token = 'tok-'.hash('sha256', $email);
    $convite = Convite::query()->create([
        'email_normalizado' => $email,
        'papel_id' => Papel::query()->where('codigo', 'coordenadora_chefe')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $ebi->id,
        'token_hash' => hash('sha256', $token),
        'expira_em' => now()->addDays(7),
        'convidante_id' => $admin->id,
    ]);

    return compact('admin', 'igreja', 'ebi', 'convite', 'token', 'auth');
}

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

it('adm_igreja nomeia a chefe e a segunda nomeacao e recusada', function () {
    $c = cenarioChefe();
    $alvo = Usuario::factory()->create();

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => $alvo->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ], $c['auth'])->assertStatus(201);

    expect(VinculoAcesso::query()->where('chefe_de_ebi_id', $c['ebi']->id)->value('usuario_id'))
        ->toBe($alvo->id);

    $outro = Usuario::factory()->create();

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => $outro->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ], $c['auth'])->assertStatus(422)->assertJsonPath('code', 'chefe_ja_definida');

    expect(VinculoAcesso::query()->where('chefe_de_ebi_id', $c['ebi']->id)->count())->toBe(1);
});

it('a chefe so existe no escopo de EBI', function () {
    $c = cenarioChefe();
    $alvo = Usuario::factory()->create();

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => $alvo->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'igreja', 'escopo_id' => $c['igreja']->id,
    ], $c['auth'])->assertStatus(422)->assertJsonPath('code', 'escopo_invalido_para_papel');
});

it('a coordenadora nao nomeia chefe', function () {
    $c = cenarioChefe();
    $coord = criarUsuarioComPapel('coordenadora', 'ebi', $c['ebi']->id);
    $auth = ['Authorization' => 'Bearer '.$coord->createToken('t')->plainTextToken];

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => Usuario::factory()->create()->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ], $auth)->assertStatus(403);
});

it('reconceder o mesmo vinculo a mesma chefe nao e conflito', function () {
    $c = cenarioChefe();
    $alvo = Usuario::factory()->create();
    $corpo = [
        'usuario_id' => $alvo->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ];

    $this->postJson('/api/v1/access-grants', $corpo, $c['auth'])->assertStatus(201);
    $this->postJson('/api/v1/access-grants', $corpo, $c['auth'])->assertStatus(201);

    expect(VinculoAcesso::query()->where('chefe_de_ebi_id', $c['ebi']->id)->count())->toBe(1);
});

it('revogar libera a vaga e limpa a coluna', function () {
    $c = cenarioChefe();
    $alvo = Usuario::factory()->create();

    $vinculo = $this->postJson('/api/v1/access-grants', [
        'usuario_id' => $alvo->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ], $c['auth'])->assertStatus(201)->json();

    $this->deleteJson("/api/v1/access-grants/{$vinculo['id']}", [], $c['auth'])->assertNoContent();

    expect(VinculoAcesso::query()->whereNotNull('chefe_de_ebi_id')->count())->toBe(0);

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => Usuario::factory()->create()->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ], $c['auth'])->assertStatus(201);
});

it('convite de chefe e recusado com chefe vigente', function () {
    $c = cenarioChefe();
    criarUsuarioComPapel('coordenadora_chefe', 'ebi', $c['ebi']->id);

    $this->postJson('/api/v1/invitations', [
        'email' => 'nova@example.test', 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ], $c['auth'])->assertStatus(422)->assertJsonPath('code', 'chefe_ja_definida');
});

it('dois convites antes da nomeacao: o segundo aceite e recusado e nao cria conta', function () {
    $a = cenarioChefe('um@example.test');
    $b = cenarioChefe('dois@example.test', $a['ebi']);

    $this->postJson("/api/v1/invitations/{$a['convite']->id}/accept", [
        'token' => $a['token'], 'nome' => 'Um', 'password' => 'senha-bem-longa-123',
    ])->assertStatus(201);

    $this->postJson("/api/v1/invitations/{$b['convite']->id}/accept", [
        'token' => $b['token'], 'nome' => 'Dois', 'password' => 'senha-bem-longa-123',
    ])->assertStatus(422)->assertJsonPath('code', 'chefe_ja_definida');

    expect(Usuario::query()->where('email_normalizado', 'dois@example.test')->exists())->toBeFalse()
        ->and($b['convite']->fresh()->aceito_em)->toBeNull()
        ->and(VinculoAcesso::query()->where('chefe_de_ebi_id', $a['ebi']->id)->count())->toBe(1);

    // Revogar a chefe libera o convite pendente.
    $chefe = VinculoAcesso::query()->where('chefe_de_ebi_id', $a['ebi']->id)->firstOrFail();
    $this->deleteJson("/api/v1/access-grants/{$chefe->id}", [], $a['auth'])->assertNoContent();
    $chefe->refresh();

    expect($chefe->chefe_de_ebi_id)->toBeNull()
        ->and($chefe->fim)->not->toBeNull();

    $this->postJson("/api/v1/invitations/{$b['convite']->id}/accept", [
        'token' => $b['token'], 'nome' => 'Dois', 'password' => 'senha-bem-longa-123',
    ])->assertStatus(201);
});

it('sem checagem previa, o indice unico garante a vaga', function () {
    $c = cenarioChefe();
    criarUsuarioComPapel('coordenadora_chefe', 'ebi', $c['ebi']->id);

    $this->mock(GuardaChefe::class, function (MockInterface $mock): void {
        $mock->makePartial();
        $mock->shouldReceive('garantirVaga')->andReturnNull();
    });

    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => Usuario::factory()->create()->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ], $c['auth'])->assertStatus(422)->assertJsonPath('code', 'chefe_ja_definida');

    expect(VinculoAcesso::query()->where('chefe_de_ebi_id', $c['ebi']->id)->count())->toBe(1);
});

it('todo vinculo vigente de chefe aponta para o EBI', function () {
    $c = cenarioChefe();
    $this->postJson('/api/v1/access-grants', [
        'usuario_id' => Usuario::factory()->create()->id, 'papel' => 'coordenadora_chefe',
        'tipo_escopo' => 'ebi', 'escopo_id' => $c['ebi']->id,
    ], $c['auth'])->assertStatus(201);

    $semColuna = VinculoAcesso::query()
        ->whereNull('fim')
        ->where('papel_id', Papel::query()->where('codigo', 'coordenadora_chefe')->value('id'))
        ->whereNull('chefe_de_ebi_id')
        ->count();

    expect($semColuna)->toBe(0);
});

it('o aceite de convite da chefe passa pelo serviço', function () {
    $c = cenarioChefe();

    $conta = app(AceitarConvite::class)->aceitar($c['convite']->id, $c['token'], 'Chefe', 'senha-bem-longa-123');

    expect(VinculoAcesso::query()->where('usuario_id', $conta->id)->value('chefe_de_ebi_id'))->toBe($c['ebi']->id);
});
