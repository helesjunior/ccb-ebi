# Identidade e acesso (Etapa 3 / E01) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Substituir o andaime de acesso da fundação (`AutenticadorFicticio` + `Gate` provisório) por identidade real: `usuario` (ULID), autenticação com e-mail verificado e MFA obrigatório (CA-15), autorização RBAC+ABAC com `X-Acting-Scope` (CA-03, RN-11) e a equipe do EBI (RF-05).

**Architecture:** Módulo `App\Domain\Identidade` (ADR-0002) com contratos públicos consumidos por `app/Http`. A tabela `atores` evolui **in-place** para `usuarios` (ULID); papéis passam para `vinculo_acesso` com vigência `[inicio, fim)` (`fim IS NULL` = ativo), espelhando `igreja_vinculacao` da Etapa 2. Autorização em Abordagem C: Policies como superfície (`can:`) delegando a um núcleo único `ResolverEscopo`; o middleware `X-ActingScope` resolve e revalida o contexto de atuação. API REST contract-first em `/api/v1` (OpenAPI é a fonte da verdade).

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, Redis 7, Sanctum, Horizon, Pest 4, PHPStan nível 8, Pint.

**Spec:** `docs/superpowers/specs/2026-11-09-identidade-e-acesso-etapa-3-design.md`

## Global Constraints

- PHP 8.4 **sempre** em contêiner: rodar `docker compose run --rm app <cmd>`. Nunca o PHP do host (8.3).
- Testes: `docker compose run --rm app composer test`. Pint: `composer pint --test`. PHPStan: `composer phpstan`. Todos verdes antes de cada commit.
- Monólito modular (ADR-0002): nada fora de `App\Domain\Identidade` importa `App\Domain\Identidade\Models\*`. `app/Http` só consome `App\Domain\Identidade\Contracts\*`. O `tests/Arch/ModularBoundariesTest.php` **deve continuar verde**.
- IDs opacos **ULID** (coluna `ulid('id')->primary()`); rotas e campos da API em inglês; domínio e mensagens de domínio em português.
- Erros em `application/problem+json` via `App\Support\ProblemException` (nunca `abort()`/`response()->json` de erro direto).
- Escritas críticas aceitam `Idempotency-Key` (middleware `IdempotenciaMiddleware`).
- E-mail transacional pela fila (Redis/Horizon); em dev, Mailpit.
- Sem passkey/WebAuthn, sem federação real, sem WhatsApp, sem captura de finalidade (`*` da matriz) — ver Não-objetivos do spec.
- Sanctum é o guard (`auth:sanctum`); o provider `atores` passa a apontar para `App\Domain\Identidade\Models\Usuario`.

## Review Focus

Entradas/condições que o spec implica mas os testes nomeados podem não cobrir; cada uma ganha teste na task dona.

1. **Login de perfil que exige MFA sem fator cadastrado** (primeiro acesso do convidado): deve emitir só o token restrito de inscrição, jamais sessão plena. (Task 9/10)
2. **`X-Acting-Scope` apontando para um nó irmão** (mesmo nível, fora da subárvore): deve negar (horizontal). (Task 12)
3. **Recuperação de senha com e-mail inexistente vs. existente**: resposta e status idênticos (202), sem enumeração. (Task 6)
4. **Replay de `POST /invitations/{id}/accept` com o mesmo token**: o token é de uso único; o segundo uso falha. (Task 15)
5. **Migração `atores`→`usuarios`**: `down()` restaura o estado; uma linha existente é preservada com o mesmo e-mail. (Task 2)

---

### Task 1: Catálogo de papéis — modelo, migração e seeder

**Files:**
- Create: `backend/database/migrations/2026_11_09_200001_create_papeis_table.php`
- Create: `backend/app/Domain/Identidade/Models/Papel.php`
- Create: `backend/app/Domain/Identidade/Database/Factories/PapelFactory.php`
- Create: `backend/database/seeders/PapelSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Test: `backend/tests/Feature/Identidade/EsquemaPapeisTest.php`

**Interfaces:**
- Produces: `App\Domain\Identidade\Models\Papel` (usa `HasUlids`, tabela `papeis`, colunas `codigo` UNIQUE e `descricao`); `PapelSeeder` que cria os 11 códigos: `super_admin`, `adm_regional`, `adm_setor`, `adm_igreja`, `ministerio_central`, `ministerio_regional`, `ministerio_setorial`, `ministerio_local`, `coordenadora`, `auxiliar`, `responsavel`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/EsquemaPapeisTest.php

use App\Domain\Identidade\Models\Papel;
use Database\Seeders\PapelSeeder;

it('semeia os onze papeis com codigo unico', function () {
    $this->seed(PapelSeeder::class);

    expect(Papel::query()->count())->toBe(11)
        ->and(Papel::query()->where('codigo', 'coordenadora')->exists())->toBeTrue()
        ->and(Papel::query()->where('codigo', 'ministerio_central')->exists())->toBeTrue();

    // Reexecutar nao duplica.
    $this->seed(PapelSeeder::class);
    expect(Papel::query()->count())->toBe(11);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=EsquemaPapeis`
Expected: FAIL — tabela `papeis`/classe `Papel` não existe.

- [ ] **Step 3: Implementar migração, modelo, factory e seeder**

Migração `papeis`: `ulid('id')->primary()`, `string('codigo')->unique()`, `string('descricao')`, `timestamps()`.
`Papel` (modelo): `use HasUlids;`, `protected $table = 'papeis';`, `$fillable = ['codigo', 'descricao'];`, `newFactory()`.
`PapelSeeder`: `foreach` sobre o mapa código→descrição (os 11 acima), `Papel::updateOrCreate(['codigo' => $codigo], ['descricao' => $desc]);`.
`DatabaseSeeder`: adicionar `$this->call(PapelSeeder::class);` antes de `AtorSeeder`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=EsquemaPapeis`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_11_09_200001_create_papeis_table.php backend/app/Domain/Identidade/Models/Papel.php backend/app/Domain/Identidade/Database/Factories/PapelFactory.php backend/database/seeders/PapelSeeder.php backend/database/seeders/DatabaseSeeder.php backend/tests/Feature/Identidade/EsquemaPapeisTest.php
git commit -m "feat(identidade): catalogo de papeis (RF-05)"
```

---

### Task 2: Migrar `atores` → `usuarios` (in-place, ULID)

**Files:**
- Create: `backend/database/migrations/2026_11_09_200002_create_usuarios_from_atores.php`
- Create: `backend/database/migrations/2026_11_09_200003_drop_atores_and_users.php`
- Create: `backend/app/Domain/Identidade/Models/Usuario.php`
- Create: `backend/app/Domain/Identidade/Database/Factories/UsuarioFactory.php`
- Modify: `backend/config/auth.php` (provider `atores` → model `Usuario`, tabela `usuarios`)
- Test: `backend/tests/Feature/Identidade/MigracaoUsuarioTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: `App\Domain\Identidade\Models\Usuario` (`HasUlids`, `HasApiTokens`, `Authenticatable`, tabela `usuarios`, `$fillable = ['nome','email_normalizado','email_verificado_em','senha','identidade_federada','status']`, `getAuthPassword(): string` retorna `senha`, `casts` de `email_verificado_em` => datetime); coluna `papel` **não existe mais** no usuário.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/MigracaoUsuarioTest.php

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Schema;

it('cria a tabela usuarios com a forma final e sem papel', function () {
    expect(Schema::hasTable('usuarios'))->toBeTrue()
        ->and(Schema::hasTable('atores'))->toBeFalse()
        ->and(Schema::hasColumn('usuarios', 'email_normalizado'))->toBeTrue()
        ->and(Schema::hasColumn('usuarios', 'senha'))->toBeTrue()
        ->and(Schema::hasColumn('usuarios', 'papel'))->toBeFalse();
});

it('persiste um usuario com id ULID', function () {
    $u = Usuario::factory()->create(['nome' => 'Maria']);
    expect($u->id)->toBeString()->and(strlen($u->id))->toBe(26);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=MigracaoUsuario`
Expected: FAIL — `usuarios`/`Usuario` não existe.

- [ ] **Step 3: Implementar migrações e modelo**

Migração `create_usuarios_from_atores`: cria `usuarios` (ulid id, nome, `email_normalizado` unique, `email_verificado_em` nullable, `senha` nullable, `identidade_federada` nullable, `status` default `ativo`, timestamps); copia linhas de `atores` (se existir) mapeando `email`→`email_normalizado` (usar `mb_strtolower(trim(email))`), `senha`→`senha`, `email_verificado_em` = now() (atores semeados são pré-verificados), gerando novo ULID (use `Illuminate\Support\Str::ulid()`); status `ativo`.
Migração `drop_atores_and_users`: `dropIfExists('atores')`, `dropIfExists('users')`. `down()` recria `atores` vazia e `down()` da primeira dropa `usuarios` e recria `atores` (esqueleto) — suficiente para reverter.
`Usuario`: `getAuthPassword()` retorna `$this->senha`; casts `email_verificado_em` datetime; helper `emailNormalizado(string $email): string` estático (`mb_strtolower(trim($email))`).
`config/auth.php`: provider `atores` → `['driver' => 'eloquent', 'model' => Usuario::class]` (importar `App\Domain\Identidade\Models\Usuario`).

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=MigracaoUsuario`
Expected: PASS.

- [ ] **Step 5: Rodar a suíte inteira e ajustar referências a `Ator`**

Run: `docker compose run --rm app composer test`
Expected: vermelho nas referências a `Ator`/`papel`. Ajustar: substituir `App\Domain\Identidade\Models\Ator` por `Usuario` em controllers/resources/factories/seeders/testes; renomear `AtorFactory`→`UsuarioFactory` (model `Usuario`); `AtorSeeder`→`UsuarioSeeder` (email `coordenadora@ebi.test`, sem campo `papel`); `AtorResource`→`UsuarioResource` (sem campo `papel`). Green ao fim.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_11_09_200002_create_usuarios_from_atores.php backend/database/migrations/2026_11_09_200003_drop_atores_and_users.php backend/app/Domain/Identidade backend/config/auth.php backend/database backend/app/Http/Resources backend/tests
git commit -m "refactor(identidade): atores vira usuario (ULID) in-place"
```

---

### Task 3: `vinculo_acesso` com vigência (papel + escopo)

**Files:**
- Create: `backend/database/migrations/2026_11_09_200004_create_vinculo_acesso_table.php`
- Create: `backend/app/Domain/Identidade/Models/VinculoAcesso.php`
- Create: `backend/app/Domain/Identidade/Database/Factories/VinculoAcessoFactory.php`
- Test: `backend/tests/Feature/Identidade/VinculoAcessoTest.php`

**Interfaces:**
- Consumes: `Usuario` (Task 2), `Papel` (Task 1).
- Produces: `App\Domain\Identidade\Models\VinculoAcesso` (`HasUlids`, tabela `vinculo_acesso`, fillable `usuario_id, papel_id, tipo_escopo, escopo_id, inicio, fim, status`; casts `inicio`/`fim` => date; relações `usuario()` e `papel()`; `scopeVigente` = `whereNull('fim')`). Constantes `tipo_escopo`: `central, regional, setor, igreja, ebi`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/VinculoAcessoTest.php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\VinculoAcesso;
use Database\Seeders\PapelSeeder;

it('vigente e o vinculo sem fim', function () {
    $this->seed(PapelSeeder::class);
    $papel = Papel::query()->where('codigo', 'coordenadora')->firstOrFail();

    $u = \App\Domain\Identidade\Models\Usuario::factory()->create();
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel->id, 'tipo_escopo' => 'ebi', 'escopo_id' => '01HZZZ', 'inicio' => '2026-01-01', 'fim' => null]);
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel->id, 'tipo_escopo' => 'ebi', 'escopo_id' => '01HYYY', 'inicio' => '2025-01-01', 'fim' => '2026-01-01']);

    expect($u->vinculosVigentes()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=VinculoAcesso`
Expected: FAIL — tabela/classe/relação inexistentes.

- [ ] **Step 3: Implementar migração, modelo, factory e relação no `Usuario`**

Migração: `ulid id`, `foreignUlid('usuario_id')->constrained('usuarios')`, `foreignUlid('papel_id')->constrained('papeis')`, `string('tipo_escopo')`, `string('escopo_id')` (opaco; não FK — cruza módulos), `date('inicio')`, `date('fim')->nullable()`, `string('status')->default('ativo')`, timestamps; `index(['usuario_id','fim'])`; índice único parcial `vinculo_acesso_vigente_unique ON vinculo_acesso (usuario_id, papel_id, tipo_escopo, escopo_id) WHERE fim IS NULL` (espelha a Etapa 2).
`VinculoAcesso`: relações `usuario()` (`belongsTo(Usuario::class)`), `papel()` (`belongsTo(Papel::class)`); scope `vigente` (`$q->whereNull('fim')`).
`Usuario::vinculosVigentes()`: `hasMany(VinculoAcesso::class)->whereNull('fim')`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=VinculoAcesso`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_11_09_200004_create_vinculo_acesso_table.php backend/app/Domain/Identidade/Models/VinculoAcesso.php backend/app/Domain/Identidade/Database/Factories/VinculoAcessoFactory.php backend/app/Domain/Identidade/Models/Usuario.php backend/tests/Feature/Identidade/VinculoAcessoTest.php
git commit -m "feat(identidade): vinculo_acesso com vigencia (RN-11)"
```

---

### Task 4: Resolver de escopo — o núcleo do ABAC territorial

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/ResolverEscopo.php`
- Create: `backend/app/Domain/Identidade/Services/ResolvedorEscopo.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Unit/ResolvedorEscopoTest.php`

**Interfaces:**
- Consumes: `Usuario`, `VinculoAcesso` (Task 3).
- Produces: interface `ResolverEscopo` com `cobre(string $tipoEscopoVinculo, string $escopoIdVinculo, string $tipoRecurso, string $recursoId): bool` — decide se um recurso está **dentro da subárvore** do nó do vínculo. A hierarquia Central→Regional→Setor→Igreja→EBI é obtida por um contrato da Organização (ver nota). Devolve `true` quando o nível do recurso está abaixo (ou igual) e pertence ao nó.

**Nota de fronteira (ADR-0002):** o `ResolvedorEscopo` **não** importa modelos da Organização. Ele usa um contrato novo `App\Domain\Organizacao\Contracts\ConsultarHierarquia` (criado nesta task) com `ancestrais(string $tipo, string $id): array<string,string>` devolvendo o mapa `tipo => id` do nó e seus ascendentes (ex.: igreja → `['igreja'=>id,'setor'=>id,'regional'=>id,'central'=>id]`). A task cria esse contrato e a implementação (`ConsultaHierarquia`) no módulo Organização.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Unit/ResolvedorEscopoTest.php

use App\Domain\Identidade\Services\ResolvedorEscopo;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;

it('vinculo de setor cobre igreja descendente', function () {
    $hierarquia = Mockery::mock(ConsultarHierarquia::class);
    $hierarquia->shouldReceive('ancestrais')->with('igreja', 'ig1')
        ->andReturn(['igreja' => 'ig1', 'setor' => 'set1', 'regional' => 'reg1', 'central' => 'c1']);

    $resolvedor = new ResolvedorEscopo($hierarquia);

    expect($resolvedor->cobre('setor', 'set1', 'igreja', 'ig1'))->toBeTrue()
        ->and($resolvedor->cobre('setor', 'set2', 'igreja', 'ig1'))->toBeFalse(); // irmao
        ->and($resolvedor->cobre('regional', 'reg1', 'igreja', 'ig1'))->toBeTrue();
});

it('vinculo de igreja nao cobre a propria igreja em outro setor', function () {
    $hierarquia = Mockery::mock(ConsultarHierarquia::class);
    $hierarquia->shouldReceive('ancestrais')->with('igreja', 'igX')
        ->andReturn(['igreja' => 'igX', 'setor' => 'set9', 'regional' => 'reg9', 'central' => 'c1']);
    $resolvedor = new ResolvedorEscopo($hierarquia);

    expect($resolvedor->cobre('igreja', 'igX', 'igreja', 'igX'))->toBeTrue()
        ->and($resolvedor->cobre('igreja', 'igX', 'igreja', 'igY'))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=ResolvedorEscopo`
Expected: FAIL — classes inexistentes.

- [ ] **Step 3: Implementar contrato da Organização, resolver e binding**

`App\Domain\Organizacao\Contracts\ConsultarHierarquia` com `ancestrais(string $tipo, string $id): array<string,string>`.
`ConsultaHierarquia` (módulo Organização): usa os modelos e as relações já existentes (`Igreja->vinculacaoVigente->setor->regional->centralNacional`; setores→regional; regionais→central; EBI→igreja→…). Devolve o mapa do nó e ascendentes. Lança `RuntimeException` se o nó não existir.
`ResolvedorEscopo implements ResolverEscopo`: obtém `ancestrais($tipoRecurso, $recursoId)`; se `$ancestrais[$tipoEscopoVinculo] ?? null === $escopoIdVinculo`, retorna `true`; senão `false`. Registra o binding de `ConsultarHierarquia` e `ResolverEscopo` no `DomainServiceProvider`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=ResolvedorEscopo`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade/Contracts/ResolverEscopo.php backend/app/Domain/Identidade/Services/ResolvedorEscopo.php backend/app/Domain/Organizacao/Contracts/ConsultarHierarquia.php backend/app/Domain/Organizacao/Services/ConsultaHierarquia.php backend/app/Providers/DomainServiceProvider.php backend/tests/Unit/ResolvedorEscopoTest.php
git commit -m "feat(identidade): nucleo de escopo por descendencia (CA-03)"
```

---

### Task 5: Contexto de atuação e middleware `X-Acting-Scope`

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/ResolverContextoAtuacao.php`
- Create: `backend/app/Domain/Identidade/Services/ContextoAtuacao.php`
- Create: `backend/app/Domain/Identidade/Services/ResolvedorContextoAtuacao.php`
- Create: `backend/app/Http/Middleware/ResolveActingScope.php`
- Modify: `backend/bootstrap/app.php` (registrar o middleware no grupo `api`, após `auth:sanctum` quando aplicável)
- Test: `backend/tests/Feature/Identidade/ActingScopeTest.php`

**Interfaces:**
- Consumes: `Usuario`, `VinculoAcesso` (Task 3).
- Produces: `ContextoAtuacao` (DTO: `papelCodigo: string`, `tipoEscopo: string`, `escopoId: string`); `ResolverContextoAtuacao::resolver(Usuario $u, ?string $header): ContextoAtuacao` — se `$header` nulo e o usuário tem **um** vínculo vigente, usa-o; se tem vários, lança `ProblemException('acting_scope_obrigatorio', ..., 422, ...)`; o header `papel=<codigo>;escopo=<tipo>:<id>` deve corresponder a um vínculo vigente, senão `ProblemException('acting_scope_invalido', ..., 403, ...)`. O middleware guarda o `ContextoAtuacao` em `$request->attributes->set('contexto_atuacao', $ctx)`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/ActingScopeTest.php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Identidade\Services\ResolvedorContextoAtuacao;
use App\Support\ProblemException;
use Database\Seeders\PapelSeeder;

beforeEach(fn () => $this->seed(PapelSeeder::class));

it('usa o unico vinculo quando nao ha header', function () {
    $u = Usuario::factory()->create();
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => \App\Domain\Identidade\Models\Papel::query()->where('codigo','coordenadora')->value('id'), 'tipo_escopo' => 'ebi', 'escopo_id' => 'e1']);

    $ctx = app(ResolvedorContextoAtuacao::class)->resolver($u, null);
    expect($ctx->papelCodigo)->toBe('coordenadora')->and($ctx->escopoId)->toBe('e1');
});

it('exige header quando ha mais de um vinculo', function () {
    $u = Usuario::factory()->create();
    $papel = \App\Domain\Identidade\Models\Papel::query()->where('codigo','coordenadora')->value('id');
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel, 'tipo_escopo' => 'ebi', 'escopo_id' => 'e1']);
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel, 'tipo_escopo' => 'ebi', 'escopo_id' => 'e2']);

    expect(fn () => app(ResolvedorContextoAtuacao::class)->resolver($u, null))
        ->toThrow(ProblemException::class);
});

it('rejeita escopo que nao pertence ao usuario', function () {
    $u = Usuario::factory()->create();
    $papel = \App\Domain\Identidade\Models\Papel::query()->where('codigo','coordenadora')->value('id');
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => $papel, 'tipo_escopo' => 'ebi', 'escopo_id' => 'e1']);

    expect(fn () => app(ResolvedorContextoAtuacao::class)->resolver($u, 'papel=coordenadora;escopo=ebi:e9'))
        ->toThrow(ProblemException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=ActingScope`
Expected: FAIL — classes/contrato inexistentes.

- [ ] **Step 3: Implementar DTO, resolver e middleware**

`ContextoAtuacao` (readonly: `papelCodigo`, `tipoEscopo`, `escopoId`).
`ResolvedorContextoAtuacao`: carrega `vinculosVigentes` com `papel`; resolve conforme as regras; parseia o header com regex `^papel=([a-z_]+);escopo=([a-z]+):(.+)$`.
`ResolveActingScope` (middleware): se há usuário autenticado, resolve e injeta o contexto; caso contrário passa (rotas públicas). Lança `ProblemException` conforme o resolver. Registrar o middleware no grupo `api` (após o guard) em `bootstrap/app.php` via `$middleware->api(append: [ResolveActingScope::class])`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=ActingScope`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade/Contracts/ResolverContextoAtuacao.php backend/app/Domain/Identidade/Services/ContextoAtuacao.php backend/app/Domain/Identidade/Services/ResolvedorContextoAtuacao.php backend/app/Http/Middleware/ResolveActingScope.php backend/bootstrap/app.php backend/tests/Feature/Identidade/ActingScopeTest.php
git commit -m "feat(identidade): contexto de atuacao via X-Acting-Scope (RN-11)"
```

---

### Task 6: Cadastro de responsável e verificação de e-mail

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/RegistrarConta.php`
- Create: `backend/app/Domain/Identidade/Contracts/VerificarEmail.php`
- Create: `backend/app/Domain/Identidade/Services/GerenciadorConta.php`
- Create: `backend/app/Domain/Identidade/Notifications/VerificacaoEmail.php`
- Create: `backend/app/Http/Controllers/Api/V1/RegisterController.php`
- Create: `backend/app/Http/Requests/Identidade/RegistrarRequest.php`
- Modify: `backend/routes/api.php` (rotas públicas `register-guardian`, `verify-email`, `email/resend`)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Identidade/CadastroTest.php`

**Interfaces:**
- Consumes: `Usuario` (Task 2), `Papel`/`PapelSeeder` (Task 1).
- Produces: `RegistrarConta::registrar(string $nome, string $email, string $senha): Usuario` (cria usuário `status=ativo`, `email_verificado_em=null`, dispara `VerificacaoEmail` pela fila); `VerificarEmail::verificar(string $token): void` (marca `email_verificado_em=now()`); `VerificarEmail::reenviar(string $email): void` (sempre 202, sem revelar existência).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/CadastroTest.php

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Notification;
use Database\Seeders\PapelSeeder;

it('cadastra responsavel, sem verificar, e envia e-mail', function () {
    Notification::fake();
    $this->seed(PapelSeeder::class);

    $this->postJson('/api/v1/auth/register-guardian', ['nome' => 'Ana', 'email' => 'Ana@Ex.com', 'password' => 'senha-bem-longa-123'])
        ->assertStatus(201)->assertJsonPath('email_normalizado', 'ana@ex.com');

    $u = Usuario::query()->where('email_normalizado', 'ana@ex.com')->firstOrFail();
    expect($u->email_verificado_em)->toBeNull();
    $this->assertDatabaseHas('vinculo_acesso', ['usuario_id' => $u->id, 'status' => 'ativo']); // papel responsavel
});

it('nao revela se o email existe no reenvio', function () {
    $this->seed(PapelSeeder::class);
    $this->postJson('/api/v1/auth/email/resend', ['email' => 'ninguem@ex.com'])->assertStatus(202);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=Cadastro`
Expected: FAIL — rotas/classes inexistentes.

- [ ] **Step 3: Implementar contratos, serviço, notificação, controller e rotas**

`GerenciadorConta implements RegistrarConta, VerificarEmail`: no registro, cria `Usuario` com `email_normalizado = Usuario::emailNormalizado($email)` e cria `VinculoAcesso` com papel `responsavel` e `tipo_escopo='central'`, `escopo_id = CentralNacional->id`, `inicio = today()`; dispara `VerificacaoEmail::dispatch($usuario)` (fila). Token de verificação: usar `Illuminate\Auth\Notifications\VerifyEmail`-like — implementar `VerificacaoEmail` (Notification) que monta URL assinada com `URL::temporarySignedRoute('auth.verify-email', now()->addHours(24), ['usuario' => $id])`. `verificar($id)`: aceita o `usuario` (rota assinada) e marca o e-mail.
Rotas públicas: `POST auth/register-guardian`, `POST auth/verify-email` (nome `auth.verify-email`), `POST auth/email/resend`. `RegistrarRequest` valida `nome` (required), `email` (required,email), `password` (required, min:12).
Registrar bindings no provider.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=Cadastro`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade backend/app/Http backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Identidade/CadastroTest.php
git commit -m "feat(identidade): cadastro de responsavel e verificacao de e-mail (RF-03)"
```

---

### Task 7: Login com desafio de MFA (sem sessão plena antes do 2º fator)

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/Autenticar.php`
- Create: `backend/app/Domain/Identidade/Services/Autenticador.php`
- Create: `backend/app/Domain/Identidade/Services/DesafioMfa.php` (token curto de escopo restrito)
- Modify: `backend/app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `backend/app/Http/Requests/...` (validar login)
- Modify: `backend/app/Domain/Identidade/Contracts/AutenticarAtor.php` (remover/renomear para `Autenticar`), `DomainServiceProvider`
- Test: `backend/tests/Feature/Identidade/LoginMfaTest.php`

**Interfaces:**
- Consumes: `Usuario` (Task 2), `Mfa` (Task 8 — esta task define o contrato `ExigirMfa`/`TemFatorValidado` que a Task 8 implementa; no teste, usar um stub).
- Produces: `Autenticar::executar(string $email, string $senha): ResultadoLogin`. `ResultadoLogin` (DTO readonly): `usuario: Usuario`, `mfaPendente: bool`, `tokenDesafio: ?string`. Se o perfil **não** exige MFA (papel `responsavel`), `mfaPendente=false` e o controller emite sessão. Se exige, `mfaPendente=true` e emite **apenas** `tokenDesafio` (Sanctum token com ability `mfa:pendente`), nunca token de sessão plena.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/LoginMfaTest.php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Identidade\Models\Papel;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
});

function criarUsuarioComPapel(string $codigo, string $senha = 'senha-bem-longa-123'): Usuario {
    $u = Usuario::factory()->create(['senha' => Hash::make($senha), 'email_verificado_em' => now()]);
    VinculoAcesso::factory()->create(['usuario_id' => $u->id, 'papel_id' => Papel::query()->where('codigo',$codigo)->value('id'), 'tipo_escopo' => 'central', 'escopo_id' => 'c1']);
    return $u;
}

it('responsavel recebe token de sessao direto', function () {
    criarUsuarioComPapel('responsavel');
    $this->postJson('/api/v1/auth/login', ['email' => Usuario::query()->first()->email_normalizado, 'password' => 'senha-bem-longa-123'])
        ->assertStatus(201)->assertJsonStructure(['token']);
});

it('equipe sem fator recebe desafio, nao sessao plena', function () {
    criarUsuarioComPapel('coordenadora');
    $u = Usuario::query()->first();
    $resp = $this->postJson('/api/v1/auth/login', ['email' => $u->email_normalizado, 'password' => 'senha-bem-longa-123'])
        ->assertStatus(200)->assertJsonPath('mfa_required', true)
        ->assertJsonStructure(['token_desafio']);
    // O token de desafio NAO acessa area operacional:
    $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$resp->json('token_desafio')])
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=LoginMfa`
Expected: FAIL.

- [ ] **Step 3: Implementar `Autenticar`, `ResultadoLogin`, `DesafioMfa` e controller**

`Autenticador implements Autenticar`: normaliza e-mail, busca `Usuario`; `Hash::check`; se falha → `ProblemException('unauthorized', ..., 401)`. Determina se o perfil exige MFA: exige se **algum** vínculo vigente tem papel ≠ `responsavel`. Se exige e ainda não há fator validado (consulta `ExigirMfa` — Task 8), cria token Sanctum com ability `mfa:pendente` (via `createToken('mfa-desafio', ['mfa:pendente'])`) e devolve `mfaPendente=true`. Caso contrário emite token normal (abilities `*`) e devolve `mfaPendente=false`.
`AuthController::login` adapta: `mfaPendente` → 200 com `mfa_required=true` e `token_desafio`; senão 201 com `token`.
Proteger as rotas operacionais para que a ability `mfa:pendente` **não** autorize: usar middleware `abilities`/`can` ou um guard que exija `tokenCan('*')`. (Aplicado na Task 11.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=LoginMfa`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade backend/app/Http backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Identidade/LoginMfaTest.php
git commit -m "feat(identidade): login com desafio de MFA (CA-15)"
```

---

### Task 8: TOTP, códigos de recuperação e e-mail de contingência

**Files:**
- Create: `backend/database/migrations/2026_11_09_200005_create_fator_mfa_and_codigo_recuperacao.php`
- Create: `backend/app/Domain/Identidade/Models/FatorMfa.php`
- Create: `backend/app/Domain/Identidade/Models/CodigoRecuperacao.php`
- Create: `backend/app/Domain/Identidade/Support/Totp.php`
- Create: `backend/app/Domain/Identidade/Contracts/GerenciarMfa.php`
- Create: `backend/app/Domain/Identidade/Services/GerenciadorMfa.php`
- Create: `backend/app/Http/Controllers/Api/V1/MfaController.php`
- Create: `backend/app/Http/Requests/Identidade/MfaConfirmarRequest.php`
- Modify: `backend/routes/api.php` (`POST /auth/mfa/verify`, `POST /me/mfa/totp`, `POST /me/mfa/totp/confirm`, `POST /me/mfa/recovery-codes`)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Unit/TotpTest.php`, `backend/tests/Feature/Identidade/MfaTest.php`

**Interfaces:**
- Consumes: `Usuario` (Task 2), `Autenticar`/`ExigirMfa` (Task 7).
- Produces: `Totp::codigoAtual(string $segredo, ?int $timestamp = null): string` e `Totp::validar(string $segredo, string $codigo, ?int $timestamp = null, int $janela = 1): bool` (RFC 6238, HMAC-SHA1, 6 dígitos, 30s). `GerenciarMfa`: `iniciarInscricao(Usuario): array{segredo:string, otpauth_uri:string}`; `confirmar(Usuario, string $codigo): void` (valida e marca `confirmado_em`); `gerarCodigosRecuperacao(Usuario): array<string>`; `validarRecuperacao(Usuario, string $codigo): bool`; `validar(Usuario, string $codigo): bool` (TOTP ou recuperação); `exigirMfa(Usuario): bool`; `temFatorValidado(Usuario): bool`.

- [ ] **Step 1: Write the failing TOTP test (vetores RFC 6238)**

```php
<?php
// backend/tests/Unit/TotpTest.php

use App\Domain\Identidade\Support\Totp;

it('gera codigos compatíveis com os vetores RFC 6238 (SHA1)', function () {
    // Segredo ASCII "12345678901234567890" em base32:
    $segredo = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    expect(Totp::codigoAtual($segredo, 59))->toBe('287082')
        ->and(Totp::codigoAtual($segredo, 1111111109))->toBe('081804');
});

it('valida dentro da janela e rejeita fora', function () {
    $segredo = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    $codigo = Totp::codigoAtual($segredo, 1000);
    expect(Totp::validar($segredo, $codigo, 1000))->toBeTrue()
        ->and(Totp::validar($segredo, '000000', 1000))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=Totp`
Expected: FAIL — classe inexistente.

- [ ] **Step 3: Implementar `Totp` (base32 decode + HMAC-SHA1 + truncamento)**

Implementar decode base32 e o algoritmo HOTP/TOTP (RFC 4226/6238). Sem dependência externa.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=Totp`
Expected: PASS (vetores conferem).

- [ ] **Step 5: Write the failing feature test (inscrição, confirmação, recuperação)**

```php
<?php
// backend/tests/Feature/Identidade/MfaTest.php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Support\Totp;
use Illuminate\Support\Facades\Hash;

it('inscreve, confirma TOTP e gera codigos de recuperacao', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $token = $u->createToken('t')->plainTextToken;

    $seg = $this->postJson('/api/v1/me/mfa/totp', [], ['Authorization' => "Bearer $token"])
        ->assertOk()->json('segredo');

    $this->postJson('/api/v1/me/mfa/totp/confirm', ['codigo' => Totp::codigoAtual($seg)], ['Authorization' => "Bearer $token"])
        ->assertOk();

    $this->postJson('/api/v1/me/mfa/recovery-codes', [], ['Authorization' => "Bearer $token"])
        ->assertOk()->assertJsonStructure(['codigos']);
});
```

- [ ] **Step 6: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=Mfa`
Expected: FAIL — rotas/tabelas/classes inexistentes.

- [ ] **Step 7: Implementar migrações, modelos, serviço, controller e rotas**

Migração `fator_mfa`: `ulid id`, `foreignUlid usuario_id`, `string tipo` (`totp`|`email`), `text segredo_cifrado` (nullable), `timestamp confirmado_em` nullable, timestamps; índice unique parcial `(usuario_id, tipo) WHERE confirmado_em IS NOT NULL`.
Migração `codigo_recuperacao`: `ulid id`, `foreignUlid usuario_id`, `string hash`, `timestamp usado_em` nullable, timestamps.
`FatorMfa` guarda `segredo_cifrado` com cast `encrypted` (usa `APP_KEY`); `CodigoRecuperacao` guarda `hash` (bcrypt), uso único.
`GerenciadorMfa`: `exigirMfa` = algum vínculo vigente com papel ≠ responsavel; `temFatorValidado` = existe `FatorMfa` confirmado. `iniciarInscricao` gera segredo base32 e `otpauth://totp/EBI:<email>?secret=...&issuer=EBI`. `confirmar` valida com `Totp::validar`. `gerarCodigosRecuperacao` cria 10 códigos, guarda hash, devolve em claro uma vez. `validar` aceita TOTP ou um código de recuperação não usado (marcando `usado_em`).
`MfaController`: `verify` (público com token de desafio `mfa:pendente`): valida código via `GerenciarMfa::validar`, e ao sucesso **emite token de sessão plena** (novo `createToken('web', ['*'])`) e revoga o token de desafio. `totp`, `totp/confirm`, `recovery-codes` (autenticado).
Registrar bindings.

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=Mfa` e `--filter=Totp`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add backend/database/migrations/2026_11_09_200005_create_fator_mfa_and_codigo_recuperacao.php backend/app/Domain/Identidade backend/app/Http/Controllers/Api/V1/MfaController.php backend/app/Http/Requests/Identidade/MfaConfirmarRequest.php backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests
git commit -m "feat(identidade): MFA TOTP, recuperacao e contingencia (CA-15)"
```

---

### Task 9: Sessões por dispositivo (listar e revogar)

**Files:**
- Create: `backend/database/migrations/2026_11_09_200006_create_sessao_login_table.php`
- Create: `backend/app/Domain/Identidade/Models/SessaoLogin.php`
- Create: `backend/app/Domain/Identidade/Contracts/GerenciarSessao.php`
- Create: `backend/app/Domain/Identidade/Services/GerenciadorSessao.php`
- Create: `backend/app/Http/Controllers/Api/V1/SessionController.php`
- Modify: `backend/routes/api.php` (`GET /me/sessions`, `DELETE /me/sessions/{id}`)
- Test: `backend/tests/Feature/Identidade/SessaoTest.php`

**Interfaces:**
- Consumes: `Usuario` (Task 2).
- Produces: `GerenciarSessao::abrir(Usuario, string $dispositivo): SessaoLogin`; `listar(Usuario): Collection<int,SessaoLogin>`; `revogar(Usuario, string $id): void`. Expiração por perfil: `Usuario` responsável → 12 h; demais → 8 h (constantes no serviço).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/SessaoTest.php

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Hash;

it('lista e revoga dispositivos', function () {
    $u = Usuario::factory()->create(['senha' => Hash::make('senha-bem-longa-123'), 'email_verificado_em' => now()]);
    $token = $u->createToken('t')->plainTextToken;
    $auth = ['Authorization' => "Bearer $token"];

    app(\App\Domain\Identidade\Contracts\GerenciarSessao::class)->abrir($u, 'Chrome/Windows');
    $id = $this->getJson('/api/v1/me/sessions', $auth)->assertOk()->json('data.0.id');

    $this->deleteJson("/api/v1/me/sessions/{$id}", [], $auth)->assertNoContent();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=Sessao`
Expected: FAIL.

- [ ] **Step 3: Implementar migração, modelo, serviço, controller e rotas**

Migração `sessao_login`: `ulid id`, `foreignUlid usuario_id`, `string dispositivo`, `timestamp criada_em`, `timestamp expira_em`, `timestamp revogada_em` nullable, timestamps.
`GerenciadorSessao` implementa as três operações e a expiração por perfil (12 h responsável / 8 h demais — responsável = todos os vínculos vigentes são do papel `responsavel`).
Rotas autenticadas `GET /me/sessions` (lista as não revogadas e não expiradas) e `DELETE /me/sessions/{id}`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=Sessao`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_11_09_200006_create_sessao_login_table.php backend/app/Domain/Identidade backend/app/Http/Controllers/Api/V1/SessionController.php backend/routes/api.php backend/tests/Feature/Identidade/SessaoTest.php
git commit -m "feat(identidade): sessoes por dispositivo (revogaveis)"
```

---

### Task 10: Recuperação e redefinição de senha (sem enumeração)

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/RecuperarSenha.php`
- Create: `backend/app/Domain/Identidade/Services/ServicoRecuperacaoSenha.php`
- Create: `backend/app/Domain/Identidade/Notifications/RedefinicaoSenha.php`
- Create: `backend/app/Http/Controllers/Api/V1/PasswordController.php`
- Modify: `backend/routes/api.php` (`POST /auth/password/forgot`, `POST /auth/password/reset`)
- Test: `backend/tests/Feature/Identidade/RecuperacaoSenhaTest.php`

**Interfaces:**
- Consumes: `Usuario` (Task 2).
- Produces: `RecuperarSenha::solicitar(string $email): void` (sempre conclui; envia e-mail pela fila **somente** se o usuário existir); `RecuperarSenha::redefinir(string $email, string $token, string $novaSenha): void` (`ProblemException('token_invalido', ..., 422)` se inválido/expirado).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/RecuperacaoSenhaTest.php

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Notification;

it('responde 202 identico para email existente e inexistente', function () {
    Notification::fake();
    Usuario::factory()->create(['email_normalizado' => 'existe@ex.com']);

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'existe@ex.com'])->assertStatus(202);
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'naoexiste@ex.com'])->assertStatus(202);
});

it('redefine a senha com token valido e rejeita token invalido', function () {
    Notification::fake();
    $u = Usuario::factory()->create(['email_normalizado' => 'alvo@ex.com']);
    app(\App\Domain\Identidade\Contracts\RecuperarSenha::class)->solicitar('alvo@ex.com');
    $token = \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', 'alvo@ex.com')->value('token');

    $this->postJson('/api/v1/auth/password/reset', ['email' => 'alvo@ex.com', 'token' => $token, 'password' => 'nova-senha-longa-456'])->assertOk();
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'alvo@ex.com', 'token' => 'xxx', 'password' => 'nova-senha-longa-456'])->assertStatus(422);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=RecuperacaoSenha`
Expected: FAIL.

- [ ] **Step 3: Implementar notificação, serviço e controller**

`ServicoRecuperacaoSenha`: gera token via `Password::broker()` (reusa `password_reset_tokens`) ou implementação própria com hash; envia `RedefinicaoSenha` pela fila; resposta sempre 202. Redefinição: valida token+email, troca `senha` (Argon2id via cast `hashed`), invalida tokens de acesso (`Usuario->tokens()->delete()` — força re-login).
`PasswordController`: `forgot` (202 sempre), `reset` (200/422). Rate limit já é global.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=RecuperacaoSenha`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade backend/app/Http/Controllers/Api/V1/PasswordController.php backend/routes/api.php backend/tests/Feature/Identidade/RecuperacaoSenhaTest.php
git commit -m "feat(identidade): recuperacao de senha sem enumeracao (RF-03)"
```

---

### Task 11: Núcleo de autorização (Policies) e proteção das rotas operacionais

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/AutorizarOperacao.php`
- Create: `backend/app/Domain/Identidade/Services/AutorizadorOperacao.php`
- Create: `backend/app/Domain/Identidade/Support/Operacoes.php` (catálogo de operações críticas)
- Create: `backend/app/Policies/OrganizacaoPolicy.php` (reorganiza as operações de Organização por recurso)
- Modify: `backend/app/Providers/ApiServiceProvider.php` (remover `Gate::define('gerir-organizacao', ...)`; registrar a Policy)
- Modify: `backend/app/Http/Middleware/ResolveActingScope.php` (garantir `tokenCan('*')`: rejeitar ability `mfa:pendente`)
- Modify: `backend/routes/api.php` (dividir o grupo `can:gerir-organizacao` em um `can:<operacao>` por recurso)
- Modify: `backend/tests/Feature/Organizacao/AutorizacaoTest.php` (reescrever em cima de operações)
- Test: `backend/tests/Feature/Identidade/AutorizacaoOperacaoTest.php`

**Interfaces:**
- Consumes: `ResolverEscopo` (Task 4), `ContextoAtuacao` (Task 5).
- Produces: `AutorizarOperacao::permite(ContextoAtuacao $ctx, string $operacao, ?string $tipoRecurso, ?string $recursoId): bool`. A matriz papel×operação (§ catálogo de papéis do spec) decide o conjunto de operações do papel; o escopo territorial (quando a operação é sobre um recurso) é decidido por `ResolverEscopo::cobre`. `super_admin` ignora escopo (bypass explícito). `Operacoes`: constantes — `ORGANIZACAO_LER`, `ORGANIZACAO_GERENCIAR`, `USUARIO_LER`, `USUARIO_GERENCIAR`, `CONVITE_GERENCIAR`, `ACESSO_GERENCIAR`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/AutorizacaoOperacaoTest.php

use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);
    $this->autorizar = app(AutorizarOperacao::class);
});

function ctx(string $papel, string $tipo, string $id): ContextoAtuacao {
    return new ContextoAtuacao(papelCodigo: $papel, tipoEscopo: $tipo, escopoId: $id);
}

it('coordenadora gere organizacao dentro do proprio escopo', function () {
    $h = \Mockery::mock(ConsultarHierarquia::class);
    $h->shouldReceive('ancestrais')->andReturn(['igreja' => 'ig1', 'setor' => 'set1', 'regional' => 'reg1', 'central' => 'c1']);
    app()->instance(ConsultarHierarquia::class, $h);

    $autz = app(AutorizarOperacao::class);
    expect($autz->permite(ctx('coordenadora', 'setor', 'set1'), Operacoes::ORGANIZACAO_GERENCIAR, 'igreja', 'ig1'))->toBeTrue()
        ->and($autz->permite(ctx('coordenadora', 'setor', 'set2'), Operacoes::ORGANIZACAO_GERENCIAR, 'igreja', 'ig1'))->toBeFalse();
});

it('responsavel nao gere organizacao', function () {
    expect($this->autorizar->permite(ctx('responsavel', 'ebi', 'e1'), Operacoes::ORGANIZACAO_GERENCIAR))->toBeFalse()
        ->and($this->autorizar->permite(ctx('responsavel', 'ebi', 'e1'), Operacoes::ORGANIZACAO_LER))->toBeTrue();
});

it('super_admin ignora escopo', function () {
    expect($this->autorizar->permite(ctx('super_admin', 'central', 'cX'), Operacoes::ORGANIZACAO_GERENCIAR, 'igreja', 'ig1'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=AutorizacaoOperacao`
Expected: FAIL — classes/operações inexistentes.

- [ ] **Step 3: Implementar catálogo de operações, autorizador e Policy**

`Operacoes`: constantes citadas. `AutorizadorOperacao`: mapa `papel => [operacoes]` derivado da matriz (`docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md`); `super_admin` = todas. `permite`: se a operação não é de recurso, retorna pertinência do papel; se é, exige pertença e, salvo `super_admin`, `ResolverEscopo::cobre(ctx.tipoEscopo, ctx.escopoId, tipoRecurso, recursoId)`.
`OrganizacaoPolicy`: métodos `ler`, `gerenciar` delegando a `AutorizarOperacao` usando o `ContextoAtuacao` de `$request->attributes`. Registrar em `ApiServiceProvider` via `Gate::policy(...)`/`Gate::define` por operação; remover o `gerir-organizacao` provisório.
`routes/api.php`: trocar `->middleware('can:gerir-organizacao')` por `->middleware('can:organizacao.gerenciar')` (mutating) e `->middleware('can:organizacao.ler')` (leitura). Nomes das abilities de Gate = `organizacao.ler`, `organizacao.gerenciar`, `usuario.ler`, `usuario.gerenciar`, `convite.gerenciar`, `acesso.gerenciar`.
`ResolveActingScope`: rejeitar requisição cujo token não tem ability `*` (token de desafio `mfa:pendente`) com `ProblemException('mfa_pendente', ..., 403)`.
Reescrever `tests/Feature/Organizacao/AutorizacaoTest.php` para usar as novas operações.

- [ ] **Step 4: Run test to verify it passes (e a suíte inteira)**

Run: `docker compose run --rm app composer test`
Expected: PASS — incluindo os endpoints de Organização da Etapa 2 (que agora exigem vínculo vigente). Ajustar os testes da Etapa 2 que criavam `Ator::factory()->create(['papel' => 'coordenadora'])` para criar `Usuario` + `VinculoAcesso` de `coordenadora` no escopo central.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade backend/app/Policies backend/app/Providers/ApiServiceProvider.php backend/app/Http/Middleware/ResolveActingScope.php backend/routes/api.php backend/tests
git commit -m "feat(identidade): autorizacao por operacao e policies (CA-03, RN-11)"
```

---

### Task 12: Escopo horizontal negado por padrão (reforço da hierarquia)

**Files:**
- Test: `backend/tests/Feature/Identidade/EscopoHorizontalTest.php`
- Modify: `backend/app/Domain/Organizacao/Services/ConsultaHierarquia.php` (garantir que nó desconhecido lança exceção, não devolve mapa vazio)

**Interfaces:**
- Cobre o Review Focus #2: vínculo em nó irmão não cobre o recurso.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/EscopoHorizontalTest.php

use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\IgrejaVinculacao;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use App\Domain\Organizacao\Models\CentralNacional;

it('vinculo de setor nao cobre igreja de setor irmao', function () {
    $central = CentralNacional::factory()->create();
    $regional = Regional::factory()->create(['central_nacional_id' => $central->id]);
    $setorA = Setor::factory()->create(['regional_id' => $regional->id]);
    $setorB = Setor::factory()->create(['regional_id' => $regional->id]);
    $igrejaB = Igreja::factory()->create();
    // A ligacao igreja→setor tem vigencia (igreja_vinculacao), nao uma coluna em igrejas.
    IgrejaVinculacao::factory()->create(['igreja_id' => $igrejaB->id, 'setor_id' => $setorB->id, 'inicio' => '2026-01-01', 'fim' => null]);

    $resolvedor = app(ResolverEscopo::class);
    expect($resolvedor->cobre('setor', $setorA->id, 'igreja', $igrejaB->id))->toBeFalse()
        ->and($resolvedor->cobre('setor', $setorB->id, 'igreja', $igrejaB->id))->toBeTrue()
        ->and($resolvedor->cobre('regional', $regional->id, 'igreja', $igrejaB->id))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=EscopoHorizontal`
Expected: FAIL — `ConsultaHierarquia` ainda não existe (Task 4).

- [ ] **Step 3: Implementar `ConsultaHierarquia` conforme o esquema real da Etapa 2**

A ligação igreja→setor vigente vem de `Igreja::vinculacaoVigente` (`igreja_vinculacao` com `fim IS NULL`); o `ConsultaHierarquia` da Task 4 já deve percorrer essa cadeia. Garantir que um nó inexistente lança `RuntimeException` (não devolve mapa parcial que poderia virar `true`). Se a Task 4 já cobriu isso, esta task só adiciona o teste de irmãos.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=EscopoHorizontal`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/Feature/Identidade/EscopoHorizontalTest.php backend/app/Domain/Organizacao/Services/ConsultaHierarquia.php
git commit -m "test(identidade): nega escopo horizontal por padrao (CA-03)"
```

---

### Task 13: Equipe do EBI — listagem e gestão de usuários

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/ConsultarUsuarios.php`
- Create: `backend/app/Domain/Identidade/Contracts/GerirUsuario.php`
- Create: `backend/app/Domain/Identidade/Services/ConsultaUsuarios.php`
- Create: `backend/app/Domain/Identidade/Services/GerenciadorUsuario.php`
- Create: `backend/app/Http/Controllers/Api/V1/UserController.php`
- Create: `backend/app/Http/Resources/UsuarioResource.php`  (substitui `AtorResource`)
- Modify: `backend/routes/api.php` (`GET /users`, `GET /users/{id}`, `PATCH /users/{id}`)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Identidade/UsuariosTest.php`

**Interfaces:**
- Consumes: `Usuario` (Task 2), `VinculoAcesso` (Task 3), `AutorizarOperacao` (Task 11).
- Produces: `ConsultarUsuarios::listar(array $filtros, int $limit): CursorPaginator` (filtra por escopo do `ContextoAtuacao`); `GerirUsuario::atualizar(string $id, array $dados): Usuario` (nome, status — suspender/reativar). `UsuarioResource` expõe `id, nome, email, email_verificado, status, papeis`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/UsuariosTest.php

use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Support\Operacoes;

it('lista usuarios e exige permissao de leitura', function () {
    $admin = criarUsuarioComPapel('adm_setor', 'central', 'c1');
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->getJson('/api/v1/users', $auth)->assertOk()->assertJsonStructure(['data', 'meta'=>['proximo_cursor']]);
    $this->getJson('/api/v1/users')->assertStatus(401);
});

it('suspende um usuario', function () {
    $admin = criarUsuarioComPapel('adm_igreja', 'central', 'c1');
    $alvo = Usuario::factory()->create();
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->patchJson("/api/v1/users/{$alvo->id}", ['status' => 'suspenso'], $auth)->assertOk()->assertJsonPath('status', 'suspenso');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=Usuarios`
Expected: FAIL.

- [ ] **Step 3: Implementar contratos, serviços, resource, controller e rotas**

Reusar `PaginacaoCursor` (Etapa 2) para o cursor. Listagem restrita ao escopo: usuários com pelo menos um vínculo vigente coberto pelo `ContextoAtuacao`. `GerirUsuario::atualizar` audita via `RegistrarEvento` (RN-10). Rotas protegidas por `can:usuario.ler` / `can:usuario.gerenciar`, `IdempotenciaMiddleware` no mutating.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=Usuarios`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade backend/app/Http/Resources/UsuarioResource.php backend/app/Http/Controllers/Api/V1/UserController.php backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Identidade/UsuariosTest.php
git commit -m "feat(identidade): equipe do EBI — listagem e gestao (RF-05)"
```

---

### Task 14: Convites — criação, listagem e cancelamento

**Files:**
- Create: `backend/database/migrations/2026_11_09_200007_create_convite_table.php`
- Create: `backend/app/Domain/Identidade/Models/Convite.php`
- Create: `backend/app/Domain/Identidade/Contracts/GerirConvite.php`
- Create: `backend/app/Domain/Identidade/Services/GerenciadorConvite.php`
- Create: `backend/app/Domain/Identidade/Notifications/ConviteEbi.php`
- Create: `backend/app/Http/Controllers/Api/V1/InvitationController.php`
- Create: `backend/app/Http/Requests/Identidade/ConviteRequest.php`
- Modify: `backend/routes/api.php` (`POST /invitations`, `GET /invitations`, `DELETE /invitations/{id}`)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Identidade/ConviteTest.php`

**Interfaces:**
- Consumes: `Usuario` (Task 2), `Papel` (Task 1), `AutorizarOperacao` (Task 11).
- Produces: `GerirConvite::criar(string $email, string $papelCodigo, string $tipoEscopo, string $escopoId, Usuario $convidante): Convite`; `listar`; `cancelar(string $id)`. Token secreto de uso único (`token_hash` na tabela; valor em claro só no e-mail). E-mail `ConviteEbi` pela fila com link `/convites/{token}`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/ConviteTest.php

use App\Domain\Identidade\Models\Convite;
use Illuminate\Support\Facades\Notification;

it('cria convite e envia email com token', function () {
    Notification::fake();
    $admin = criarUsuarioComPapel('adm_regional', 'regional', 'reg1');
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $this->postJson('/api/v1/invitations', ['email' => 'nova@ex.com', 'papel' => 'auxiliar', 'tipo_escopo' => 'regional', 'escopo_id' => 'reg1'], $auth)
        ->assertStatus(201)->assertJsonPath('email', 'nova@ex.com');

    expect(Convite::query()->count())->toBe(1)->and(Convite::query()->first()->token_hash)->not->toBeEmpty();
});

it('nega convite com papel acima do escopo do convidante', function () {
    $admin = criarUsuarioComPapel('adm_setor', 'setor', 'set1');
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];
    $this->postJson('/api/v1/invitations', ['email' => 'x@ex.com', 'papel' => 'adm_regional', 'tipo_escopo' => 'regional', 'escopo_id' => 'reg9'], $auth)
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=Convite`
Expected: FAIL.

- [ ] **Step 3: Implementar migração, modelo, serviço, notificação, controller e rotas**

Migração `convite`: `ulid id`, `string email_normalizado`, `foreignUlid papel_id`, `string tipo_escopo`, `string escopo_id`, `string token_hash`, `timestamp expira_em`, `foreignUlid convidante_id`, `timestamp aceito_em` nullable, `timestamp cancelado_em` nullable, timestamps. Regra: o convidante só convida para papel/escopo que ele mesmo poderia ocupar (usa `AutorizarOperacao`). Rotas `can:convite.gerenciar`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=Convite`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_11_09_200007_create_convite_table.php backend/app/Domain/Identidade backend/app/Http/Controllers/Api/V1/InvitationController.php backend/app/Http/Requests/Identidade backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Identidade/ConviteTest.php
git commit -m "feat(identidade): convites para a equipe (RF-05)"
```

---

### Task 15: Aceite de convite sem login (token único) e MFA obrigatório no primeiro acesso

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/AceitarConvite.php`
- Create: `backend/app/Domain/Identidade/Services/AceitadorConvite.php`
- Create: `backend/app/Http/Controllers/Api/V1/InvitationAcceptController.php`
- Create: `backend/app/Http/Requests/Identidade/AceitarConviteRequest.php`
- Modify: `backend/routes/api.php` (`POST /invitations/{id}/accept` — público, mas exige token)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Identidade/AceiteConviteTest.php`

**Interfaces:**
- Consumes: `Convite` (Task 14), `Usuario` (Task 2), `VinculoAcesso` (Task 3), `GerenciarMfa` (Task 8).
- Produces: `AceitarConvite::aceitar(string $conviteId, string $token, string $nome, string $senha): Usuario`. Cria a conta já vinculada (papel+escopo do convite) e marca `aceito_em`. O novo usuário **não** tem fator MFA; no primeiro login, `Autenticar` (Task 7) devolverá o desafio (papel ≠ responsavel) e o acesso operacional só ocorre após inscrever TOTP (Task 8 + guarda da Task 11). Cobre Review Focus #4 (replay do token).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/AceiteConviteTest.php

use App\Domain\Identidade\Models\Convite;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Notification;

it('aceita convite sem login e cria vinculo vigente', function () {
    Notification::fake();
    $admin = criarUsuarioComPapel('adm_regional', 'regional', 'reg1');
    $criado = $this->postJson('/api/v1/invitations', ['email' => 'nova@ex.com', 'papel' => 'auxiliar', 'tipo_escopo' => 'regional', 'escopo_id' => 'reg1'], ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken])->json();

    // O token em claro é obtido no fluxo do serviço (não trafega na criação):
    $token = app(\App\Domain\Identidade\Contracts\GerirConvite::class)->tokenEmClaroParaTeste($criado['id']);

    $this->postJson("/api/v1/invitations/{$criado['id']}/accept", ['token' => $token, 'nome' => 'Nova', 'password' => 'senha-bem-longa-123'])
        ->assertStatus(201)->assertJsonPath('email_normalizado', 'nova@ex.com');

    $u = Usuario::query()->where('email_normalizado', 'nova@ex.com')->firstOrFail();
    expect($u->vinculosVigentes()->count())->toBe(1);

    // Replay: o token é de uso único.
    $this->postJson("/api/v1/invitations/{$criado['id']}/accept", ['token' => $token, 'nome' => 'Nova', 'password' => 'senha-bem-longa-123'])
        ->assertStatus(410);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=AceiteConvite`
Expected: FAIL.

- [ ] **Step 3: Implementar contrato, serviço, controller e rota**

`AceitadorConvite`: em transação, valida convite não aceito/cancelado e não expirado, compara hash do token; cria o `Usuario` (verificado por posse do token de convite) e o `VinculoAcesso`; marca `aceito_em`. Segundo uso → `ProblemException('convite_ja_aceito', ..., 410)`. O helper `tokenEmClaroParaTeste` só existe sob o contrato para o teste; alternativa limpa: `Convite` guarda o token claro em memória na criação e a `Notification` o carrega — preferir capturar via `Notification::assertSentTo(...)` no teste. Escolher a opção sem API de produção para "ler token".

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=AceiteConvite`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade backend/app/Http/Controllers/Api/V1/InvitationAcceptController.php backend/app/Http/Requests/Identidade/AceitarConviteRequest.php backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Identidade/AceiteConviteTest.php
git commit -m "feat(identidade): aceite de convite sem login (RF-05, CA-15)"
```

---

### Task 16: Concessões de acesso diretas (`/access-grants`)

**Files:**
- Create: `backend/app/Domain/Identidade/Contracts/GerirVinculo.php`
- Create: `backend/app/Domain/Identidade/Services/GerenciadorVinculo.php`
- Create: `backend/app/Http/Controllers/Api/V1/AccessGrantController.php`
- Create: `backend/app/Http/Requests/Identidade/VinculoRequest.php`
- Modify: `backend/routes/api.php` (`POST /access-grants`, `DELETE /access-grants/{id}`)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Identidade/AccessGrantTest.php`

**Interfaces:**
- Consumes: `VinculoAcesso` (Task 3), `AutorizarOperacao` (Task 11), `RegistrarEvento` (auditoria).
- Produces: `GerirVinculo::conceder(string $usuarioId, string $papelCodigo, string $tipoEscopo, string $escopoId): VinculoAcesso` (encerra vínculo anterior do mesmo par, se houver — `fim = today()`); `GerirVinculo::revogar(string $vinculoId): void` (`fim = today()`). Ambas auditadas (RN-10).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/AccessGrantTest.php

use App\Domain\Identidade\Models\Usuario;

it('concede e revoga vinculo de acesso', function () {
    $admin = criarUsuarioComPapel('super_admin', 'central', 'c1');
    $alvo = Usuario::factory()->create();
    $auth = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

    $vinculo = $this->postJson('/api/v1/access-grants', ['usuario_id' => $alvo->id, 'papel' => 'coordenadora', 'tipo_escopo' => 'regional', 'escopo_id' => 'reg1'], $auth)
        ->assertStatus(201)->json();

    $this->assertDatabaseHas('vinculo_acesso', ['id' => $vinculo['id'], 'fim' => null]);
    $this->deleteJson("/api/v1/access-grants/{$vinculo['id']}", [], $auth)->assertNoContent();
    $this->assertDatabaseMissing('vinculo_acesso', ['id' => $vinculo['id'], 'fim' => null]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=AccessGrant`
Expected: FAIL.

- [ ] **Step 3: Implementar contrato, serviço, controller e rotas**

Rotas `can:acesso.gerenciar`, com `IdempotenciaMiddleware` no POST. Encerrar vínculo = `fim = today()`, preservando o histórico (vigência). Auditar com `RegistrarEvento` (ação `acesso.concedido` / `acesso.revogado`).

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=AccessGrant`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade backend/app/Http/Controllers/Api/V1/AccessGrantController.php backend/app/Http/Requests/Identidade/VinculoRequest.php backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Identidade/AccessGrantTest.php
git commit -m "feat(identidade): concessoes de acesso diretas (RF-05, RN-10)"
```

---

### Task 17: `/me` e `/me/scopes` (contexto de atuação exposto)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/MeController.php` (+ `scopes`)
- Create: `backend/app/Http/Resources/ContextoAtuacaoResource.php`
- Modify: `backend/routes/api.php` (`GET /me/scopes`)
- Test: `backend/tests/Feature/Identidade/MeScopesTest.php`

**Interfaces:**
- Consumes: `Usuario`, `VinculoAcesso` (Task 3), `UsuarioResource` (Task 13).
- Produces: `GET /me` devolve o `UsuarioResource` (id, nome, email, email_verificado, status, papeis); `GET /me/scopes` devolve `{ data: [{ papel, tipo_escopo, escopo_id }] }` com todos os vínculos vigentes (para o seletor de contexto do portal).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Identidade/MeScopesTest.php

it('devolve o usuario e seus escopos de atuacao', function () {
    $u = criarUsuarioComPapel('coordenadora', 'setor', 'set1');
    $auth = ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];

    $this->getJson('/api/v1/me', $auth)
        ->assertOk()->assertJsonStructure(['id', 'nome', 'email', 'papeis']);

    $this->getJson('/api/v1/me/scopes', $auth)
        ->assertOk()->assertJsonPath('data.0.tipo_escopo', 'setor');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app composer test -- --filter=MeScopes`
Expected: FAIL — rota `/me/scopes` inexistente.

- [ ] **Step 3: Implementar `scopes` no `MeController` e rota**

Ler os vínculos via contrato (`ConsultarUsuarios` ou um `ConsultarVinculos` novo). Não expor tokens nem segredos.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm app composer test -- --filter=MeScopes`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/MeController.php backend/app/Http/Resources/ContextoAtuacaoResource.php backend/routes/api.php backend/tests/Feature/Identidade/MeScopesTest.php
git commit -m "feat(identidade): /me e /me/scopes expõem o contexto de atuacao"
```

---

### Task 18: Portal web — telas de identidade e acesso

**Files:**
- Modify: `frontend/` (SPA React) — telas: Login (com passo MFA), Aceite de convite, Meu perfil (`/me`, inscrição/confirmação TOTP, códigos de recuperação), Dispositivos (`/me/sessions`), Equipe (`/users`, convites, concessões).
- Modify: `frontend/src/**` (rotas, hooks de API, formulários).
- Test: `frontend/` — testes de componente/fluxo (Vitest) para login MFA e aceite de convite.

**Interfaces:**
- Consumes: os endpoints das Tasks 6–17.
- Produces: fluxo de login que, ao receber `mfa_required`, navega para o passo de MFA; seletor de escopo de atuação que envia `X-Acting-Scope`; telas de gestão de equipe/convites.

- [ ] **Step 1: Escrever os testes de fluxo (falhando)**

Cobrir: (a) login de perfil com MFA não emite sessão antes do 2º fator; (b) aceite de convite sem login cria a conta; (c) seletor de escopo envia o header correto.

- [ ] **Step 2: Rodar para ver falhar**

Run: `cd frontend && npm run lint && npm run build`

- [ ] **Step 3: Implementar as telas e a cliente de API**

Reusar o padrão de fetch/erro problem+json da Etapa 1/2. Ao receber `403 mfa_pendente`, redirecionar para a inscrição de TOTP.

- [ ] **Step 4: Rodar para ver passar**

Run: `cd frontend && npm run lint && npm run build`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend
git commit -m "feat(web): telas de identidade e acesso (login MFA, convites, equipe)"
```

---

### Task 19: Documentação, OpenAPI e catálogo de endpoints

**Files:**
- Modify: `docs/04-api/openapi.yaml` (todas as rotas novas: auth, me, sessions, users, invitations, access-grants; esquema `X-Acting-Scope`; respostas `problem+json`).
- Modify: `docs/04-api/endpoints.md` (mesmas rotas; reforçar a fonte da verdade).
- Modify: `docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md` (linkar cada operação ao gate `can:<operacao>`).
- Modify: `docs/CHANGELOG.md` (nota da Etapa 3).
- Create (se aplicável): `docs/06-arquitetura/decisoes/ADR-0022-identidade-e-acesso.md` (o que ficou fora: finalidade `*`, passkey, federação, WhatsApp — dívidas registradas).
- Test: `docker ... python scripts/check-endpoints.py` e Spectral.

**Interfaces:**
- Produces: catálogo e spec consistentes com o que foi implementado.

- [ ] **Step 1: Atualizar OpenAPI e catálogo**

Adicionar paths/schemas; garantir que `scripts/check-endpoints.py` cobre as rotas de `routes/api.php` (o teste de contrato OpenAPI↔rotas da Etapa 1 deve passar).

- [ ] **Step 2: Validar**

Run: `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"`
Run também o Spectral se disponível no CI.
Expected: PASS.

- [ ] **Step 3: Atualizar a matriz e o CHANGELOG**

- [ ] **Step 4: Rodar a suíte + lint + análise estática finais**

Run:
- `docker compose run --rm app composer test`
- `docker compose run --rm app composer pint --test`
- `docker compose run --rm app composer phpstan`
- `cd frontend && npm run lint && npm run build && npm run gen:api`
Expected: tudo verde. `ModularBoundariesTest` verde.

- [ ] **Step 5: Commit**

```bash
git add docs
git commit -m "docs(identidade): openapi, catalogo e matriz da Etapa 3"
```

---

## Auto-revisão do plano

- **Cobertura do spec:** cada RF/CA/RN do design tem task dona — catálogo de papéis (T1), usuário ULID (T2), vínculo/escopo (T3–T5), cadastro+verificação (T6), login+MFA (T7–T8), sessões (T9), senha (T10), autorização por operação (T11–T12), equipe (T13), convites (T14–T15), concessões (T16), `/me` (T17), portal (T18), docs (T19).
- **Review Focus:** #1→T7/T15, #2→T12, #3→T10, #4→T15, #5→T2.
- **Fronteira modular:** `ResolverEscopo` atravessa módulos por `ConsultarHierarquia` (novo contrato em Organização), nunca por Model. `app/Http` só usa Contracts + Resources.
- **Tipo/assinatura consistentes:** `ContextoAtuacao` usado por T5/T11 permanece `papelCodigo/tipoEscopo/escopoId`. `criarUsuarioComPapel` é helper de teste introduzido em T7 e reusado em T11/T13–T17 (extrair para `tests/Pest.php` na T7).
- **Proporção:** a etapa é grande por natureza (identidade+autorização+equipe). Commits pequenos e TDD por task mantêm cada passo revisável.

**Dívidas registradas (fora do escopo):** captura de finalidade (`*`), passkey/WebAuthn, federação real, WhatsApp, trilha consultável/retenção (E05).