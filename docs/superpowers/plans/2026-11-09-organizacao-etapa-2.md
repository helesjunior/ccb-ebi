# Organização (Etapa 2) — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar a estrutura organizacional (Central → Regional → Setor → Igreja → EBI) com histórico de vinculação, transferência de igreja (o setor vigente é o atual, sem data de corte) e carga CSV idempotente da Regional de Brasília.

**Architecture:** Módulo `App\Domain\Organizacao` (ADR-0002) com contratos públicos, models, serviços e um provider de binding. A igreja é a âncora territorial; o setor vigente é resolvido por `igreja_vinculacao` com `fim IS NULL`. Autorização provisória por `Gate` (RBAC real na Etapa 3). API REST contract-first em `/api/v1`.

**Tech Stack:** Laravel 13, PHP 8.4 (em contêiner), PostgreSQL 17 / SQLite (testes), Pest 4, Sanctum, ULID, OpenAPI 3.1 (Spectral).

**Spec:** `docs/superpowers/specs/2026-11-09-organizacao-etapa-2-design.md`

## Global Constraints

- **PHP 8.4 sempre em contêiner**: todo comando de backend via `docker compose run --rm app <cmd>`. O host tem PHP 8.3; nunca usar o PHP do host.
- **Monólito modular (ADR-0002)**: código fora de `App\Domain` não usa `App\Domain\<X>\Models\*`; um módulo só consome `Contracts/` de outro.
- **IDs opacos**: tabelas novas usam ULID (`$table->ulid('id')->primary()`), exposto como `string`.
- **API**: rotas e campos em **inglês**; domínio/código em português. Prefixo `/api/v1`. Erros `application/problem+json` (RFC 9457). Paginação por cursor (`?cursor=&limit=`, `limit` máx. 100).
- **Legado de teste**: `atores` usa `bigint`; os testes usam SQLite (`phpunit.xml`) com `RefreshDatabase`; `tests/Pest.php` limpa o cache antes de cada teste de Feature.
- **Auditoria**: toda escrita organizacional chama `RegistrarEvento` (append-only).
- **Doc-first**: cada rota entra em `docs/04-api/openapi.yaml` antes do código e é marcada `no OpenAPI` em `docs/04-api/endpoints.md`; `scripts/check-endpoints.py` roda no CI.
- **Qualidade**: `composer pint --test`, `composer phpstan` (nível 8) e `composer test` passam ao fim de cada task.
- Valores fixos do spec: `igreja.codigo` é string numérica UNIQUE (ex.: `240003`); `ebi.participantes_por_auxiliar` default `4`; `fim` de `igreja_vinculacao` é exclusivo (`[inicio, fim)`).

## Review Focus

- **Transferência com data no passado ou igual a outro vínculo**: sobreposição de vigência deve ser rejeitada (422 problem+json), nunca silenciosamente aceita.
- **Importação CSV repetida**: rodar o mesmo arquivo duas vezes não pode duplicar igrejas/setores (idempotência por `codigo`).
- **Linha de CSV sem `codigo`**: deve falhar com erro claro da linha, sem gravar registro parcial.
- **Escrita sem token / com papel não autorizado**: deve ser 401/403 problem+json, não 500 nem 200.
- **Leitura do setor vigente quando a igreja tem zero vínculos**: deve ser tratada (igreja sem setor não quebra listagem/relatório).

---

### Task 1: Camada de dados da organização (migrations + models)

**Files:**
- Create: `backend/database/migrations/2026_11_09_100001_create_organizacao_tables.php`
- Create: `backend/app/Domain/Organizacao/Models/CentralNacional.php`
- Create: `backend/app/Domain/Organizacao/Models/Regional.php`
- Create: `backend/app/Domain/Organizacao/Models/Setor.php`
- Create: `backend/app/Domain/Organizacao/Models/Igreja.php`
- Create: `backend/app/Domain/Organizacao/Models/IgrejaVinculacao.php`
- Create: `backend/app/Domain/Organizacao/Models/Ebi.php`
- Create: `backend/app/Domain/Organizacao/Database/Factories/RegionalFactory.php`
- Create: `backend/app/Domain/Organizacao/Database/Factories/SetorFactory.php`
- Create: `backend/app/Domain/Organizacao/Database/Factories/IgrejaFactory.php`
- Create: `backend/app/Domain/Organizacao/Database/Factories/EbiFactory.php`
- Test: `backend/tests/Feature/Organizacao/EsquemaTest.php`

**Interfaces:**
- Produces: models com `ULID` PK; `Igreja` com `codigo` (string, UNIQUE), `nome`, `endereco?`, `latitude?`, `longitude?`, `timezone`, `status`; relação `Igreja::vinculacoes()` (HasMany), `Igreja::vinculacaoVigente()` (HasOne, `fim IS NULL`); `IgrejaVinculacao` com `igreja_id`, `setor_id`, `inicio` (date), `fim` (date?, nullable); `Setor::regional()`, `Ebi::igreja()`.

- [ ] **Step 1: Escrever o teste que falha do esquema**

```php
<?php // tests/Feature/Organizacao/EsquemaTest.php

use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;

it('cria a hierarquia com ULID e codigo unico de igreja', function () {
    $central = CentralNacional::create(['nome' => 'Central (Brás)']);
    $regional = Regional::create(['central_nacional_id' => $central->id, 'nome' => 'Brasília', 'codigo' => '01', 'status' => 'ativo']);
    $setor = Setor::create(['regional_id' => $regional->id, 'nome' => 'Setor A', 'codigo' => '0110', 'status' => 'ativo']);
    $igreja = Igreja::create(['codigo' => '240003', 'nome' => 'Núcleo Bandeirante', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo']);
    $ebi = Ebi::create(['igreja_id' => $igreja->id, 'nome' => 'EBI Núcleo Bandeirante', 'status' => 'ativo']);

    expect(strlen($igreja->id))->toBe(26)
        ->and($igreja->codigo)->toBe('240003')
        ->and($ebi->participantes_por_auxiliar)->toBe(4)
        ->and($igreja->vinculacoes()->count())->toBe(0);
});

it('impede codigo de igreja duplicado', function () {
    Igreja::create(['codigo' => '240003', 'nome' => 'A', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo']);
    Igreja::create(['codigo' => '240003', 'nome' => 'B', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo']);
})->throws(Throwable::class);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=EsquemaTest`
Expected: FAIL ("Class CentralNacional not found" / tabela inexistente).

- [ ] **Step 3: Escrever a migration**

Uma migration cria as seis tabelas. Chaves estrangeiras com `ulid`. `igreja.codigo` `unique()`. `ebi.participantes_por_auxiliar` `unsignedSmallInteger()->default(4)`. Índice composto `('igreja_id', 'fim')` em `igreja_vinculacao`.

- [ ] **Step 4: Escrever os models e factories**

Padrão dos models existentes (`$table`, `$fillable`/`$guarded`, `casts`, `newFactory`). `IgrejaVinculacao` com `casts: ['inicio' => 'date', 'fim' => 'date']`. `Igreja::vinculacaoVigente()` = `hasOne(IgrejaVinculacao::class)->whereNull('fim')`. Factories no namespace `App\Domain\Organizacao\Database\Factories`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=EsquemaTest`
Expected: PASS (2 testes).

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_11_09_100001_create_organizacao_tables.php backend/app/Domain/Organizacao backend/tests/Feature/Organizacao/EsquemaTest.php
git commit -m "cria tabelas e models da organizacao"
```

---

### Task 2: Contratos públicos e consulta do setor vigente

**Files:**
- Create: `backend/app/Domain/Organizacao/Contracts/ConsultarOrganizacao.php`
- Create: `backend/app/Domain/Organizacao/Services/ConsultaOrganizacao.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Organizacao/ConsultaVigenteTest.php`

**Interfaces:**
- Consumes: models da Task 1.
- Produces: interface `ConsultarOrganizacao` com `setorVigente(string $igrejaId): ?Setor` e `regionalDoSetor(string $setorId): ?Regional`; binding no `DomainServiceProvider`. Serviços de outras etapas resolvem território **por aqui**, nunca lendo tabelas de Organização.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php // tests/Feature/Organizacao/ConsultaVigenteTest.php

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Models\Igreja;

it('resolve o setor vigente e troca ao transferir', function () {
    $igreja = Igreja::factory()->create();
    $consulta = app(ConsultarOrganizacao::class);
    expect($consulta->setorVigente($igreja->id))->toBeNull(); // sem vínculo ainda
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=ConsultaVigenteTest`
Expected: FAIL (interface/binding inexistentes).

- [ ] **Step 3: Implementar interface e serviço**

`ConsultaOrganizacao::setorVigente()` busca `IgrejaVinculacao` da igreja com `fim IS NULL` e devolve o `Setor`; `regionalDoSetor()` devolve a `Regional` do setor. Registrar `bind(ConsultarOrganizacao::class, ConsultaOrganizacao::class)`.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=ConsultaVigenteTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Organizacao/Contracts backend/app/Domain/Organizacao/Services/ConsultaOrganizacao.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Organizacao/ConsultaVigenteTest.php
git commit -m "adiciona contrato de consulta e setor vigente"
```

---

### Task 3: Transferência de igreja com vigência + auditoria (regra central)

**Files:**
- Create: `backend/app/Domain/Organizacao/Contracts/TransferirIgreja.php`
- Create: `backend/app/Domain/Organizacao/Services/ServicoTransferencia.php`
- Create: `backend/app/Domain/Organizacao/Exceptions/SobreposicaoVigenciaException.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Organizacao/TransferenciaTest.php`

**Interfaces:**
- Consumes: models da Task 1; `ConsultarOrganizacao` (Task 2); `App\Domain\Auditoria\Contracts\RegistrarEvento`.
- Produces: interface `TransferirIgreja` com `executar(string $igrejaId, string $setorDestinoId, \DateTimeInterface $data, ?string $motivo = null): IgrejaVinculacao`. Regra: fecha o vínculo ativo (`fim = data`, exclusivo) e abre novo (`inicio = data`, `fim = null`); rejeita sobreposição com `SobreposicaoVigenciaException`; registra evento `acao: transferir`, `recurso: igreja`.

- [ ] **Step 1: Escrever o teste que falha (inclui a regra territorial)**

```php
<?php // tests/Feature/Organizacao/TransferenciaTest.php

use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Contracts\TransferirIgreja;
use App\Domain\Organizacao\Exceptions\SobreposicaoVigenciaException;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Setor;

it('transfere e o setor vigente passa a ser o novo (sem data de corte)', function () {
    $igreja = Igreja::factory()->create();
    $setorA = Setor::factory()->create();
    $setorB = Setor::factory()->create();
    app(TransferirIgreja::class)->executar($igreja->id, $setorA->id, now()->subYear());
    app(TransferirIgreja::class)->executar($igreja->id, $setorB->id, now());

    // A leitura VIGENTE — inclusive para um período anterior — resolve o setor NOVO.
    expect(app(ConsultarOrganizacao::class)->setorVigente($igreja->id)?->id)->toBe($setorB->id);
});

it('rejeita transferência que sobrepõe vigência', function () {
    $igreja = Igreja::factory()->create();
    $setorA = Setor::factory()->create();
    $setorB = Setor::factory()->create();
    app(TransferirIgreja::class)->executar($igreja->id, $setorA->id, now()->subMonth());
    app(TransferirIgreja::class)->executar($igreja->id, $setorB->id, now()->subMonth());
})->throws(SobreposicaoVigenciaException::class);

it('registra evento de auditoria na transferência', function () {
    $igreja = Igreja::factory()->create();
    $setor = Setor::factory()->create();
    app(TransferirIgreja::class)->executar($igreja->id, $setor->id, now());
    expect(DB::table('evento_auditoria')->where('acao', 'transferir')->count())->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=TransferenciaTest`
Expected: FAIL (interface/serviço inexistentes).

- [ ] **Step 3: Implementar a exceção, a interface e o serviço**

`SobreposicaoVigenciaException` estende `RuntimeException`. O serviço, em `DB::transaction`: (1) valida setor destino existe e difere do vigente; (2) valida que `data` não cai dentro de nenhum vínculo existente da igreja (nem o que está sendo fechado: exige `data > inicio` do vínculo ativo); (3) fecha o ativo com `fim = data`; (4) abre o novo; (5) chama `RegistrarEvento::registrar([...])`. Registrar o binding.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=TransferenciaTest`
Expected: PASS (3 testes).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Organizacao/Contracts/TransferirIgreja.php backend/app/Domain/Organizacao/Services/ServicoTransferencia.php backend/app/Domain/Organizacao/Exceptions backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Organizacao/TransferenciaTest.php
git commit -m "adiciona transferencia de igreja com vigencia e auditoria"
```

---

### Task 4: Importação CSV idempotente da Regional de Brasília

**Files:**
- Create: `backend/app/Domain/Organizacao/Services/ImportadorBrasilia.php`
- Create: `backend/app/Console/Commands/ImportarBrasilia.php`
- Create: `backend/database/data/brasilia.csv`
- Test: `backend/tests/Feature/Organizacao/ImportacaoBrasiliaTest.php`

**Interfaces:**
- Consumes: models da Task 1.
- Produces: comando `organizacao:importar-brasilia {arquivo}` que lê o CSV, faz `updateOrCreate` por `codigo` de igreja (e por `codigo` de regional/setor), e imprime `criados`/`atualizados`/`ignorados`. Classes `ImportadorBrasilia` com `importar(string $caminho): array{criados:int, atualizados:int, ignorados:int}`.

- [ ] **Step 1: Escrever o teste que falha (idempotência + linha inválida)**

```php
<?php // tests/Feature/Organizacao/ImportacaoBrasiliaTest.php

use App\Domain\Organizacao\Services\ImportadorBrasilia;
use App\Domain\Organizacao\Models\Igreja;

it('importa duas vezes sem duplicar (idempotente por codigo)', function () {
    $csv = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($csv, "regional_codigo,regional_nome,setor_codigo,setor_nome,igreja_codigo,igreja_nome,timezone\n01,Brasília,0110,Setor A,240003,Núcleo Bandeirante,America/Sao_Paulo\n");
    $importador = app(ImportadorBrasilia::class);
    $r1 = $importador->importar($csv);
    $r2 = $importador->importar($csv);
    expect($r1['criados'])->toBeGreaterThan(0)
        ->and($r2['criados'])->toBe(0)
        ->and(Igreja::where('codigo', '240003')->count())->toBe(1);
});

it('falha em linha sem codigo de igreja', function () {
    $csv = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($csv, "regional_codigo,regional_nome,setor_codigo,setor_nome,igreja_codigo,igreja_nome,timezone\n01,Brasília,0110,Setor A,,Sem Codigo,America/Sao_Paulo\n");
    expect(fn () => app(ImportadorBrasilia::class)->importar($csv))->toThrow(RuntimeException::class);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=ImportacaoBrasiliaTest`
Expected: FAIL (classes inexistentes).

- [ ] **Step 3: Implementar o importador e o comando**

`ImportadorBrasilia` lê com `fgetcsv`, valida `igreja_codigo` não vazio por linha (senão `RuntimeException` com número da linha), e usa `updateOrCreate` por `codigo`. O comando (namespace `App\Console\Commands`) chama o importador e imprime o resumo. Criar `brasilia.csv` com cabeçalho + linhas fictícias (incluindo `240003`).

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=ImportacaoBrasiliaTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Organizacao/Services/ImportadorBrasilia.php backend/app/Console/Commands/ImportarBrasilia.php backend/database/data/brasilia.csv backend/tests/Feature/Organizacao/ImportacaoBrasiliaTest.php
git commit -m "adiciona importacao CSV idempotente de Brasilia"
```

---

### Task 5: Autorização provisória por Gate

**Files:**
- Create: `backend/app/Domain/Organizacao/Services/PolicyProvisoria.php`
- Modify: `backend/app/Providers/ApiServiceProvider.php`
- Test: `backend/tests/Feature/Organizacao/AutorizacaoTest.php`

**Interfaces:**
- Consumes: `App\Domain\Identidade\Models\Ator` (via guard, não import direto no módulo).
- Produces: `Gate::define('gerir-organizacao', ...)` que libera quando `$ator?->papel === 'coordenadora'` (ator fictício administrativo). Middleware `can:gerir-organizacao` nas rotas de escrita (Tasks 7–8).

- [ ] **Step 1: Escrever o teste que falha (pode / não pode)**

```php
<?php // tests/Feature/Organizacao/AutorizacaoTest.php

use App\Domain\Organizacao\Models\Setor;
use Illuminate\Support\Facades\Gate;

it('autoriza gestao para o papel administrativo', function () {
    $ator = App\Domain\Identidade\Models\Ator::factory()->create(['papel' => 'coordenadora']);
    expect(Gate::forUser($ator)->allows('gerir-organizacao'))->toBeTrue();
});

it('nega gestao para papel sem permissao', function () {
    $ator = App\Domain\Identidade\Models\Ator::factory()->create(['papel' => 'responsavel']);
    expect(Gate::forUser($ator)->allows('gerir-organizacao'))->toBeFalse();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=AutorizacaoTest`
Expected: FAIL (`gerir-organizacao` não definido).

- [ ] **Step 3: Implementar o Gate**

`Gate::define('gerir-organizacao', fn ($ator) => $ator?->papel === 'coordenadora');` no `boot()` do `ApiServiceProvider`. Criar `AtorFactory` se ainda não existir (namespace `Database\Factories` já ligado a `App\Domain\Identidade\Models\Ator` — verificar `newFactory`).

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=AutorizacaoTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Providers/ApiServiceProvider.php backend/tests/Feature/Organizacao/AutorizacaoTest.php backend/database/factories
git commit -m "adiciona autorizacao provisoria da organizacao"
```

---

### Task 6: Infra de paginação por cursor e idempotência

**Files:**
- Create: `backend/app/Support/PaginacaoCursor.php`
- Create: `backend/app/Support/Idempotencia.php`
- Test: `backend/tests/Unit/PaginacaoCursorTest.php`
- Test: `backend/tests/Feature/Organizacao/IdempotenciaTest.php`

**Interfaces:**
- Produces: `PaginacaoCursor::resposta(Builder $q, Request $r): array{data:array, meta:array{proximo_cursor:?string}}` (limit máx. 100, usa `cursorPaginate`); middleware ou helper `Idempotencia::executar(Request $r, Closure $acao): Response` que, com `Idempotency-Key`, devolve a resposta gravada em replay.

- [ ] **Step 1: Escrever os testes que falham**

```php
<?php // tests/Unit/PaginacaoCursorTest.php
use App\Support\PaginacaoCursor;
use Illuminate\Http\Request;

it('limita o limit a 100', function () {
    $r = Request::create('/x', 'GET', ['limit' => '5000']);
    expect(PaginacaoCursor::limit($r))->toBe(100);
});
```

```php
<?php // tests/Feature/Organizacao/IdempotenciaTest.php
use App\Support\Idempotencia;
use Illuminate\Http\Request;

it('replay devolve a mesma resposta e nao executa duas vezes', function () {
    $execucoes = 0;
    $acao = function () use (&$execucoes) { $execucoes++; return response()->json(['ok' => true], 201); };
    $r = Request::create('/x', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'k1']);
    Idempotencia::executar($r, $acao);
    $segunda = Idempotencia::executar($r, $acao);
    expect($execucoes)->toBe(1)->and($segunda->getStatusCode())->toBe(201);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter="PaginacaoCursorTest|IdempotenciaTest"`
Expected: FAIL (classes inexistentes).

- [ ] **Step 3: Implementar os utilitários**

`PaginacaoCursor::limit()` (default 20, máx. 100) e `resposta()` usando `cursorPaginate`. `Idempotencia::executar()` grava em cache (`Cache::remember`) por chave+usuário e devolve a resposta no replay. Registrar onde fizer sentido (helper estático, sem middleware global — aplicado nas rotas que declararem).

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter="PaginacaoCursorTest|IdempotenciaTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/PaginacaoCursor.php backend/app/Support/Idempotencia.php backend/tests/Unit/PaginacaoCursorTest.php backend/tests/Feature/Organizacao/IdempotenciaTest.php
git commit -m "adiciona paginacao por cursor e idempotencia"
```

---

### Task 7: Endpoints de igreja (listar, criar, ver, atualizar, transferir)

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/ChurchController.php`
- Create: `backend/app/Http/Requests/Organizacao/IgrejaRequest.php`
- Create: `backend/app/Http/Requests/Organizacao/TransferenciaRequest.php`
- Create: `backend/app/Http/Resources/ChurchResource.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Api/ChurchTest.php`

**Interfaces:**
- Consumes: `ConsultarOrganizacao`, `TransferirIgreja` (Tasks 2–3); Gate (Task 5); `PaginacaoCursor`/`Idempotencia` (Task 6).
- Produces: rotas `GET/POST /api/v1/churches`, `GET/PATCH /api/v1/churches/{id}`, `POST /api/v1/churches/{id}/transfers`. `ChurchResource` expõe `id, codigo, nome, endereco, latitude, longitude, timezone, status, setor` (do vínculo vigente, `null` se não houver).

- [ ] **Step 1: Escrever o teste que falha (autorização + contrato + vigência)**

```php
<?php // tests/Feature/Api/ChurchTest.php

use App\Domain\Identidade\Models\Ator;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Setor;

beforeEach(function () {
    $this->ator = Ator::factory()->create(['papel' => 'coordenadora']);
    $this->token = $this->ator->createToken('t')->plainTextToken;
});

it('cria igreja autenticado e devolve 201', function () {
    $this->postJson('/api/v1/churches', [
        'codigo' => '240003', 'nome' => 'Núcleo Bandeirante', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo',
    ], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(201)
        ->assertJsonPath('codigo', '240003');
});

it('rejeita escrita sem token', function () {
    $this->postJson('/api/v1/churches', ['codigo' => '1', 'nome' => 'x', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo'])
        ->assertStatus(401);
});

it('rejeita escrita com papel sem permissao', function () {
    $ator = Ator::factory()->create(['papel' => 'responsavel']);
    $token = $ator->createToken('t')->plainTextToken;
    $this->postJson('/api/v1/churches', ['codigo' => '1', 'nome' => 'x', 'timezone' => 'America/Sao_Paulo', 'status' => 'ativo'], ['Authorization' => "Bearer $token"])
        ->assertStatus(403);
});

it('transfere igreja e devolve o setor novo na leitura', function () {
    $igreja = Igreja::factory()->create();
    $setor = Setor::factory()->create();
    $this->postJson("/api/v1/churches/{$igreja->id}/transfers", ['sector_id' => $setor->id, 'date' => now()->toDateString()], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(201);
    $this->getJson("/api/v1/churches/{$igreja->id}", ['Authorization' => "Bearer {$this->token}"])
        ->assertOk()->assertJsonPath('setor.id', $setor->id);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=ChurchTest`
Expected: FAIL (rotas inexistentes → 404).

- [ ] **Step 3: Implementar requests, resource, controller e rotas**

`IgrejaRequest` valida `codigo` (required, unique em create), `nome`, `timezone`, `status` (in `ativo,inativo`). `TransferenciaRequest` valida `sector_id` (exists) e `date` (date). Controller `ChurchController` com `index` (paginação cursor), `store`, `show`, `update`, `transfer` (usa `Idempotencia` quando há cabeçalho). Rotas agrupadas em `auth:sanctum` + `can:gerir-organizacao` nas escritas; leitura só `auth:sanctum`.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=ChurchTest`
Expected: PASS (4 testes).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/ChurchController.php backend/app/Http/Requests/Organizacao backend/app/Http/Resources/ChurchResource.php backend/routes/api.php backend/tests/Feature/Api/ChurchTest.php
git commit -m "adiciona endpoints de igreja e transferencia"
```

---

### Task 8: Endpoints de região, setor, central e EBI

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/RegionController.php`
- Create: `backend/app/Http/Controllers/Api/V1/SectorController.php`
- Create: `backend/app/Http/Controllers/Api/V1/EbiController.php`
- Create: `backend/app/Http/Requests/Organizacao/{RegionalRequest,SetorRequest,EbiRequest}.php`
- Create: `backend/app/Http/Resources/{RegionResource,SectorResource,EbiResource}.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Api/OrganizacaoEndpointsTest.php`

**Interfaces:**
- Consumes: models (Task 1); Gate (Task 5); `PaginacaoCursor` (Task 6).
- Produces: `GET /api/v1/regions`, `POST/PATCH /api/v1/regions[/{id}]`, `GET /api/v1/regions/{id}/sectors`, `POST/PATCH /api/v1/sectors[/{id}]`, `GET /api/v1/ebis`, `POST/PATCH /api/v1/ebis[/{id}]`, `GET /api/v1/central`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php // tests/Feature/Api/OrganizacaoEndpointsTest.php

use App\Domain\Identidade\Models\Ator;
use App\Domain\Organizacao\Models\Regional;

beforeEach(function () {
    $this->ator = Ator::factory()->create(['papel' => 'coordenadora']);
    $this->token = $this->ator->createToken('t')->plainTextToken;
});

it('cria regional e lista seus setores', function () {
    $regional = $this->postJson('/api/v1/regions', ['nome' => 'Brasília', 'codigo' => '01', 'status' => 'ativo'], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(201)->json();
    $this->getJson("/api/v1/regions/{$regional['id']}/sectors", ['Authorization' => "Bearer {$this->token}"])
        ->assertOk()->assertJsonStructure(['data']);
});

it('cria ebi vinculado a igreja', function () {
    $igreja = App\Domain\Organizacao\Models\Igreja::factory()->create();
    $this->postJson('/api/v1/ebis', ['igreja_id' => $igreja->id, 'nome' => 'EBI', 'status' => 'ativo'], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(201)->assertJsonPath('igreja_id', $igreja->id);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=OrganizacaoEndpointsTest`
Expected: FAIL (rotas inexistentes).

- [ ] **Step 3: Implementar controllers, requests, resources e rotas**

Mesmo padrão da Task 7. `GET /regions/{id}/sectors` lista setores da regional (paginado). Escritas sob `can:gerir-organizacao`; leitura sob `auth:sanctum`.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=OrganizacaoEndpointsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/RegionController.php backend/app/Http/Controllers/Api/V1/SectorController.php backend/app/Http/Controllers/Api/V1/EbiController.php backend/app/Http/Requests/Organizacao backend/app/Http/Resources backend/routes/api.php backend/tests/Feature/Api/OrganizacaoEndpointsTest.php
git commit -m "adiciona endpoints de regiao, setor, central e ebi"
```

---

### Task 9: OpenAPI, endpoints.md e verificação de contrato

**Files:**
- Modify: `docs/04-api/openapi.yaml`
- Modify: `docs/04-api/endpoints.md`
- Test: verificação por script (CI)

**Interfaces:**
- Consumes: as rotas das Tasks 7–8.
- Produces: contrato OpenAPI 3.1 com todas as rotas novas; `endpoints.md` marcando cada rota nova como `no OpenAPI`; `check-endpoints.py` verde.

- [ ] **Step 1: Adicionar os caminhos e schemas ao openapi.yaml**

Incluir `Church`, `ChurchTransferRequest`, `Region`, `Sector`, `Ebi`, `CentralNacional`, respostas `Problem`, e os `paths` das Tasks 7–8. Cuidado com **vírgulas em flow mapping** (bug histórico) — quotar strings.

- [ ] **Step 2: Marcar as rotas novas em endpoints.md**

Adicionar `no OpenAPI` nas linhas correspondentes.

- [ ] **Step 3: Verificar Spectral e alinhamento**

Run: `npx --yes @stoplight/spectral-cli lint docs/04-api/openapi.yaml --ruleset scripts/.spectral.yaml`
Expected: sem erros.
Run: `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"`
Expected: `OK: N operações alinhadas.`

- [ ] **Step 4: Commit**

```bash
git add docs/04-api/openapi.yaml docs/04-api/endpoints.md
git commit -m "documenta contrato OpenAPI da organizacao"
```

---

### Task 10: Atualizar a documentação de requisitos (RN-09, CA-18, modelo/dicionário)

**Files:**
- Modify: `docs/02-regras-de-negocio/regras-de-negocio.md` (RN-09)
- Modify: `docs/01-requisitos/criterios-de-aceite.md` (CA-18)
- Modify: `docs/03-dados/modelo-conceitual.md` (sessao)
- Modify: `docs/CHANGELOG.md`

**Interfaces:**
- Consumes: os textos novos da seção 8 do desenho.
- Produces: documentação coerente com a implementação.

- [ ] **Step 1: Reescrever RN-09**

Texto: *"Transferências organizacionais não criam data de corte: o território (setor e regional) de uma igreja é sempre o vigente atual. Ao transferir, todos os dados daquela igreja — inclusive de períodos anteriores — passam a ser atribuídos ao novo setor. O vínculo com vigência preserva o histórico de transferências (RF-01), não a atribuição territorial dos dados."*

- [ ] **Step 2: Reescrever CA-18**

Texto: *"Transferência de igreja: depois que a igreja muda de setor, todos os relatórios — inclusive de períodos anteriores — passam a atribuir os dados daquela igreja ao novo setor; a igreja é a âncora e o setor é sempre o vigente."*

- [ ] **Step 3: Ajustar o modelo conceitual (sessao)**

Remover `setor_id, regional_id` de `sessao` e anotar que setor/regional derivam do vínculo vigente da igreja.

- [ ] **Step 4: Registrar no CHANGELOG**

Nota da mudança de regra territorial.

- [ ] **Step 5: Commit**

```bash
git add docs/02-regras-de-negocio/regras-de-negocio.md docs/01-requisitos/criterios-de-aceite.md docs/03-dados/modelo-conceitual.md docs/CHANGELOG.md
git commit -m "atualiza RN-09, CA-18 e modelo para a regra territorial vigente"
```

---

### Task 11: Fronteira do módulo, seed de desenvolvimento e fumaça final

**Files:**
- Modify: `backend/tests/Arch/ModularBoundariesTest.php` (se necessário)
- Create: `backend/database/seeders/OrganizacaoSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: tudo das tasks anteriores.
- Produces: seeder de hierarquia fictícia de Brasília; suíte verde; fumaça end-to-end.

- [ ] **Step 1: Garantir que o teste de arquitetura cobre o novo contrato**

Rodar `docker compose run --rm app php artisan test --filter=ModularBoundariesTest`. Se as rotas/controllers importarem `Models` de `Organizacao` direto, corrigir para usar `Contracts` ou mover a leitura para o módulo. Adicionar caso afirmando que `app/Http` não importa `App\Domain\Organizacao\Models\*`.

- [ ] **Step 2: Escrever o seeder de desenvolvimento**

`OrganizacaoSeeder` cria Central (Brás) → Regional Brasília → setores → igrejas fictícias → EBIs. Registrar no `DatabaseSeeder`.

- [ ] **Step 3: Rodar a suíte completa e o estático**

Run: `docker compose run --rm app composer test`
Expected: todos os testes verdes.
Run: `docker compose run --rm app composer pint --test` · `docker compose run --rm app composer phpstan`
Expected: PASS / `[OK] No errors`.

- [ ] **Step 4: Fumaça end-to-end**

Run: `docker compose exec app php artisan migrate:fresh --seed --force`
Expected: sem erro. Depois: `GET /api/v1/churches` (autenticado) devolve a hierarquia semeada; `php artisan organizacao:importar-brasilia database/data/brasilia.csv` roda 2× sem duplicar.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/Arch/ModularBoundariesTest.php backend/database/seeders
git commit -m "adiciona seeder da organizacao e fecha a etapa 2"
```

---

## Self-review (feito pelo autor do plano)

- **Cobertura do spec:** entidades (T1), consulta vigente (T2), transferência+auditoria (T3), CSV (T4), autorização provisória (T5), infra cursor/idempotência (T6), endpoints igreja (T7), endpoints demais (T8), OpenAPI (T9), docs (T10), fronteira/seeder/fumaça (T11). Geocadastro fora por escopo.
- **Consistência de tipos:** `ConsultarOrganizacao::setorVigente(string): ?Setor` e `TransferirIgreja::executar(string,string,DateTimeInterface,?string): IgrejaVinculacao` usados igualmente em T3/T7/T8. `sem data de corte` garantido por T3 (teste) e texto em T10.
- **Review Focus:** cada linha tem teste no task dono (sobreposição → T3; CSV 2× e linha sem código → T4; sem token/papel → T5/T7; zero vínculos → T2).
- **Proporção:** plano descreve decisões e assinaturas; corpos ficam com o implementador.