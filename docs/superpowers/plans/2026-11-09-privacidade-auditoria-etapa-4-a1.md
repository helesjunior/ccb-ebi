# Privacidade e auditoria (E05 · sub-etapa A1) — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar o núcleo do E05 que fecha o CA-16: termos versionados + consentimento com evidência, direitos do titular, e a consulta da trilha de auditoria com o `ator_id` corrigido.

**Architecture:** Novo módulo `App\Domain\Privacidade` (models `termo_versao`, `manifestacao_privacidade`, `solicitacao_titular`; Contracts/Services; factories) seguindo [ADR-0002](../../../06-arquitetura/decisoes/ADR-0002-monolito-modular.md) — `app/Http` não importa Models; entre módulos só `Contracts`. Endpoints sob `/api/v1`, contract-first (OpenAPI antes do código). A auditoria já existe (append-only, hash encadeado) e só ganha superfície de consulta; o `ator_id` passa a ser propagado.

**Tech Stack:** Laravel 12, PHP 8.4, Pest, Sanctum, PostgreSQL/SQLite, React + TypeScript (Vite), OpenAPI 3.1.

**Spec:** [docs/superpowers/specs/2026-11-09-privacidade-auditoria-etapa-4-design.md](../specs/2026-11-09-privacidade-auditoria-etapa-4-design.md)

## Global Constraints

- **PHP somente via Docker:** `docker compose run --rm app <cmd>` (nunca PHP do host). Testes: `docker compose run --rm app php artisan test --filter=<Nome>`.
- **Contract-first:** toda rota entra no `docs/04-api/openapi.yaml` **antes** do código e é marcada `no OpenAPI` em `docs/04-api/endpoints.md` (`scripts/check-endpoints.py` valida no CI).
- **Fronteiras modulares:** `ModularBoundariesTest` proíbe `app/Http` (e `app/Policies`) de importar `App\Domain\*\Models` e proíbe um módulo de importar de outro fora de `Contracts`. Respeitar sempre.
- **ULIDs** em todas as chaves primárias novas (`$table->ulid('id')->primary()`; models usam `HasUlids`). FKs `foreignUlid(...)->constrained(...)`.
- **Erros** sempre via `App\Support\ProblemException` (title, code, httpStatus, detail) → problem+json RFC 9457.
- **Paginação** sempre por cursor via `App\Support\PaginacaoCursor`.
- **Nada de dado pessoal em log** (RNF-01).
- **Textos de termo e e-mail de privacidade** nascem marcados como **rascunho/configuráveis**; base legal é coluna texto (não enum).
- **Idioma do código:** comentários e mensagens de erro em português, como o resto do projeto.
- Referência normativa: [ADR-0019](../../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md) (itens 1 e 2), [ADR-0029](../../../06-arquitetura/decisoes/ADR-0029-dados-identificaveis-acima-da-igreja.md).

## Review Focus

- **Aceite sem termo vigente** deve falhar com 422 explícito, não criar conta sem manifestação.
- **Rio de dados Http→Domain da evidência:** o valor de `ip`/`user-agent` deve vir da request real, nunca inventado no serviço (teste com header `User-Agent` fixo).
- **`GET /privacy/consents/{id}` por operador fora da subárvore** deve ser 403, e a manifestação alheia nunca exposta.
- **`GET /audit-events` sem escopo territorial** (ex.: evento com `escopo = null`) não pode vazar para papel territorial.
- **`ator_id` nulo** após escrita auditada é o bug de hoje; o teste deve falhar se voltar a `null`.
- **Retenção/anonimização e registro de finalidade** não entram na A1 (dívida): nada no código deve fingir que entram.

---

### Task 1: Módulo Privacidade + `termo_versao`

**Files:**
- Create: `backend/app/Domain/Privacidade/README.md`
- Create: `backend/app/Domain/Privacidade/Models/TermoVersao.php`
- Create: `backend/app/Domain/Privacidade/Database/Factories/TermoVersaoFactory.php`
- Create: `backend/database/migrations/2026_11_10_100001_create_privacidade_tables.php`
- Create: `backend/app/Domain/Privacidade/Contracts/GerirTermos.php`
- Create: `backend/app/Domain/Privacidade/Services/GerenciadorTermos.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php` (binding)
- Test: `backend/tests/Feature/Privacidade/TermosTest.php`

**Interfaces:**
- Produces:
  - `TermoVersao` (model, `$table = 'termo_versao'`): fillable `['finalidade','base_legal','versao','texto','texto_hash','vigente_em','encerrado_em']`; consts `FINALIDADE_CADASTRO='cadastro'`, `FINALIDADE_NOTIFICACOES='notificacoes'`; `casts(['vigente_em'=>'datetime','encerrado_em'=>'datetime'])`; `scopeVigente(Builder): Builder` (onde `vigente_em <= now` e `encerrado_em` nulo); `static newFactory(): TermoVersaoFactory`.
  - `GerirTermos` (contract): `vigente(string $finalidade): ?TermoVersao`; `publicar(string $finalidade, string $baseLegal, string $versao, string $texto, \DateTimeInterface $vigenteEm): TermoVersao`.
  - `GerenciadorTermos implements GerirTermos`.

- [ ] **Step 1: Escrever o teste que falha** — `backend/tests/Feature/Privacidade/TermosTest.php`

```php
<?php

use App\Domain\Privacidade\Models\TermoVersao;

it('registra uma versao de termo vigente por finalidade', function () {
    $termo = TermoVersao::factory()->create([
        'finalidade' => 'cadastro',
        'versao' => '2026-11',
        'vigente_em' => now()->subDay(),
    ]);

    expect($termo->id)->not->toBeNull();
    expect(TermoVersao::query()->vigente('cadastro')->value('id'))->toBe($termo->id);
});

it('nao retorna termo vigente quando a vigencia e futura ou encerrada', function () {
    TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->addDay()]);
    TermoVersao::factory()->encerrado()->create(['finalidade' => 'cadastro']);

    expect(TermoVersao::query()->vigente('cadastro')->first())->toBeNull();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=TermosTest`
Expected: FAIL — classe `TermoVersao` inexistente.

- [ ] **Step 3: Criar a migration** `backend/database/migrations/2026_11_10_100001_create_privacidade_tables.php`

Tabelas `termo_versao`, `manifestacao_privacidade` e `solicitacao_titular` (as três numa migration, pois o módulo nasce inteiro). `termo_versao`:

```php
Schema::create('termo_versao', function (Blueprint $table): void {
    $table->ulid('id')->primary();
    $table->string('finalidade');
    $table->string('base_legal');
    $table->string('versao');
    $table->text('texto');
    $table->string('texto_hash', 64);
    $table->timestamp('vigente_em');
    $table->timestamp('encerrado_em')->nullable();
    $table->timestamps();

    $table->unique(['finalidade', 'versao']);
    $table->index(['finalidade', 'vigente_em']);
});
```

`manifestacao_privacidade`:

```php
Schema::create('manifestacao_privacidade', function (Blueprint $table): void {
    $table->ulid('id')->primary();
    $table->foreignUlid('usuario_id')->nullable()->constrained('usuarios');
    $table->string('participante_id')->nullable(); // FK real entra no E03
    $table->foreignUlid('termo_versao_id')->constrained('termo_versao');
    $table->string('decisao');   // aceito | recusado | retirado
    $table->string('origem');    // proprio | assistido
    $table->text('evidencia');
    $table->timestamp('ocorrido_em');
    $table->timestamps();

    $table->index(['usuario_id', 'termo_versao_id']);
});
```

`solicitacao_titular`:

```php
Schema::create('solicitacao_titular', function (Blueprint $table): void {
    $table->ulid('id')->primary();
    $table->foreignUlid('solicitante_id')->constrained('usuarios');
    $table->string('participante_id')->nullable();
    $table->string('tipo');        // acesso | correcao | revogacao
    $table->string('protocolo')->unique();
    $table->foreignUlid('responsavel_id')->nullable()->constrained('usuarios');
    $table->date('prazo')->nullable();
    $table->string('status');      // recebida | em_analise | atendida | recusada
    $table->text('descricao')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Criar o model, a factory e o README**

`TermoVersao` conforme Interfaces acima. `scopeVigente`: `where('vigente_em','<=',now())->whereNull('encerrado_em')`. Factory: `finalidade => 'cadastro'`, `base_legal => 'consentimento'`, `versao => '2026-11'`, `texto => '<rascunho>'`, `texto_hash => hash('sha256','<rascunho>')`, `vigente_em => now()`, `encerrado_em => null`; estado `encerrado()` = `encerrado_em => now()->subDay()`. README: uma linha — "Termos versionados e manifestações de consentimento (RF-04). Expoe: `App\Domain\Privacidade\Contracts\*`".

- [ ] **Step 5: Criar `GerirTermos` + `GerenciadorTermos` e o binding**

`publicar` calcula `texto_hash = hash('sha256', $texto)` e cria o registro. Registrar em `DomainServiceProvider`: `$this->app->bind(GerirTermos::class, GerenciadorTermos::class);`.

- [ ] **Step 6: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=TermosTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Domain/Privacidade backend/database/migrations backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Privacidade
git commit -m "feat(privacidade): modulo Privacidade e termos versionados (RF-04)"
```

---

### Task 2: `GET /privacy/terms` (OpenAPI primeiro)

**Files:**
- Modify: `docs/04-api/openapi.yaml` (novo path + schema `TermoVersao`)
- Modify: `docs/04-api/endpoints.md` (marcar `no OpenAPI`)
- Create: `backend/app/Http/Controllers/Api/V1/PrivacyTermController.php`
- Create: `backend/app/Http/Resources/TermoVersaoResource.php`
- Modify: `backend/routes/api.php` (rota autenticada)
- Test: `backend/tests/Feature/Privacidade/TermosEndpointTest.php`

**Interfaces:**
- Consumes: `GerirTermos` (Task 1).
- Produces: `GET /api/v1/privacy/terms?finalidade=<codigo>` → 200 `{data: TermoVersao[]}`; `TermoVersaoResource` sem importar o Model (lê via `$this->resource->...`).

- [ ] **Step 1: Declarar no OpenAPI** — path `/privacy/terms` (GET, `security: [bearerAuth]`, param `finalidade`), schema `TermoVersao` (`id`, `finalidade`, `base_legal`, `versao`, `vigente_em`). Marcar a linha em `endpoints.md`.

- [ ] **Step 2: Escrever o teste que falha** — `TermosEndpointTest.php`

```php
<?php

use App\Domain\Privacidade\Models\TermoVersao;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Hash;

it('lista o termo vigente da finalidade', function () {
    TermoVersao::factory()->create(['finalidade' => 'cadastro', 'versao' => '2026-11', 'vigente_em' => now()->subDay()]);
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $this->getJson('/api/v1/privacy/terms?finalidade=cadastro', $auth)
        ->assertOk()
        ->assertJsonPath('data.0.versao', '2026-11');
});

it('exige autenticacao', function () {
    $this->getJson('/api/v1/privacy/terms?finalidade=cadastro')->assertStatus(401);
});
```

- [ ] **Step 3: Rodar e ver falhar** — `docker compose run --rm app php artisan test --filter=TermosEndpointTest` → FAIL (404).

- [ ] **Step 4: Implementar controller, resource e rota** — `PrivacyTermController@index` injeta `GerirTermos`, valida `finalidade` (`required|string`), devolve `response()->json(['data' => ...])`. Rota dentro do grupo `auth:sanctum` existente: `Route::get('privacy/terms', [PrivacyTermController::class, 'index'])->name('privacy.terms');`.

- [ ] **Step 5: Rodar e ver passar.** Expected: PASS. Rodar também `python scripts/check-endpoints.py` (ou validar por revisão, se o host não tiver python) para o path marcado.

- [ ] **Step 6: Commit**

```bash
git add docs/04-api backend/app/Http backend/routes/api.php backend/tests/Feature/Privacidade
git commit -m "feat(privacidade): GET /privacy/terms (contract-first)"
```

---

### Task 3: Manifestação de consentimento — serviço + CA-16

**Files:**
- Create: `backend/app/Domain/Privacidade/Models/ManifestacaoPrivacidade.php`
- Create: `backend/app/Domain/Privacidade/Database/Factories/ManifestacaoPrivacidadeFactory.php`
- Create: `backend/app/Domain/Privacidade/Contracts/RegistrarConsentimento.php`
- Create: `backend/app/Domain/Privacidade/Contracts/ConsultarConsentimentos.php`
- Create: `backend/app/Domain/Privacidade/Services/GerenciadorConsentimento.php`
- Create: `backend/app/Domain/Privacidade/Support/EvidenciaAceite.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php` (bindings)
- Test: `backend/tests/Feature/Privacidade/ConsentimentoTest.php`

**Interfaces:**
- Produces:
  - `ManifestacaoPrivacidade` (`$table='manifestacao_privacidade'`): consts `DECISAO_ACEITO/RECUSADO/RETIRADO`, `ORIGEM_PROPRIO/ASSISTIDO`; `casts(['ocorrido_em'=>'datetime'])`; relação `termoVersao(): BelongsTo`.
  - `EvidenciaAceite` (value object readonly): `__construct(public readonly string $ip, public readonly string $userAgent, public readonly ?string $termoHash = null)`; `static deRequest(Request $r, string $termoHash): self` (lê `$r->ip()` e `User-Agent`).
  - `RegistrarConsentimento` (contract): `registrar(string $usuarioId, string $termoVersaoId, string $decisao, string $origem, EvidenciaAceite $evidencia): ManifestacaoPrivacidade`; `retirar(string $usuarioId, string $termoVersaoId, EvidenciaAceite $evidencia): ManifestacaoPrivacidade`.
  - `ConsultarConsentimentos` (contract): `atuais(string $usuarioId): array<int, ManifestacaoPrivacidade>`; `encontrar(string $manifestacaoId): ?ManifestacaoPrivacidade`.
  - `GerenciadorConsentimento implements RegistrarConsentimento, ConsultarConsentimentos`.

- [ ] **Step 1: Escrever o teste que falha** — `ConsentimentoTest.php`

```php
<?php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Privacidade\Contracts\ConsultarConsentimentos;
use App\Domain\Privacidade\Contracts\RegistrarConsentimento;
use App\Domain\Privacidade\Models\TermoVersao;
use App\Domain\Privacidade\Support\EvidenciaAceite;
use Illuminate\Support\Facades\Hash;

it('registra um aceite com evidencia e permite recuperar a versao do termo', function () {
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'versao' => '2026-11']);
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);

    $evidencia = new EvidenciaAceite(ip: '203.0.113.5', userAgent: 'phpunit');

    $m = app(RegistrarConsentimento::class)->registrar(
        $u->id, $termo->id, 'aceito', 'proprio', $evidencia,
    );

    expect($m->decisao)->toBe('aceito');
    expect($m->evidencia)->toContain('203.0.113.5');

    $recuperada = app(ConsultarConsentimentos::class)->encontrar($m->id);

    expect($recuperada?->termoVersao?->versao)->toBe('2026-11');
    expect($recuperada?->termoVersao?->texto_hash)->toBe($termo->texto_hash);
});

it('retirar cria uma manifestacao de retirada', function () {
    $termo = TermoVersao::factory()->create();
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);

    $m = app(RegistrarConsentimento::class)->retirar($u->id, $termo->id, new EvidenciaAceite('1.1.1.1', 'phpunit'));

    expect($m->decisao)->toBe('retirado');
});
```

- [ ] **Step 2: Rodar e ver falhar.** Expected: FAIL (classe inexistente).

- [ ] **Step 3: Implementar model + factory + value object + contratos + serviço + bindings** conforme Interfaces. `registrar`/`retirar` criam a `ManifestacaoPrivacidade` com `ocorrido_em = now()` e `evidencia` textual (`sprintf('aceite digital ip=%s ua=%s termo=%s', ...)`, para `aceito`; análogo para os demais). `evidencia` guarda o hash do termo quando fornecido. Registrar os dois bindings em `DomainServiceProvider`.

- [ ] **Step 4: Rodar e ver passar.** Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Privacidade backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Privacidade
git commit -m "feat(privacidade): consentimento com evidencia e recuperacao (CA-16)"
```

---

### Task 4: Endpoints `/privacy/consents` e a operação `consentimento.ler`

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/PrivacyConsentController.php`
- Create: `backend/app/Http/Requests/Privacidade/ConsentimentoRequest.php`
- Create: `backend/app/Http/Resources/ManifestacaoPrivacidadeResource.php`
- Create: `backend/app/Policies/ConsentimentoPolicy.php`
- Modify: `backend/app/Domain/Identidade/Support/Operacoes.php` (+ `CONSENTIMENTO_LER`)
- Modify: `backend/app/Domain/Identidade/Services/AutorizadorOperacao.php` (+ constante nos papéis)
- Modify: `backend/app/Providers/ApiServiceProvider.php` (+ `Gate::define`)
- Modify: `backend/routes/api.php`
- Modify: `docs/04-api/openapi.yaml` + `docs/04-api/endpoints.md`
- Test: `backend/tests/Feature/Privacidade/ConsentimentoEndpointTest.php`

**Interfaces:**
- Consumes: `RegistrarConsentimento`, `ConsultarConsentimentos`, `GerirTermos`; `AutorizarOperacao`, `ContextoAtuacao`, `Operacoes::CONSENTIMENTO_LER`.
- Produces:
  - `GET /api/v1/privacy/consents` → 200 `{data: ManifestacaoPrivacidade[]}` (só do próprio usuário).
  - `POST /api/v1/privacy/consents` body `{termo_versao_id, decisao (aceito|recusado), retirar?: bool}` → 201 manifestação. `participante_id` **rejeitado** nesta etapa (E03).
  - `GET /api/v1/privacy/consents/{id}` → 200 manifestação + `termo_versao` (versão + `texto_hash`) + evidência; sob `can:consentimento.ler`; 403 fora do escopo/horizontal.
  - `Operacoes::CONSENTIMENTO_LER = 'consentimento.ler'`; papéis com a operação: `super_admin`, `adm_regional`, `adm_setor`, `adm_igreja`.

- [ ] **Step 1: OpenAPI primeiro** — os 3 paths + schema `ManifestacaoPrivacidade` (com `termo_versao` aninhado na resposta do `{id}`). Marcar em `endpoints.md`.

- [ ] **Step 2: Escrever o teste que falha** — `ConsentimentoEndpointTest.php`

```php
<?php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Privacidade\Models\TermoVersao;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

function usuarioCom(string $papel, string $tipo, string $escopoId): Usuario {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create([
        'usuario_id' => $u->id,
        'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => $tipo, 'escopo_id' => $escopoId,
    ]);
    return $u;
}

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->igreja = Igreja::factory()->create();
});

it('registra o proprio consentimento e o lista', function () {
    TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->subDay()]);
    $u = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $termoId = \App\Domain\Privacidade\Models\TermoVersao::query()->vigente('cadastro')->value('id');

    $this->postJson('/api/v1/privacy/consents', ['termo_versao_id' => $termoId, 'decisao' => 'aceito'], $auth)
        ->assertStatus(201)->assertJsonPath('decisao', 'aceito');

    $this->getJson('/api/v1/privacy/consents', $auth)
        ->assertOk()->assertJsonPath('data.0.decisao', 'aceito');
});

it('operador dentro do escopo recupera a versao do termo (CA-16)', function () {
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'versao' => '2026-11', 'vigente_em' => now()->subDay()]);
    $titular = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $eu = ['Authorization' => 'Bearer '.$titular->createToken('t', ['*'])->plainTextToken];
    $m = $this->postJson('/api/v1/privacy/consents', ['termo_versao_id' => $termo->id, 'decisao' => 'aceito'], $eu)->json();

    $admin = usuarioCom('adm_igreja', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:'.$this->igreja->id];

    $this->getJson("/api/v1/privacy/consents/{$m['id']}", $auth)
        ->assertOk()
        ->assertJsonPath('termo_versao.versao', '2026-11');
});

it('nega a leitura a operador de outra subarvore', function () {
    $termo = TermoVersao::factory()->create(['vigente_em' => now()->subDay()]);
    $titular = usuarioCom('responsavel', 'igreja', $this->igreja->id);
    $eu = ['Authorization' => 'Bearer '.$titular->createToken('t', ['*'])->plainTextToken];
    $m = $this->postJson('/api/v1/privacy/consents', ['termo_versao_id' => $termo->id, 'decisao' => 'aceito'], $eu)->json();

    $outra = Igreja::factory()->create();
    $admin = usuarioCom('adm_igreja', 'igreja', $outra->id);
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:'.$outra->id];

    $this->getJson("/api/v1/privacy/consents/{$m['id']}", $auth)->assertStatus(403);
});
```

- [ ] **Step 3: Rodar e ver falhar.** Expected: FAIL (404/rota ausente).

- [ ] **Step 4: Implementar.** `Operacoes::CONSENTIMENTO_LER`; adicionar a consta à matriz para os 4 papéis; `ConsentimentoPolicy::ler(?Authenticatable, ?string $manifestacaoId)` lê `contexto_atuacao` e chama `AutorizarOperacao->permite($contexto, Operacoes::CONSENTIMENTO_LER, 'usuario', $donoId)` — o tipo de recurso `usuario` já é coberto por `ResolverEscopo` (o dono da manifestação). Rota: `GET /privacy/consents/{id}` com `->middleware('can:consentimento.ler,{id}')`. Os endpoints do titular (index/store) ficam sem `can:` e usam o próprio `$request->user()->id`. `ConsentimentoRequest` valida `termo_versao_id` (`exists:termo_versao,id`) e `decisao` (`in:aceito,recusado`); rejeita `participante_id` se presente (422). Controller monta evidência via `EvidenciaAceite::deRequest()`.

- [ ] **Step 5: Rodar e ver passar.** Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add docs/04-api backend/app/Http backend/app/Policies backend/app/Domain/Identidade backend/app/Providers backend/routes/api.php backend/tests/Feature/Privacidade
git commit -m "feat(privacidade): endpoints de consentimento e operacao consentimento.ler (CA-16)"
```

---

### Task 5: Aceite obrigatório no cadastro de responsável

**Files:**
- Modify: `backend/app/Domain/Identidade/Contracts/RegistrarConta.php` (assinatura + `EvidenciaAceite`)
- Modify: `backend/app/Domain/Identidade/Services/GerenciadorConta.php`
- Modify: `backend/app/Http/Requests/Identidade/RegistrarRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/RegisterController.php`
- Modify: `docs/04-api/openapi.yaml` (schema `RegistroGuardiao`) + `docs/04-api/endpoints.md`
- Test: `backend/tests/Feature/Privacidade/CadastroConsentimentoTest.php`

**Interfaces:**
- Consumes: `RegistrarConsentimento`, `GerirTermos`, `EvidenciaAceite` (módulo Privacidade — import via `Contracts`/`Support` conforme fronteira; `Support` de outro módulo **viola** o teste Arch → mover uso: ver nota).
- Produces: `RegistrarConta::registrar(string $nome, string $email, string $senha, string $termoVersaoId, string $origem, EvidenciaAceite $evidencia): Usuario`.

> **Nota de fronteira (importante):** `ModularBoundariesTest` só permite importar de outro módulo via `Contracts`. Portanto `EvidenciaAceite` **não pode** viver em `Privacidade\Support` sendo usado por `Identidade`. Mover `EvidenciaAceite` para `backend/app/Support/EvidenciaAceite.php` (namespace `App\Support`) — fora de `Domain`, compartilhado. Ajustar a Task 3 para criá-lo lá.

- [ ] **Step 1: Mover `EvidenciaAceite` para `App\Support`** (ajuste da Task 3) e atualizar usos.

- [ ] **Step 2: Escrever o teste que falha** — `CadastroConsentimentoTest.php`

```php
<?php

use App\Domain\Privacidade\Models\ManifestacaoPrivacidade;
use App\Domain\Privacidade\Models\TermoVersao;
use App\Http\Middleware\ForceJsonResponse;

it('exige o aceite do termo vigente para criar a conta', function () {
    $this->postJson('/api/v1/auth/register-guardian', [
        'nome' => 'Responsável Teste', 'email' => 'resp@ebi.test', 'password' => 'senha-bem-longa-123',
    ], ['User-Agent' => 'phpunit'])->assertStatus(422);
});

it('cria a conta e registra a manifestacao com evidencia na mesma transacao', function () {
    $termo = TermoVersao::factory()->create(['finalidade' => 'cadastro', 'vigente_em' => now()->subDay()]);

    $this->postJson('/api/v1/auth/register-guardian', [
        'nome' => 'Responsável Teste', 'email' => 'resp2@ebi.test', 'password' => 'senha-bem-longa-123',
        'termo_versao_id' => $termo->id, 'aceite' => true,
    ], ['User-Agent' => 'phpunit'])->assertStatus(201);

    $m = ManifestacaoPrivacidade::query()->latest('id')->first();

    expect($m)->not->toBeNull();
    expect($m->decisao)->toBe('aceito');
    expect($m->origem)->toBe('proprio');
    expect($m->evidencia)->toContain('phpunit');
});
```

- [ ] **Step 3: Rodar e ver falhar.** Expected: FAIL (o register atual aceita sem aceite → 201 no primeiro caso, e não cria manifestação).

- [ ] **Step 4: Implementar.** `RegistrarRequest`: `termo_versao_id` (`required|exists:termo_versao,id`), `aceite` (`required|accepted`). `GerenciadorConta::registrar` valida que o termo está **vigente** (via `GerirTermos`) — se não, lança `ProblemException('termo_indisponivel', ..., 422)` — e, na mesma `DB::transaction`, chama `RegistrarConsentimento->registrar($usuario->id, $termoVersaoId, 'aceito', $origem, $evidencia)`. Controller monta `EvidenciaAceite::deRequest($request, hash('sha256', $termo->texto))`. `RegistrarConta` passa a receber `$termoVersaoId, $origem, EvidenciaAceite`. Atualizar `openapi.yaml` (`RegistroGuardiao` ganha `termo_versao_id`, `aceite`).

- [ ] **Step 5: Rodar e ver passar** + suíte de identidade (o register é usado por `CadastroTest`): `docker compose run --rm app php artisan test --filter=Cadastro`. Ajustar testes existentes que chamam o register sem aceite (criar helper ou adicionar os campos).

- [ ] **Step 6: Commit**

```bash
git add docs/04-api backend/app backend/tests
git commit -m "feat(privacidade): aceite obrigatorio no cadastro de responsavel (CA-16)"
```

---

### Task 6: Solicitação de direitos do titular

**Files:**
- Create: `backend/app/Domain/Privacidade/Models/SolicitacaoTitular.php`
- Create: `backend/app/Domain/Privacidade/Database/Factories/SolicitacaoTitularFactory.php`
- Create: `backend/app/Domain/Privacidade/Contracts/SolicitarDireitoTitular.php`
- Create: `backend/app/Domain/Privacidade/Services/GerenciadorSolicitacao.php`
- Create: `backend/app/Http/Controllers/Api/V1/PrivacyRequestController.php`
- Create: `backend/app/Http/Requests/Privacidade/SolicitacaoRequest.php`
- Create: `backend/app/Http/Resources/SolicitacaoTitularResource.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`, `backend/routes/api.php`
- Modify: `docs/04-api/openapi.yaml` + `docs/04-api/endpoints.md`
- Test: `backend/tests/Feature/Privacidade/SolicitacaoTitularTest.php`

**Interfaces:**
- Produces:
  - `SolicitacaoTitular` (`$table='solicitacao_titular'`): consts `TIPO_ACESSO/CORRECAO/REVOGACAO`, `STATUS_RECEBIDA`; `casts(['prazo'=>'date'])`.
  - `SolicitarDireitoTitular` (contract): `abrir(string $solicitanteId, string $tipo, ?string $descricao): SolicitacaoTitular`.
  - `POST /api/v1/privacy/requests` body `{tipo, descricao?}` → 201 `{id, protocolo, tipo, status}`. Protocolo = `strtoupper(Str::ulid())` (curto). Status inicial `recebida`.

- [ ] **Step 1: OpenAPI** — path `/privacy/requests` (POST) + schema `SolicitacaoTitular`. Marcar em `endpoints.md`.

- [ ] **Step 2: Teste que falha** — `SolicitacaoTitularTest.php`

```php
<?php

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Hash;

it('abre uma solicitacao com protocolo e status recebida', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $this->postJson('/api/v1/privacy/requests', ['tipo' => 'acesso'], $auth)
        ->assertStatus(201)
        ->assertJsonPath('status', 'recebida')
        ->assertJsonStructure(['id', 'protocolo', 'tipo', 'status']);
});

it('rejeita tipo invalido', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken];

    $this->postJson('/api/v1/privacy/requests', ['tipo' => 'x'], $auth)->assertStatus(422);
});
```

- [ ] **Step 3: Rodar e ver falhar.** Expected: FAIL.

- [ ] **Step 4: Implementar** model/factory/contrato/serviço/controller/resource/rota + binding. `SolicitacaoRequest` valida `tipo` (`in:acesso,correcao,revogacao`).

- [ ] **Step 5: Rodar e ver passar.** Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add docs/04-api backend/app backend/routes/api.php backend/tests/Feature/Privacidade
git commit -m "feat(privacidade): solicitacao de direitos do titular (RF-16)"
```

---

### Task 7: Auditoria consultável — `GET /audit-events`

**Files:**
- Create: `backend/app/Domain/Auditoria/Contracts/ConsultarAuditoria.php`
- Create: `backend/app/Domain/Auditoria/Services/ConsultaAuditoria.php`
- Create: `backend/app/Http/Controllers/Api/V1/AuditEventController.php`
- Create: `backend/app/Http/Resources/EventoAuditoriaResource.php`
- Create: `backend/app/Policies/AuditoriaPolicy.php`
- Modify: `backend/app/Domain/Identidade/Support/Operacoes.php` (+ `AUDITORIA_LER`)
- Modify: `backend/app/Domain/Identidade/Services/AutorizadorOperacao.php` (+ consta a `super_admin`, `adm_regional`, `adm_setor`)
- Modify: `backend/app/Providers/ApiServiceProvider.php`, `backend/app/Providers/DomainServiceProvider.php`, `backend/routes/api.php`
- Modify: `docs/04-api/openapi.yaml` + `docs/04-api/endpoints.md`
- Test: `backend/tests/Feature/Privacidade/AuditoriaEndpointTest.php`

**Interfaces:**
- Consumes: `EventoAuditoria` (módulo Auditoria), `ResolverEscopo`/`ConsultarHierarquia` para revalidar o `escopo` textual.
- Produces:
  - `ConsultarAuditoria` (contract): `listar(ContextoAtuacao $contexto, array $filtros, int $limit): CursorPaginator` — `$filtros`: `recurso`, `ator_id`, `de`, `ate`.
  - `GET /api/v1/audit-events` → 200 `{data, meta:{proximo_cursor}}` sob `can:auditoria.ler`.
  - `Operacoes::AUDITORIA_LER = 'auditoria.ler'`.
  - **Regra de escopo:** `super_admin` vê tudo. Papel territorial vê eventos cujo `escopo` textual (`tipo:id`) tenha nó **dentro da sua subárvore** (revalidado via `ResolverEscopo::cobre`); eventos com `escopo` nulo ou não-territorial **não** aparecem para papel territorial.

- [ ] **Step 1: OpenAPI** — path `/audit-events` (GET, params `recurso`, `ator_id`, `de`, `ate`, `cursor`, `limit`) + schema `EventoAuditoria`. Marcar em `endpoints.md`.

- [ ] **Step 2: Teste que falha** — `AuditoriaEndpointTest.php`

```php
<?php

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Igreja;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

function comVinculo(string $papel, string $tipo, string $escopoId): Usuario {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create([
        'usuario_id' => $u->id, 'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
        'tipo_escopo' => $tipo, 'escopo_id' => $escopoId,
    ]);
    return $u;
}

beforeEach(function () { $this->seed(PapelSeeder::class); $this->igreja = Igreja::factory()->create(); });

it('lista eventos para super_admin', function () {
    app(RegistrarEvento::class)->registrar(['acao' => 'x', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => 'igreja:'.$this->igreja->id]);
    $admin = comVinculo('super_admin', 'central', 'central-1');
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=super_admin;escopo=central:central-1'];

    $this->getJson('/api/v1/audit-events', $auth)->assertOk()->assertJsonStructure(['data', 'meta' => ['proximo_cursor']]);
});

it('nao mostra a papel territorial evento sem escopo territorial', function () {
    app(RegistrarEvento::class)->registrar(['acao' => 'x', 'recurso' => 'usuario', 'origem' => 'api', 'escopo' => null]);
    $adm = comVinculo('adm_igreja', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$adm->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:'.$this->igreja->id];

    $this->getJson('/api/v1/audit-events', $auth)->assertOk()->assertJsonCount(0, 'data');
});

it('nega a papel sem permissao', function () {
    $u = comVinculo('auxiliar', 'igreja', $this->igreja->id);
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=auxiliar;escopo=igreja:'.$this->igreja->id];

    $this->getJson('/api/v1/audit-events', $auth)->assertStatus(403);
});
```

- [ ] **Step 3: Rodar e ver falhar.** Expected: FAIL (404).

- [ ] **Step 4: Implementar.** `ConsultaAuditoria::listar` monta a query com filtros e, para papel ≠ `super_admin`, aplica o filtro territorial: para cada `escopo` textual `tipo:id`, incluir só se `ResolverEscopo::cobre($contexto->tipoEscopo, $contexto->escopoId, $tipo, $id)`. Como isso é por linha, materializar via `whereIn('id', <ids visíveis>)` calculado a partir dos `escopo` distintos presentes (query enxuta: `select distinct escopo`), ou filtrar em PHP após o cursor — **decisão do implementador**, desde que o teste de "sem escopo territorial não aparece" e o teste de super_admin passem. `AuditoriaPolicy::ler` delega a `AutorizarOperacao` com `Operacoes::AUDITORIA_LER`. Registrar `Gate::define(Operacoes::AUDITORIA_LER, [AuditoriaPolicy::class,'ler'])`.

- [ ] **Step 5: Rodar e ver passar.** Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add docs/04-api backend/app backend/routes/api.php backend/tests/Feature/Privacidade
git commit -m "feat(auditoria): consulta de eventos por papel e escopo (RF-16)"
```

---

### Task 8: Corrigir o `ator_id` das escritas auditadas

**Files:**
- Modify: `backend/app/Domain/Identidade/Services/ContextoAtuacao.php` (+ `usuarioId`)
- Modify: `backend/app/Domain/Identidade/Services/ResolvedorContextoAtuacao.php` (preencher `usuarioId`)
- Modify: `backend/app/Domain/Identidade/Services/GerenciadorUsuario.php`
- Modify: `backend/app/Domain/Identidade/Services/GerenciadorVinculo.php`
- Modify: `backend/app/Domain/Organizacao/Services/ServicoTransferencia.php` (recebe `?string $atorId`)
- Modify: `backend/app/Http/Controllers/Api/V1/ChurchController.php` (passar o ator na transferência)
- Test: `backend/tests/Feature/Privacidade/AtorAuditoriaTest.php`

**Interfaces:**
- Produces: `ContextoAtuacao` ganha `public readonly ?string $usuarioId = null` (4º parâmetro, default null — compatível com construções existentes). `GerenciadorUsuario`/`GerenciadorVinculo` gravam `'ator_id' => $contexto->usuarioId`. `ServicoTransferencia::executar(..., ?string $atorId = null)` grava `'ator_id' => $atorId`.

- [ ] **Step 1: Teste que falha** — `AtorAuditoriaTest.php`

```php
<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Igreja;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () { $this->seed(PapelSeeder::class); $this->igreja = Igreja::factory()->create(); });

it('grava o ator na alteracao de status de usuario', function () {
    $admin = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create(['usuario_id' => $admin->id, 'papel_id' => Papel::query()->where('codigo','adm_igreja')->value('id'), 'tipo_escopo'=>'igreja','escopo_id'=>$this->igreja->id]);
    $alvo = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create(['usuario_id' => $alvo->id, 'papel_id' => Papel::query()->where('codigo','coordenadora')->value('id'), 'tipo_escopo'=>'igreja','escopo_id'=>$this->igreja->id]);

    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t', ['*'])->plainTextToken, 'X-Acting-Scope' => 'papel=adm_igreja;escopo=igreja:'.$this->igreja->id];

    $this->patchJson("/api/v1/users/{$alvo->id}", ['status' => 'suspenso'], $auth)->assertOk();

    $evento = EventoAuditoria::query()->where('acao', 'alterar_status_usuario')->latest('id')->first();
    expect($evento->ator_id)->toBe($admin->id);
});
```

- [ ] **Step 2: Rodar e ver falhar.** Expected: FAIL — `ator_id` é `null`.

- [ ] **Step 3: Implementar.** `ContextoAtuacao` ganha `usuarioId`; `ResolvedorContextoAtuacao` preenche com o id do usuário autenticado. Atualizar os três serviços auditados e o `ChurchController` (transferência). Rodar a suíte de identidade/organização para garantir que nada quebre.

- [ ] **Step 4: Rodar e ver passar.** Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app backend/tests
git commit -m "fix(auditoria): grava o ator nas escritas auditadas (RF-16/RN-10)"
```

---

### Task 9: Portal — telas de privacidade, solicitação e auditoria + aceite no cadastro

**Files:**
- Modify: `frontend/src/api/schema.d.ts` (regenerar)
- Create: `frontend/src/pages/Privacidade.tsx`
- Create: `frontend/src/pages/Auditoria.tsx`
- Modify: `frontend/src/App.tsx` (navegação para as novas telas)
- Modify: `frontend/src/pages/Cadastro.tsx` (aceite do termo) — se existir; senão criar o componente de aceite no fluxo de cadastro
- Test: nenhum runner de frontend (dívida); validar por `oxlint` + `build`

**Interfaces:**
- Consumes: os endpoints das Tasks 2/4/6/7.

- [ ] **Step 1: Regenerar o cliente tipado** — `cd frontend && npm run gen:api`.

- [ ] **Step 2: Implementar as telas** — `Privacidade` (termo vigente, consentimento atual, botão de retirada, formulário de solicitação); `Auditoria` (listagem paginada, visível só a perfis com a operação); aceite do termo no cadastro de responsável (checkbox + envio de `termo_versao_id`). Telas acessíveis (labels, teclado, contraste) e usáveis no celular.

- [ ] **Step 3: Validar** — `cd frontend && npm run lint && npm run build`. Expected: 0 erros.

- [ ] **Step 4: Commit**

```bash
git add frontend
git commit -m "feat(web): telas de privacidade, solicitacao e auditoria"
```

---

### Task 10: Documentação da etapa

**Files:**
- Modify: `docs/CHANGELOG.md` (nova versão 2.13 — Etapa 4 A1)
- Create: `docs/06-arquitetura/decisoes/ADR-0030-privacidade-e-auditoria.md` (escopo e dívidas da etapa)
- Modify: `docs/01-requisitos/rastreabilidade.md` (RF-04/CA-16, RF-16/CA-13 marcados)
- Modify: `docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md` (+ operações `consentimento.ler` e `auditoria.ler`)
- Modify: `docs/04-api/endpoints.md` se necessário

**Interfaces:** nenhuma (documentação).

- [ ] **Step 1: ADR-0030** — registra o escopo entregue e as dívidas: registro de finalidade (`*`) e exceção do ADR-0029 (aguardam recurso sensível); A2 (chave própria + retenção); cadastro assistido completo (E03); textos finais (jurídico).

- [ ] **Step 2: CHANGELOG 2.13** — resumo da A1.

- [ ] **Step 3: Rastreabilidade e matriz** — atualizar as linhas.

- [ ] **Step 4: Commit**

```bash
git add docs
git commit -m "docs(privacidade): registra a sub-etapa A1, dividas e rastreabilidade"
```

---

## Self-review

- **Cobertura do spec:** §6.1 → T1; §6.2 → T1/T3/T6; §6.3 → T1/T3/T6/T7; §6.4 → T2/T4/T6/T7; §6.5 → T5; §6.6 → T7/T8; §6.6-bis → T4; §6.6-ter → T5; §6.7 → T9; CA-16 → T3/T4/T5; `ator_id` → T8. Dívidas → T10. Coberto.
- **Consistência de tipos:** `EvidenciaAceite` mora em `App\Support` (T5 nota) — T3 o cria lá; `RegistrarConta::registrar` (T5) usa `$termoVersaoId,$origem,EvidenciaAceite`; `Operacoes::CONSENTIMENTO_LER`/`AUDITORIA_LER` nomeadas igual em T4/T7; `ContextoAtuacao::usuarioId` definido em T8 e só consumido em T4/T7 via `AutorizarOperacao` (não depende dele).
- **Review Focus → testes:** aceite sem termo → T5 Step 2; evidência real → T5 Step 2 (`User-Agent`); operador fora da subárvore → T4 Step 2; evento sem escopo → T7 Step 2; `ator_id` nulo → T8 Step 1.
- **Proporção:** plano descreve decisões e testes; os corpos ficam para o implementador (só migrations e value objects vão completos, por serem valores exatos).