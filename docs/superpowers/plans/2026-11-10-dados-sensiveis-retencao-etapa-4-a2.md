# Dados sensíveis e retenção (Etapa 4 · A2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar a sub-etapa A2 da E05 — módulo segregado de dados sensíveis com chave própria (separada da `APP_KEY`), leitura sempre auditada, e motor de retenção configurável e desligado por padrão.

**Architecture:** Novo módulo `App\Domain\DadosSensiveis` (irmão de Identidade/Organizacao/Privacidade/Auditoria), com chave lida só dentro do módulo, cifrando com AES-256-GCM. Leitura passa por contrato que grava evento na trilha append-only existente. Retenção é config (`config/privacidade.php`) + serviço idempotente acionado por comando artisan, desligado por padrão.

**Tech Stack:** Laravel 13 · PHP 8.4 (Docker: `docker compose run --rm app <cmd>`, a partir do repo principal `C:/Users/arl0001/Documents/Projetos/ccb-ebi`) · Pest · PHPStan/Larastan · Pint · PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-11-10-dados-sensiveis-retencao-etapa-4-a2-design.md`

## Global Constraints

- **PHP sempre via Docker**, a partir do repo principal: `docker compose run --rm app <cmd>`. Nunca PHP do host. Após mexer no backend, se usar containers de longa duração: `docker compose restart app horizon scheduler`.
- **Toda resposta e texto de UI em português.**
- **Fronteiras de módulo (ADR-0002)**, verificadas por `tests/Arch/ModularBoundariesTest.php`: `app/Http` **não** importa `App\Domain\*\Models`; entre módulos, só `App\Domain\<X>\Contracts`; `App\Support\*` é compartilhado.
- **A chave de dados sensíveis nunca é a `APP_KEY`** e nunca há fallback implícito para ela (ADR-0006). Ausente/inválida ⇒ falha explícita.
- **Nenhum conteúdo sensível em log**; só metadados minimizados (`tipo`, `finalidade`, ids, contagens).
- **Retenção desligada por padrão** (`config('privacidade.retencao.ativa') === false`).
- **Prazos de retenção** (ADR-0019, provisórios): participante 24 meses, feedback 24 meses, logs de acesso 6 meses, auditoria/evidência de consentimento 60 meses, laudo: apagado ao fim da exceção (por registro), lista offline 7 dias. A A2 usa os que fazem sentido para o que existe; o mapa é configurável.
- **Gate final:** os 4 verdes — `php artisan test`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, `python tools/check-endpoints.py` (ou o caminho do script no repo). Este último **não muda** na A2 (sem mudança de contrato público).
- **Commits** pequenos, um por task, mensagem em português no padrão do repo (`feat:`, `test:`, `docs:`, `fix:`).

## Review Focus

Cinco classes de entrada/condição que a spec implica mas nenhum teste da spec fixa; cada uma ganha teste na task dona:

1. **Chave ausente ou malformada** no momento de cifrar/decifrar — o serviço deve **falhar** (exceção clara), nunca usar `APP_KEY` nem devolver texto corrompido. → Task 3, 4.
2. **Leitura sem ator** (`atorId` vazio) — deve falhar (não existe leitura anônima de dado sensível); a trilha não pode receber evento com `ator_id` nulo. → Task 4.
3. **Texto cifrado exposto**: o conteúdo em claro não pode aparecer na coluna `conteudo_cifrado` nem no `json_encode` do modelo em log. → Task 3.
4. **Expurgo rodado duas vezes** (idempotência): o segundo expurgo não reaudita nem altera registro já `expurgado_em` preenchido. → Task 5.
5. **`conteudo_cifrado` e `referencia_segura` juntos ou ambos vazios**: a gravação deve rejeitar (não é válido ter os dois nem nenhum). → Task 3.

---

### Task 1: Módulo `DadosSensiveis` — esqueleto, chave própria e config

**Files:**
- Create: `backend/app/Domain/DadosSensiveis/README.md`
- Create: `backend/app/Domain/DadosSensiveis/Support/CofreChaveSensivel.php`
- Create: `backend/config/privacidade.php`
- Modify: `backend/.env.example` (acrescentar `SENSITIVE_DATA_KEY=`)
- Test: `backend/tests/Unit/CofreChaveSensivelTest.php`

**Interfaces:**
- Consumes: `App\Support\EnvArquivo::obter(string $chave): ?string` (existe).
- Produces:
  - `App\Domain\DadosSensiveis\Support\CofreChaveSensivel` com `public static function encrypter(): \Illuminate\Encryption\Encrypter` (lança `RuntimeException` se a chave faltar/inválida).
  - `config('privacidade.chave')` — a chave crua (string|null) lida de `SENSITIVE_DATA_KEY`/`_FILE`.
  - `config('privacidade.retencao.ativa')` (bool, default `false`) e `config('privacidade.retencao.prazos')` (array `tipo => meses`).

- [ ] **Step 1: Write the failing test**

Crie `backend/tests/Unit/CofreChaveSensivelTest.php`:

```php
<?php

use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use Illuminate\Encryption\Encrypter;

it('monta um encrypter AES-256-GCM a partir da chave configurada', function () {
    $enc = CofreChaveSensivel::encrypter();
    expect($enc)->toBeInstanceOf(Encrypter::class);
    expect($enc->getCipher())->toBe('aes-256-gcm');

    $cifrado = $enc->encryptString('segredo infantil');
    expect($cifrado)->not->toContain('segredo infantil');
    expect($enc->decryptString($cifrado))->toBe('segredo infantil');
});

it('falha explicitamente quando a chave nao esta configurada', function () {
    config(['privacidade.chave' => null]);
    expect(fn () => CofreChaveSensivel::encrypter())
        ->toThrow(RuntimeException::class);
});

it('nao usa a APP_KEY como fallback', function () {
    config(['privacidade.chave' => null]);
    // app.key existe (APP_KEY), mas o cofre nao pode cair nela.
    expect(config('app.key'))->not->toBeNull();
    expect(fn () => CofreChaveSensivel::encrypter())->toThrow(RuntimeException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app php artisan test tests/Unit/CofreChaveSensivelTest.php`
Expected: FAIL — classe `CofreChaveSensivel` não existe / config ausente.

- [ ] **Step 3: Implement**

Crie `backend/app/Domain/DadosSensiveis/README.md`:

```markdown
# Dados sensíveis e retenção (E05/A2). Expoe: `App\Domain\DadosSensiveis\Contracts\*`.
```

Crie `backend/config/privacidade.php`:

```php
<?php

use App\Support\EnvArquivo;

return [
    // Chave de criptografia dos dados sensíveis, SEPARADA da APP_KEY (ADR-0006).
    // Nunca há fallback para APP_KEY: se faltar, a operação de cifra falha.
    'chave' => EnvArquivo::obter('SENSITIVE_DATA_KEY'),

    'retencao' => [
        // Rotina de descarte/anonimização DESLIGADA até o DPO aprovar (RNF-07).
        'ativa' => filter_var(EnvArquivo::obter('PRIVACIDADE_RETENCAO_ATIVA') ?? 'false', FILTER_VALIDATE_BOOL),

        // Prazo por tipo de dado, em meses (padrão provisório do ADR-0019).
        'prazos' => [
            'laudo' => 0,          // apagado ao fim da exceção (por registro; 0 = só com prazo_retencao explícito)
            'diagnostico' => 0,
            'saude' => 0,
        ],
    ],
];
```

Crie `backend/app/Domain/DadosSensiveis/Support/CofreChaveSensivel.php`:

```php
<?php

namespace App\Domain\DadosSensiveis\Support;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Chave de criptografia dos dados sensíveis, isolada da APP_KEY (ADR-0006).
 * Só este módulo lê a chave. Sem fallback: chave ausente/inválida é erro.
 */
final class CofreChaveSensivel
{
    public static function encrypter(): Encrypter
    {
        $chave = config('privacidade.chave');

        if (! is_string($chave) || $chave === '') {
            throw new RuntimeException('Chave de dados sensíveis ausente (SENSITIVE_DATA_KEY).');
        }

        $binaria = base64_decode(substr($chave, 7), true); // "base64:" prefix
        if ($binaria === false || strlen($binaria) !== 32) {
            throw new RuntimeException('Chave de dados sensíveis inválida: esperado base64 de 32 bytes.');
        }

        return new Encrypter($binaria, 'aes-256-gcm');
    }
}
```

Acrescente a `backend/.env.example` (após a seção de chaves/crypto, se houver; senão junto ao topo):

```
# Chave de criptografia dos dados sensíveis (separada da APP_KEY). Gere com:
# php artisan key:generate --show  (ou openssl rand -base64 32)
SENSITIVE_DATA_KEY=
```

> No ambiente local, gere e preencha: `docker compose run --rm app php artisan key:generate --show` e cole o valor em `backend/.env` como `SENSITIVE_DATA_KEY=base64:...`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app php artisan test tests/Unit/CofreChaveSensivelTest.php`
Expected: PASS (os 3). Caso a chave não esteja no `.env`, ajuste o teste 1 para injetar `config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))])` antes — mantendo os testes 2 e 3 como estão.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/DadosSensiveis backend/config/privacidade.php backend/.env.example backend/tests/Unit/CofreChaveSensivelTest.php
git commit -m "feat: modulo de dados sensiveis com chave propria isolada da APP_KEY"
```

---

### Task 2: Migration, modelo e factory de `dado_sensivel_participante`

**Files:**
- Create: `backend/database/migrations/2026_11_10_120001_create_dado_sensivel_participante_table.php`
- Create: `backend/app/Domain/DadosSensiveis/Models/DadoSensivelParticipante.php`
- Create: `backend/app/Domain/DadosSensiveis/Database/Factories/DadoSensivelParticipanteFactory.php`
- Test: `backend/tests/Feature/Privacidade/DadoSensivelSchemaTest.php`

**Interfaces:**
- Consumes: `App\Domain\Identidade\Models\Usuario` apenas na factory (para `criado_por`), se necessário — senão, nada.
- Produces:
  - Modelo `App\Domain\DadosSensiveis\Models\DadoSensivelParticipante` (tabela `dado_sensivel_participante`).
  - Scope `scopeExpiraveis(\Illuminate\Database\Eloquent\Builder $q, \DateTimeInterface $ref): \Illuminate\Database\Eloquent\Builder` — `prazo_retencao <= ref` e `expurgado_em` nulo.
  - Colunas: `id`, `participante_id` (nullable), `tipo`, `conteudo_cifrado` (nullable), `referencia_segura` (nullable), `finalidade`, `base_legal`, `controle_acesso`, `prazo_retencao` (date, nullable), `expurgado_em` (nullable), `timestamps`.
  - Factory `DadoSensivelParticipanteFactory`.

- [ ] **Step 1: Write the failing test**

Crie `backend/tests/Feature/Privacidade/DadoSensivelSchemaTest.php`:

```php
<?php

use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use Illuminate\Support\Facades\Schema;

it('cria a tabela dado_sensivel_participante com as colunas esperadas', function () {
    expect(Schema::hasTable('dado_sensivel_participante'))->toBeTrue();
    foreach (['id', 'participante_id', 'tipo', 'conteudo_cifrado', 'referencia_segura',
              'finalidade', 'base_legal', 'controle_acesso', 'prazo_retencao', 'expurgado_em'] as $col) {
        expect(Schema::hasColumn('dado_sensivel_participante', $col))->toBeTrue();
    }
});

it('scope expiraveis so traz prazos vencidos e nao expurgados', function () {
    $vencido = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'expurgado_em' => null,
    ]);
    $futuro = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->addYear(),
        'expurgado_em' => null,
    ]);
    $jaExpurgado = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'expurgado_em' => now(),
    ]);

    $ids = DadoSensivelParticipante::query()
        ->expiraveis(now())
        ->pluck('id');

    expect($ids)->toContain($vencido->id);
    expect($ids)->not->toContain($futuro->id, $jaExpurgado->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app php artisan test tests/Feature/Privacidade/DadoSensivelSchemaTest.php`
Expected: FAIL — tabela/modelo/factory inexistentes.

- [ ] **Step 3: Implement**

Migration `2026_11_10_120001_create_dado_sensivel_participante_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dado sensível do participante (E05/A2): conteudo_cifrado (chave própria) OU
 * referencia_segura (bucket próprio, E08). participante_id sem FK real até o E03
 * (mesma decisão de manifestacao_privacidade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dado_sensivel_participante', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('participante_id')->nullable();  // FK real no E03
            $table->string('tipo');
            $table->text('conteudo_cifrado')->nullable();
            $table->string('referencia_segura')->nullable();
            $table->string('finalidade');
            $table->string('base_legal');
            $table->string('controle_acesso');
            $table->date('prazo_retencao')->nullable();
            $table->timestamp('expurgado_em')->nullable();
            $table->timestamps();

            $table->index(['participante_id', 'tipo']);
            $table->index('prazo_retencao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dado_sensivel_participante');
    }
};
```

Modelo `backend/app/Domain/DadosSensiveis/Models/DadoSensivelParticipante.php`:

```php
<?php

namespace App\Domain\DadosSensiveis\Models;

use App\Domain\DadosSensiveis\Database\Factories\DadoSensivelParticipanteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DadoSensivelParticipante extends Model
{
    /** @use HasFactory<DadoSensivelParticipanteFactory> */
    use HasFactory;

    protected $table = 'dado_sensivel_participante';

    protected $guarded = [];

    protected $casts = [
        'prazo_retencao' => 'date',
        'expurgado_em' => 'datetime',
    ];

    protected $hidden = ['conteudo_cifrado']; // nunca serializar conteúdo sensível

    protected static function newFactory(): DadoSensivelParticipanteFactory
    {
        return DadoSensivelParticipanteFactory::new();
    }

    /** @param  Builder<DadoSensivelParticipante>  $q */
    public function scopeExpiraveis(Builder $q, \DateTimeInterface $ref): Builder
    {
        return $q->whereNotNull('prazo_retencao')
            ->where('prazo_retencao', '<=', $ref)
            ->whereNull('expurgado_em');
    }
}
```

Factory `backend/app/Domain/DadosSensiveis/Database/Factories/DadoSensivelParticipanteFactory.php`:

```php
<?php

namespace App\Domain\DadosSensiveis\Database\Factories;

use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DadoSensivelParticipante> */
class DadoSensivelParticipanteFactory extends Factory
{
    protected $model = DadoSensivelParticipante::class;

    public function definition(): array
    {
        return [
            'participante_id' => null,
            'tipo' => 'laudo',
            'conteudo_cifrado' => 'cifrado-de-teste',
            'referencia_segura' => null,
            'finalidade' => 'excecao_etaria',
            'base_legal' => 'consentimento',
            'controle_acesso' => 'coordenador',
            'prazo_retencao' => null,
            'expurgado_em' => null,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app php artisan test tests/Feature/Privacidade/DadoSensivelSchemaTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_11_10_120001_create_dado_sensivel_participante_table.php backend/app/Domain/DadosSensiveis/Models backend/app/Domain/DadosSensiveis/Database
git commit -m "feat: tabela, modelo e factory de dado sensivel do participante"
```

---

### Task 3: `ArmazenarDadoSensivel` — cifra com chave própria

**Files:**
- Create: `backend/app/Domain/DadosSensiveis/Contracts/ArmazenarDadoSensivel.php`
- Create: `backend/app/Domain/DadosSensiveis/Services/ArmazenadorDadoSensivel.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Privacidade/ArmazenarDadoSensivelTest.php`

**Interfaces:**
- Consumes: `CofreChaveSensivel::encrypter()` (Task 1); `DadoSensivelParticipante` (Task 2).
- Produces: contrato `ArmazenarDadoSensivel` com
  `armazenar(?string $participanteId, string $tipo, string $conteudo, ?string $referenciaSegura, string $finalidade, string $baseLegal, string $controleAcesso, ?\DateTimeInterface $prazoRetencao): DadoSensivelParticipante`.
  Regras: exatamente um de `conteudo`/`referenciaSegura` preenchido; quando `conteudo` preenchido, cifra-o e grava em `conteudo_cifrado`.

- [ ] **Step 1: Write the failing test**

Crie `backend/tests/Feature/Privacidade/ArmazenarDadoSensivelTest.php`:

```php
<?php

use App\Domain\DadosSensiveis\Contracts\ArmazenarDadoSensivel;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use App\Support\ProblemException;

beforeEach(function () {
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);
});

it('cifra o conteudo em repouso (texto em claro nao aparece na coluna)', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'CID F90.0', null, 'excecao_etaria', 'consentimento', 'coordenador', null,
    );

    expect($d->conteudo_cifrado)->not->toContain('CID F90.0');
    expect(CofreChaveSensivel::encrypter()->decryptString($d->conteudo_cifrado))->toBe('CID F90.0');
});

it('aceita referencia segura sem cifrar conteudo', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', '', 's3://laudos/abc.pdf', 'excecao_etaria', 'consentimento', 'coordenador', null,
    );

    expect($d->conteudo_cifrado)->toBeNull();
    expect($d->referencia_segura)->toBe('s3://laudos/abc.pdf');
});

it('rejeita conteudo e referencia segura juntos', function () {
    expect(fn () => app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'texto', 's3://x', 'f', 'b', 'c', null,
    ))->toThrow(ProblemException::class);
});

it('rejeita conteudo e referencia segura ambos vazios', function () {
    expect(fn () => app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', '', null, 'f', 'b', 'c', null,
    ))->toThrow(ProblemException::class);
});

it('nao expoe o conteudo cifrado ao serializar o modelo', function () {
    $d = DadoSensivelParticipante::factory()->create();
    expect($d->toArray())->not->toHaveKey('conteudo_cifrado');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app php artisan test tests/Feature/Privacidade/ArmazenarDadoSensivelTest.php`
Expected: FAIL — contrato/serviço inexistentes.

- [ ] **Step 3: Implement**

Contrato `Contracts/ArmazenarDadoSensivel.php` (assinatura como em **Produces**).

Serviço `Services/ArmazenadorDadoSensivel.php`:

```php
<?php

namespace App\Domain\DadosSensiveis\Services;

use App\Domain\DadosSensiveis\Contracts\ArmazenarDadoSensivel;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use App\Support\ProblemException;

class ArmazenadorDadoSensivel implements ArmazenarDadoSensivel
{
    public function armazenar(
        ?string $participanteId,
        string $tipo,
        string $conteudo,
        ?string $referenciaSegura,
        string $finalidade,
        string $baseLegal,
        string $controleAcesso,
        ?\DateTimeInterface $prazoRetencao,
    ): DadoSensivelParticipante {
        $temConteudo = $conteudo !== '';
        $temReferencia = is_string($referenciaSegura) && $referenciaSegura !== '';

        // Exatamente um dos dois (nem ambos, nem nenhum).
        if ($temConteudo === $temReferencia) {
            throw new ProblemException(
                'dado_sensivel_invalido',
                'Conteúdo inválido',
                422,
                'Informe conteúdo ou referência segura, nunca os dois nem nenhum.',
            );
        }

        return DadoSensivelParticipante::query()->create([
            'participante_id' => $participanteId,
            'tipo' => $tipo,
            'conteudo_cifrado' => $temConteudo ? CofreChaveSensivel::encrypter()->encryptString($conteudo) : null,
            'referencia_segura' => $temReferencia ? $referenciaSegura : null,
            'finalidade' => $finalidade,
            'base_legal' => $baseLegal,
            'controle_acesso' => $controleAcesso,
            'prazo_retencao' => $prazoRetencao,
        ]);
    }
}
```

> O `ProblemException` do projeto é construído com argumentos posicionais `(code, title, status, detail)` — como nos demais serviços.

Binding em `DomainServiceProvider::register()`:

```php
$this->app->bind(ArmazenarDadoSensivel::class, ArmazenadorDadoSensivel::class);
```
(com os `use` de `App\Domain\DadosSensiveis\Contracts\ArmazenarDadoSensivel` e `App\Domain\DadosSensiveis\Services\ArmazenadorDadoSensivel`)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app php artisan test tests/Feature/Privacidade/ArmazenarDadoSensivelTest.php`
Expected: PASS (os 5).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/DadosSensiveis backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Privacidade/ArmazenarDadoSensivelTest.php
git commit -m "feat: armazenar dado sensivel cifrado com chave propria"
```

---

### Task 4: `LerDadoSensivel` — decifra, controla acesso e audita

**Files:**
- Create: `backend/app/Domain/DadosSensiveis/Contracts/LerDadoSensivel.php`
- Create: `backend/app/Domain/DadosSensiveis/Services/LeitorDadoSensivel.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Privacidade/LerDadoSensivelTest.php`

**Interfaces:**
- Consumes: `CofreChaveSensivel::encrypter()`; `DadoSensivelParticipante`; `App\Domain\Auditoria\Contracts\RegistrarEvento::registrar(array $evento): EventoAuditoria`.
- Produces: contrato `LerDadoSensivel` com
  `ler(string $id, string $atorId, string $papelAtuante, ?string $escopo = null): string`.
  Regras: `atorId` vazio ⇒ `RuntimeException`; registro inexistente ⇒ `ProblemException` (404); `controle_acesso` sem o `papelAtuante` ⇒ `ProblemException` (403); registro só com `referencia_segura` ⇒ devolve a referência (não decifra); sempre grava evento `acao=dado_sensivel.ler`.

- [ ] **Step 1: Write the failing test**

Crie `backend/tests/Feature/Privacidade/LerDadoSensivelTest.php`:

```php
<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\DadosSensiveis\Contracts\ArmazenarDadoSensivel;
use App\Domain\DadosSensiveis\Contracts\LerDadoSensivel;
use App\Support\ProblemException;

beforeEach(function () {
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);
});

it('decifra e grava evento de auditoria com ator e finalidade', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'CID F90.0', null, 'excecao_etaria', 'consentimento', 'coordenador', null,
    );

    $texto = app(LerDadoSensivel::class)->ler($d->id, 'usuario-1', 'coordenador', 'igreja:1');

    expect($texto)->toBe('CID F90.0');

    $evento = EventoAuditoria::query()->where('acao', 'dado_sensivel.ler')->latest('sequencia')->first();
    expect($evento)->not->toBeNull();
    expect($evento->ator_id)->toBe('usuario-1');
    expect($evento->recurso)->toBe('dado_sensivel_participante');
    expect($evento->metadados_minimizados['finalidade'])->toBe('excecao_etaria');
});

it('falha ao ler sem ator (nao existe leitura anonima)', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'x', null, 'f', 'b', 'coordenador', null,
    );
    expect(fn () => app(LerDadoSensivel::class)->ler($d->id, '', 'coordenador'))
        ->toThrow(RuntimeException::class);
});

it('nega leitura a papel fora do controle de acesso', function () {
    $d = app(ArmazenarDadoSensivel::class)->armazenar(
        null, 'laudo', 'x', null, 'f', 'b', 'coordenador', null,
    );
    expect(fn () => app(LerDadoSensivel::class)->ler($d->id, 'u2', 'adm_setor'))
        ->toThrow(ProblemException::class);
});

it('404 para dado sensivel inexistente', function () {
    expect(fn () => app(LerDadoSensivel::class)->ler('nao-existe', 'u1', 'coordenador'))
        ->toThrow(ProblemException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app php artisan test tests/Feature/Privacidade/LerDadoSensivelTest.php`
Expected: FAIL — contrato/serviço inexistentes.

- [ ] **Step 3: Implement**

Contrato `Contracts/LerDadoSensivel.php` (assinatura como em **Produces**).

Serviço `Services/LeitorDadoSensivel.php` — injeta `RegistrarEvento` no construtor:

```php
<?php

namespace App\Domain\DadosSensiveis\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\DadosSensiveis\Contracts\LerDadoSensivel;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use App\Domain\DadosSensiveis\Support\CofreChaveSensivel;
use App\Support\ProblemException;
use RuntimeException;

class LeitorDadoSensivel implements LerDadoSensivel
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function ler(string $id, string $atorId, string $papelAtuante, ?string $escopo = null): string
    {
        if (trim($atorId) === '') {
            throw new RuntimeException('Leitura de dado sensível exige ator identificado.');
        }

        $dado = DadoSensivelParticipante::query()->find($id);

        if ($dado === null) {
            throw new ProblemException('dado_sensivel_inexistente', 'Não encontrado', 404, 'Dado sensível não encontrado.');
        }

        if (! $this->permite($dado->controle_acesso, $papelAtuante)) {
            throw new ProblemException('dado_sensivel_negado', 'Acesso negado', 403, 'Acesso ao dado sensível negado.');
        }

        $this->auditoria->registrar([
            'acao' => 'dado_sensivel.ler',
            'recurso' => 'dado_sensivel_participante',
            'ator_id' => $atorId,
            'origem' => 'usuario',
            'escopo' => $escopo,
            'metadados_minimizados' => [
                'tipo' => $dado->tipo,
                'finalidade' => $dado->finalidade,
                'dado_id' => $dado->id,
            ],
        ]);

        if ($dado->conteudo_cifrado !== null) {
            return CofreChaveSensivel::encrypter()->decryptString($dado->conteudo_cifrado);
        }

        return (string) $dado->referencia_segura;
    }

    private function permite(string $controleAcesso, string $papel): bool
    {
        // Formato: lista de papéis separados por vírgula; "*" libera todos.
        $permitidos = array_map('trim', explode(',', $controleAcesso));

        return in_array('*', $permitidos, true) || in_array($papel, $permitidos, true);
    }
}
```

> O `ProblemException` usa argumentos posicionais `(code, title, status, detail)`, como no Task 3. Binding do contrato em `DomainServiceProvider`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app php artisan test tests/Feature/Privacidade/LerDadoSensivelTest.php`
Expected: PASS (os 4).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/DadosSensiveis backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Privacidade/LerDadoSensivelTest.php
git commit -m "feat: leitura de dado sensivel com controle de acesso e auditoria"
```

---

### Task 5: `ExpurgarRetencao` + comando `privacidade:expurgar`

**Files:**
- Create: `backend/app/Domain/DadosSensiveis/Contracts/ExpurgarRetencao.php`
- Create: `backend/app/Domain/DadosSensiveis/Services/ExpurgadorRetencao.php`
- Create: `backend/app/Domain/DadosSensiveis/Support/ResultadoRetencao.php`
- Create: `backend/app/Console/Commands/PrivacidadeExpurgar.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Privacidade/RetencaoTest.php`

**Interfaces:**
- Consumes: `DadoSensivelParticipante::expiraveis()` (Task 2); `RegistrarEvento`.
- Produces: contrato `ExpurgarRetencao` com
  `executar(bool $executar = false): ResultadoRetencao`.
  `ResultadoRetencao` (readonly) com `int $candidatos`, `int $expurgados` e `bool $executado`.
  Regras: quando `$executar=false` (ou `config('privacidade.retencao.ativa')` falso), **não escreve** nada e devolve contagens; quando executa, para cada vencido marca `expurgado_em=now()` e zera `conteudo_cifrado`/`referencia_segura` (anonimiza), grava evento `acao=retencao.expurgar` (`origem=sistema`, `ator_id=null`) e **não** reaudita o que já está `expurgado_em`.

- [ ] **Step 1: Write the failing test**

Crie `backend/tests/Feature/Privacidade/RetencaoTest.php`:

```php
<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;
use App\Domain\DadosSensiveis\Contracts\ExpurgarRetencao;

beforeEach(function () {
    config(['privacidade.chave' => 'base64:'.base64_encode(random_bytes(32))]);
    config(['privacidade.retencao.ativa' => false]);
});

it('com retencao desligada nao apaga nada (relatorio)', function () {
    $d = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'conteudo_cifrado' => 'cifrado',
    ]);

    $r = app(ExpurgarRetencao::class)->executar();

    expect($r->executado)->toBeFalse();
    expect(DadoSensivelParticipante::query()->find($d->id)->conteudo_cifrado)->toBe('cifrado');
});

it('com retencao ligada expurga vencidos e audita', function () {
    $d = DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'conteudo_cifrado' => 'cifrado',
        'referencia_segura' => null,
    ]);

    $r = app(ExpurgarRetencao::class)->executar(true);

    expect($r->executado)->toBeTrue();
    expect($r->expurgados)->toBe(1);

    $fresh = DadoSensivelParticipante::query()->find($d->id);
    expect($fresh->expurgado_em)->not->toBeNull();
    expect($fresh->conteudo_cifrado)->toBeNull();

    expect(EventoAuditoria::query()->where('acao', 'retencao.expurgar')->count())->toBe(1);
});

it('eh idempotente: rodar duas vezes nao reaudita o expurgado', function () {
    DadoSensivelParticipante::factory()->create([
        'prazo_retencao' => now()->subDay(),
        'conteudo_cifrado' => 'cifrado',
    ]);

    app(ExpurgarRetencao::class)->executar(true);
    app(ExpurgarRetencao::class)->executar(true);

    expect(EventoAuditoria::query()->where('acao', 'retencao.expurgar')->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app php artisan test tests/Feature/Privacidade/RetencaoTest.php`
Expected: FAIL — serviço/contrato inexistentes.

- [ ] **Step 3: Implement**

`Support/ResultadoRetencao.php`:

```php
<?php

namespace App\Domain\DadosSensiveis\Support;

readonly class ResultadoRetencao
{
    public function __construct(
        public int $candidatos,
        public int $expurgados,
        public bool $executado,
    ) {}
}
```

Contrato `Contracts/ExpurgarRetencao.php` (assinatura como em **Produces**).

Serviço `Services/ExpurgadorRetencao.php` — injeta `RegistrarEvento`; envolve numa transação; usa `expiraveis(now())`; se `! $executar || ! config('privacidade.retencao.ativa')` devolve `ResultadoRetencao(candidatos, 0, false)` sem escrever. Ao executar, para cada candidato: `update(['expurgado_em' => now(), 'conteudo_cifrado' => null, 'referencia_segura' => null])` e `auditoria->registrar(['acao' => 'retencao.expurgar', 'recurso' => 'dado_sensivel_participante', 'ator_id' => null, 'origem' => 'sistema', 'metadados_minimizados' => ['dado_id' => $d->id, 'tipo' => $d->tipo]])`. Como `expiraveis` filtra `expurgado_em` nulo, a segunda execução não acha mais o registro (idempotência natural).

Comando `backend/app/Console/Commands/PrivacidadeExpurgar.php`:

```php
<?php

namespace App\Console\Commands;

use App\Domain\DadosSensiveis\Contracts\ExpurgarRetencao;
use Illuminate\Console\Command;

class PrivacidadeExpurgar extends Command
{
    protected $signature = 'privacidade:expurgar {--force : Executa mesmo com a retenção desligada (uso consciente)}';

    protected $description = 'Aplica os prazos de retenção (descarte/anonimização). Desligado até o DPO aprovar (RNF-07).';

    public function handle(ExpurgarRetencao $retencao): int
    {
        $executar = (bool) config('privacidade.retencao.ativa') || (bool) $this->option('force');

        $r = $retencao->executar($executar);

        $this->info(sprintf(
            'Candidatos: %d · Expurgados: %d · Executado: %s',
            $r->candidatos, $r->expurgados, $r->executado ? 'sim' : 'não (dry-run)',
        ));

        return self::SUCCESS;
    }
}
```

Binding do contrato em `DomainServiceProvider`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app php artisan test tests/Feature/Privacidade/RetencaoTest.php`
Expected: PASS (os 3).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/DadosSensiveis backend/app/Console/Commands/PrivacidadeExpurgar.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Privacidade/RetencaoTest.php
git commit -m "feat: motor de retencao configuravel desligado por padrao com comando de expurgo"
```

---

### Task 6: Docs — ADR-0031, CHANGELOG, rastreabilidade

**Files:**
- Create: `docs/06-arquitetura/decisoes/ADR-0031-dados-sensiveis-e-retencao.md`
- Modify: `docs/CHANGELOG.md`
- Modify: `docs/06-arquitetura/decisoes/README.md` (índice, se houver linha a acrescentar)
- Modify: rastreabilidade (arquivo indicado no repo; localizar com grep por `CA-16`/`RNF-07`)

**Interfaces:** nenhuma (documentação).

- [ ] **Step 1: Escrever o ADR-0031** registrando a A2: escopo (chave própria, leitura auditada, retenção configurável desligada), decisões (`meu call`: AES-256-GCM, `SENSITIVE_DATA_KEY`, `controle_acesso` textual, dry-run por padrão) e dívidas (consumidor E03/E08, prazos do DPO, bucket/antivírus E08, frontend). Formato e tom dos ADRs vizinhos (ver ADR-0030).

- [ ] **Step 2: Atualizar CHANGELOG** com a entrada da A2 (contexto: E05/A2, o que entra, dívidas).

- [ ] **Step 3: Atualizar rastreabilidade** (CA-16/RNF-07) conforme o arquivo do repo.

- [ ] **Step 4: Verificar docs**

Run: `python tools/check-endpoints.py` (caminho do repo) — Expected: sem alteração (A2 não mexe no contrato público). Se o script não existir nesse caminho, localize com `grep -ri check-endpoints`.

- [ ] **Step 5: Commit**

```bash
git add docs
git commit -m "docs: ADR-0031 e changelog da sub-etapa A2 (dados sensiveis e retencao)"
```

---

## Self-Review

**1. Spec coverage:** cada objetivo do design vira task — chave própria (T1), entidade (T2), cifra (T3), leitura auditada + controle de acesso (T4), retenção configurável desligada + expurgo idempotente (T5), docs (T6). Não-objetivos (consumidor, bucket, DPO, frontend) não viram task, por design.

**2. Step scan:** cada step é uma ação com resultado checável; os bodies aparecem só onde a assinatura/teste não determinam o algoritmo (validação XOR, composição do `Encrypter`, dry-run).

**3. Type consistency:** `CofreChaveSensivel::encrypter()`, `ArmazenarDadoSensivel::armazenar(...)`, `LerDadoSensivel::ler(...)`, `ExpurgarRetencao::executar(bool)`, `ResultadoRetencao{candidatos,expurgados,executado}` — nomes/assinaturas iguais entre tasks que os usam.

**4. Review Focus:** as 5 classes têm teste na task dona (chave ausente → T1/T3/T4; leitura sem ator → T4; conteúdo em claro → T3; idempotência → T5; XOR de colunas → T3).

**5. Proportion:** plano ~= spec em tamanho; sem transcrição de programa.

## Execution Handoff

Método já definido pelo produto: **subagentes por task** + review fresco por task + review whole-branch no fim. Seguir `superpowers:subagent-driven-development`.