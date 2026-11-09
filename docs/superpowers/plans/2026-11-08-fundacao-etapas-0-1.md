# Fundação técnica (Etapas 0 e 1) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar a fundação executável do EBI — monorepo que sobe com `docker compose up`, com esqueleto modular do backend em Laravel 13 / PHP 8.4 sob Octane/FrankenPHP, portal React, trilha de auditoria append-only e CI verde desde o primeiro PR.

**Architecture:** Monorepo com `backend/` (Laravel), `frontend/` (React+Vite+TS), `docker/` (imagem única PHP 8.4+FrankenPHP), e `infra/` já existente para homolog/produção. O backend é um monólito modular: cada módulo em `app/Domain/<Módulo>/` é dono das suas tabelas e modelos, e só expõe interface pública; um teste de arquitetura impede a violação das fronteiras. A trilha de auditoria nasce append-only com encadeamento de hash real, para que nenhuma escrita sensível nasça sem registro.

**Tech Stack:** Laravel 13, PHP 8.4, Octane + FrankenPHP, PostgreSQL 17, Redis 7, Horizon, Sanctum, Pest/PHPUnit, PHPStan, Pint, React 19 + Vite + TypeScript, GitHub Actions, Spectral.

**Spec:** `docs/superpowers/specs/2026-11-08-fundacao-etapas-0-1-design.md`

## Global Constraints

- **PHP 8.4 sempre em contêiner.** Nada depende do PHP do host (o host tem 8.3). Todos os comandos PHP/Composer/Artisan rodam via `docker compose exec` ou `docker compose run`.
- **`vendor/` em volume Linux nomeado** (`vendordata`), nunca no bind mount do Windows.
- **PostgreSQL 17**, **Redis 7**. O `infra/compose.yml` já fixa `postgres:17-alpine` e `redis:7-alpine`. Não alterar.
- **Imagens `ghcr.io/helesjunior/ccb-ebi-api` e `ghcr.io/helesjunior/ccb-ebi-web`** — nomes já assumidos pelo `infra/compose.yml`. Não renomear.
- **Domínio e documentação em português; rotas e campos da API em inglês.** Prefixo de API `/api/v1`.
- **Erros sempre em `application/problem+json` (RFC 9457)** com `type`, `title`, `status`, `detail`, `code`.
- **Nenhum dado real.** Seeders só geram hierarquia fictícia de Brasília.
- **Reuso:** `infra/compose.yml`, `infra/compose.dev.yml`, `infra/.env.example`, `infra/app.env.example`, `scripts/check-endpoints.py` e `docs/04-api/openapi.yaml` já existem. Estender, não recriar (exceto o `openapi.yaml`, que ganha as rotas novas).
- **Sem regra de negócio.** Nada de hierarquia, cadastro, presença ou exceção etária nesta leva.
- **Commits frequentes**, um por passo de commit. Mensagens em português, imperativo (`adiciona`, `corrige`, `configura`).

## Review Focus

Os pontos abaixo são condições que o spec implica mas cujos testes nenhuma tarefa óbvia cobre; cada um ganha um teste na tarefa dona do código.

1. **Healthcheck com dependência fora do ar.** Se o Postgres ou o Redis estiverem indisponíveis, `GET /api/v1/health` deve responder `503` com `status: "degraded"` e o `checks` indicando o que falhou — nunca `200` nem exceção não tratada. (Testado na Tarefa 20.)
2. **Login com credenciais inválidas.** `POST /api/v1/auth/login` com corpo inválido ou ator inexistente deve responder `422`/`401` em `problem+json`, nunca vazar stack trace nem criar sessão. (Testado na Tarefa 21.)
3. **Rota protegida sem token.** `GET /api/v1/me` sem `Authorization` deve responder `401` em `problem+json`; com token válido, `200` com o ator. (Testado na Tarefa 22.)
4. **Auditoria imutável.** Um `UPDATE`/`DELETE` na tabela `evento_auditoria` pela aplicação deve falhar (guarda no modelo), e alterar uma linha no meio da cadeia deve invalidar a verificação das seguintes. (Testado na Tarefa 19.)
5. **Fronteira de módulo violada.** Um `use App\Domain\X\Models\...` fora do módulo `X` deve fazer o teste de arquitetura falhar. (Testado na Tarefa 5.)

---

## File Structure

```
ccb-ebi/
├── backend/
│   ├── app/
│   │   ├── Domain/
│   │   │   ├── Identidade/       # auth de mentira, ator semeado, /me
│   │   │   │   ├── Contracts/
│   │   │   │   ├── Models/
│   │   │   │   └── Services/
│   │   │   ├── Auditoria/        # trilha append-only com hash encadeado
│   │   │   │   ├── Contracts/
│   │   │   │   ├── Models/
│   │   │   │   ├── Services/
│   │   │   │   └── Support/
│   │   │   ├── Organizacao/      # vazio nesta leva (estrutura + README)
│   │   │   ├── Participantes/    # vazio
│   │   │   ├── Agenda/           # vazio
│   │   │   ├── Sessoes/          # vazio
│   │   │   ├── Conteudo/         # vazio
│   │   │   ├── Feedback/         # vazio
│   │   │   ├── Indicadores/      # vazio
│   │   │   └── Notificacoes/     # vazio
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Middleware/
│   │   │   └── Resources/
│   │   ├── Providers/
│   │   │   └── DomainServiceProvider.php
│   │   └── Support/
│   │       ├── ProblemDetails.php
│   │       └── ProblemException.php
│   ├── bootstrap/app.php
│   ├── config/
│   ├── database/migrations/
│   ├── database/seeders/
│   ├── routes/api.php
│   ├── tests/
│   │   ├── Arch/ModularBoundariesTest.php
│   │   ├── Feature/Api/
│   │   └── Unit/
│   ├── .env.example
│   ├── composer.json
│   ├── phpstan.neon
│   └── pint.json
├── frontend/
│   ├── src/
│   │   ├── api/client.ts         # gerado/a partir do OpenAPI
│   │   ├── pages/Login.tsx
│   │   ├── App.tsx
│   │   └── main.tsx
│   ├── package.json
│   ├── vite.config.ts
│   └── tsconfig.json
├── docker/
│   └── php/Dockerfile            # php:8.4-cli + FrankenPHP + extensões
├── docker-compose.yml            # DEV: app, horizon, scheduler, postgres, redis, mailpit
├── .github/
│   ├── workflows/ci.yml
│   ├── workflows/publish.yml
│   └── pull_request_template.md
├── CODEOWNERS
├── CONTRIBUTING.md
├── VERSION
└── .gitignore
```

**Por que esta forma:** cada módulo é auto-contido (modelos + contratos + serviços juntos), o que é o que permite a fronteira do ADR-0002. `App\Support` guarda utilitários transversais de HTTP (ProblemDetails). `docker/php/Dockerfile` é a única imagem do backend; `app`/`horizon`/`scheduler` só mudam o `command`.

---

### Task 1: Esqueleto do backend Laravel 13 + esqueleto do repo

**Files:**
- Create: `backend/` (projeto Laravel), `docker/php/Dockerfile`, `docker-compose.yml`, `.gitignore`, `VERSION`
- Create: `backend/.env.example`

**Interfaces:**
- Produces: um `backend/` Laravel funcional sob PHP 8.4, e um `docker-compose.yml` que sobe `app`/`postgres`/`redis`.

- [ ] **Step 1: Criar o projeto Laravel dentro de `backend/` via contêiner**

Como o host tem PHP 8.3, gerar o projeto com a imagem oficial:

```powershell
docker run --rm -v "${PWD}:/app" -w /app composer:2 create-project laravel/laravel backend "^13.0"
```

Se a maior versão disponível for 12, pare e reporte: o plano exige Laravel 13 (o `nfegov2.0` usa `^13.17`). Ajuste o `composer.json` do backend para `"laravel/framework": "^13.17"`.

- [ ] **Step 2: Escrever `docker/php/Dockerfile`**

Base `php:8.4-cli` (via FrankenPHP), com as extensões que o Laravel exige. Conteúdo exato:

```dockerfile
# Imagem única do backend: PHP 8.4 + FrankenPHP (Octane). Laravel 13 exige PHP >= 8.4.
FROM dunglas/frankenphp:1-php8.4

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip zip libpq-dev libzip-dev libicu-dev libonig-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && install-php-extensions \
        pdo_pgsql pgsql zip intl mbstring bcmath pcntl opcache gd exif redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
```

- [ ] **Step 3: Escrever `docker-compose.yml` (dev)**

```yaml
name: ebi-dev
services:
  app:
    build: { context: ., dockerfile: docker/php/Dockerfile }
    working_dir: /app
    command: ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]
    volumes:
      - ./backend:/app
      - vendordata:/app/vendor
    ports: ["8000:8000"]
    env_file: backend/.env
    depends_on:
      postgres: { condition: service_healthy }
      redis: { condition: service_healthy }
  horizon:
    build: { context: ., dockerfile: docker/php/Dockerfile }
    working_dir: /app
    command: ["php", "artisan", "horizon"]
    volumes: [./backend:/app, vendordata:/app/vendor]
    env_file: backend/.env
    depends_on: [app]
  scheduler:
    build: { context: ., dockerfile: docker/php/Dockerfile }
    working_dir: /app
    command: ["php", "artisan", "schedule:work"]
    volumes: [./backend:/app, vendordata:/app/vendor]
    env_file: backend/.env
    depends_on: [app]
  postgres:
    image: postgres:17-alpine
    environment: { POSTGRES_DB: ebi, POSTGRES_USER: ebi, POSTGRES_PASSWORD: ebi }
    volumes: [pgdata:/var/lib/postgresql/data]
    ports: ["5433:5432"]
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ebi -d ebi"]
      interval: 5s
  redis:
    image: redis:7-alpine
    command: ["redis-server", "--appendonly", "yes"]
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
  mailpit:
    image: axllent/mailpit:v1.24
    ports: ["8025:8025"]
volumes:
  pgdata:
  vendordata:
```

- [ ] **Step 4: Escrever `.gitignore` da raiz e `VERSION`**

`.gitignore` (raiz), espelhando o `nfegov2.0`:

```
/backend/vendor/
/backend/node_modules/
/backend/public/build/
/backend/public/hot
/backend/public/spa/
/backend/storage/*.key
/backend/.env
/backend/.phpunit.result.cache
/frontend/node_modules/
/frontend/dist/
/frontend/.vite/
.env
*.log
.DS_Store
.idea/
.vscode/
/.superpowers/
```

`VERSION` com uma linha: `0.1.0`.

- [ ] **Step 5: Instalar dependências dentro do contêiner e verificar o framework**

```powershell
docker compose build app
docker compose run --rm app composer install
docker compose run --rm app php artisan --version
```

Expected: imprime `Laravel Framework 13.x`.

- [ ] **Step 6: Commit**

```bash
git add backend docker docker-compose.yml .gitignore VERSION
git commit -m "esqueleto do backend Laravel 13 com imagem PHP 8.4/FrankenPHP"
```

---

### Task 2: Dependências do backend, `app.env` e configuração base

**Files:**
- Modify: `backend/composer.json` (dev deps), `backend/.env.example`
- Create: `backend/pint.json`, `backend/phpstan.neon`

**Interfaces:**
- Produces: `laravel/octane`, `laravel/sanctum`, `laravel/horizon`, `laravel/pint`, `phpstan/phpstan`, `larastan/larastan`, `pestphp/pest`, `pestphp/pest-plugin-laravel` disponíveis; comandos `composer pint`, `composer phpstan`, `composer test`.

- [ ] **Step 1: Adicionar dependências**

```powershell
docker compose run --rm app composer require laravel/octane laravel/sanctum laravel/horizon laravel/tinker
docker compose run --rm app composer require --dev laravel/pint phpstan/phpstan larastan/larastan pestphp/pest pestphp/pest-plugin-laravel
```

- [ ] **Step 2: Configurar `composer.json`**

Em `scripts`, adicionar:

```json
"pint": "pint",
"phpstan": "phpstan analyse --memory-limit=1G",
"test": "pest"
```

- [ ] **Step 3: Escrever `backend/phpstan.neon`**

```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    level: 8
    paths: [app]
    excludePaths: []
```

- [ ] **Step 4: Escrever `backend/pint.json`**

```json
{ "preset": "laravel" }
```

- [ ] **Step 5: Escrever `backend/.env.example`**

Baseado no do `nfegov2.0`, com os valores do EBI. Campos que importam (o restante é o padrão do Laravel skeleton):

```
APP_NAME="EBI"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ebi
DB_USERNAME=ebi
DB_PASSWORD=ebi

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379

HORIZON_NAME=ebi
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

- [ ] **Step 6: Criar o `backend/.env` local e a chave**

```powershell
Copy-Item backend/.env.example backend/.env
docker compose run --rm app php artisan key:generate
```

- [ ] **Step 7: Rodar lint/estático/teste sobre o esqueleto**

```powershell
docker compose run --rm app composer pint
docker compose run --rm app composer phpstan
docker compose run --rm app composer test
```

Expected: as três passam (o Pest roda pelo menos os testes de exemplo do skeleton; remova-os no Step 8 se existirem).

- [ ] **Step 8: Remover os testes de exemplo do skeleton e commitar**

Apague `backend/tests/Feature/ExampleTest.php` e `backend/tests/Unit/ExampleTest.php`.

```bash
git add backend/composer.json backend/composer.lock backend/pint.json backend/phpstan.neon backend/.env.example backend/tests
git commit -m "configura deps, pint, phpstan e ambiente do backend"
```

---

### Task 3: `App\Support\ProblemDetails` (RFC 9457)

**Files:**
- Create: `backend/app/Support/ProblemException.php`, `backend/app/Support/ProblemDetails.php`
- Modify: `backend/bootstrap/app.php`
- Test: `backend/tests/Unit/ProblemDetailsTest.php`

**Interfaces:**
- Produces: `App\Support\ProblemException(string $code, string $title, int $status, ?string $detail = null, array $extra = [])`; e um renderizador que converte as exceções da API em `application/problem+json` com `type`, `title`, `status`, `detail`, `code`.

- [ ] **Step 1: Escrever o teste que falha**

`backend/tests/Unit/ProblemDetailsTest.php`:

```php
<?php

use App\Support\ProblemException;

it('serializa como problem+json', function () {
    $e = new ProblemException('forbidden', 'Acesso negado', 403, 'Sem permissão');
    $body = $e->toProblemArray();

    expect($body)->toMatchArray([
        'type' => 'about:blank',
        'title' => 'Acesso negado',
        'status' => 403,
        'detail' => 'Sem permissão',
        'code' => 'forbidden',
    ]);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=ProblemDetails`
Expected: FAIL (classe `ProblemException` não existe).

- [ ] **Step 3: Implementar `ProblemException`**

`backend/app/Support/ProblemException.php`:

```php
<?php

namespace App\Support;

use RuntimeException;

class ProblemException extends RuntimeException
{
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        public readonly int $status,
        public readonly ?string $detail = null,
        public readonly array $extra = [],
        public readonly string $type = 'about:blank',
    ) {
        parent::__construct($title);
    }

    public function toProblemArray(): array
    {
        return array_merge([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'code' => $this->code,
        ], $this->extra);
    }
}
```

- [ ] **Step 4: Implementar `ProblemDetails` (renderizador)**

`backend/app/Support/ProblemDetails.php`:

```php
<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ProblemDetails
{
    public static function from(ProblemException $e): JsonResponse
    {
        return new JsonResponse(
            $e->toProblemArray(),
            $e->status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
```

- [ ] **Step 5: Registrar o renderizador no `bootstrap/app.php`**

Em `withExceptions`, mapear `ProblemException` para `ProblemDetails::from`, e mapear as exceções padrão da API (validation → 422, authentication → 401, model not found → 404) para problemas RFC 9457. Mínimo desta tarefa: renderizar `ProblemException`. As demais são tratadas na Tarefa 20.

- [ ] **Step 6: Rodar o teste e ver passar**

Run: `docker compose run --rm app php artisan test --filter=ProblemDetails`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Support backend/bootstrap/app.php backend/tests/Unit/ProblemDetailsTest.php
git commit -m "adiciona ProblemDetails (RFC 9457) para erros da API"
```

---

### Task 4: Estrutura dos módulos do domínio + `DomainServiceProvider`

**Files:**
- Create: `backend/app/Domain/<Módulo>/` para os 10 módulos, cada um com `README.md` e (para os dois ativos) `Contracts/`, `Models/`, `Services/`
- Create: `backend/app/Providers/DomainServiceProvider.php`
- Modify: `backend/bootstrap/providers.php`

**Interfaces:**
- Produces: as pastas dos 10 módulos (`Identidade`, `Organizacao`, `Participantes`, `Agenda`, `Sessoes`, `Conteudo`, `Feedback`, `Indicadores`, `Notificacoes`, `Auditoria`) e um provedor que será o ponto de registro dos serviços de domínio.

- [ ] **Step 1: Criar as pastas dos 10 módulos**

Cada módulo ganha `README.md` de uma linha dizendo o que expõe (ex.: `Organizacao/README.md`: "Módulo Organizacao. Nesta leva: vazio. Expõe: —."). `Identidade` e `Auditoria` já nascem com `Contracts/`, `Models/`, `Services/`.

- [ ] **Step 2: Escrever `DomainServiceProvider`**

`backend/app/Providers/DomainServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Serviços de domínio serão registrados aqui nas próximas etapas.
    }
}
```

Registrar em `backend/bootstrap/providers.php` junto aos provedores da aplicação.

- [ ] **Step 3: Verificar que a app ainda sobe**

```powershell
docker compose run --rm app php artisan about
```

Expected: sem erro; o provider listado.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Domain backend/app/Providers/DomainServiceProvider.php backend/bootstrap/providers.php
git commit -m "cria estrutura dos modulos de dominio e DomainServiceProvider"
```

---

### Task 5: Teste de arquitetura das fronteiras de módulo

**Files:**
- Create: `backend/tests/Arch/ModularBoundariesTest.php`
- Create: `backend/tests/Arch/fixtures/` (fixtures que comprovam que o teste pega a violação)

**Interfaces:**
- Consumes: a estrutura de `app/Domain/` da Tarefa 4.
- Produces: um teste de arquitetura que falha se (1) algo fora de `App\Domain\X` usar `App\Domain\X\Models\*`; (2) um módulo usar classes internas de outro fora da superfície pública; (3) `app/Http` importar modelo interno de módulo.

- [ ] **Step 1: Escrever o teste (Pest `arch()`)**

`backend/tests/Arch/ModularBoundariesTest.php`:

```php
<?php

arch('modulos nao leem modelos alheios')
    ->expect('App\Domain')
    ->not->toUse(['App\Models']) // app/Models nao existe nesta lev; reserva o nome
    ->ignoring('App\Domain');

// Regra: http nao importa modelos internos de modulo.
arch('http fala com o dominio so pela interface publica')
    ->expect('App\Http')
    ->not->toUse([
        'App\Domain\Identidade\Models',
        'App\Domain\Organizacao\Models',
        'App\Domain\Auditoria\Models',
    ]);
```

**Nota de implementação:** `arch()` do Pest cobre bem as regras 1 e 3. A regra 2 (um módulo só pode usar a superfície pública de outro) exige uma checagem dedicada, escrita como teste comum que varre `app/Domain/*` por `use App\Domain\<Outro>\` e permite apenas `\Contracts\`. Implemente essa varredura no mesmo arquivo:

```php
it('um modulo so depende da superficie publica de outro', function () {
    $base = app_path('Domain');
    foreach (glob("$base/*", GLOB_ONLYDIR) as $dir) {
        $modulo = basename($dir);
        foreach (glob("$dir/**/*.php") as $file) {
            $code = file_get_contents($file);
            preg_match_all('/use App\\\\Domain\\\\([A-Za-z]+)\\\\(?!Contracts)([A-Za-z\\\\]+);/', $code, $m);
            foreach ($m[1] as $i => $outro) {
                expect($outro)->toBe($modulo, "Fronteira violada em $file: usa $outro\\{$m[2][$i]}");
            }
        }
    }
});
```

- [ ] **Step 2: Rodar e ver passar no esqueleto**

Run: `docker compose run --rm app php artisan test --filter=ModularBoundaries`
Expected: PASS (os módulos ainda estão vazios).

- [ ] **Step 3: Provar que o teste pega a violação**

Crie temporariamente `backend/app/Http/ProbeViolation.php`:

```php
<?php
namespace App\Http;
use App\Domain\Auditoria\Models\EventoAuditoria;
class ProbeViolation {}
```

Rode `php artisan test --filter=ModularBoundaries` e confirme que **falha**. Depois **apague** o arquivo de sonda.

- [ ] **Step 4: Confirmar verde de novo e commitar**

```bash
git add backend/tests/Arch/ModularBoundariesTest.php
git commit -m "adiciona teste de arquitetura das fronteiras de modulo"
```

---

### Task 6: Migração e modelo `evento_auditoria` (append-only)

**Files:**
- Create: `backend/database/migrations/*_create_evento_auditoria_table.php`
- Create: `backend/app/Domain/Auditoria/Models/EventoAuditoria.php`
- Test: `backend/tests/Feature/Auditoria/ImutabilidadeTest.php`

**Interfaces:**
- Produces: tabela `evento_auditoria` (colunas: `id`, `sequencia` bigint unique, `hash_anterior` string nullable, `hash_encadeado` string, `ator_id` string nullable, `acao` string, `recurso` string, `escopo` string nullable, `origem` string, `motivo` string nullable, `metadados_minimizados` json nullable, `ocorrido_em` timestamp, `timestamps`); modelo `App\Domain\Auditoria\Models\EventoAuditoria` que lança exceção em `updating`/`deleting`.

- [ ] **Step 1: Escrever o teste que falha**

`backend/tests/Feature/Auditoria/ImutabilidadeTest.php`:

```php
<?php

use App\Domain\Auditoria\Models\EventoAuditoria;

it('proibe update na auditoria', function () {
    $e = EventoAuditoria::factory()->create(['acao' => 'probe']);
    expect(fn () => $e->update(['acao' => 'outra']))
        ->toThrow(RuntimeException::class);
});

it('proibe delete na auditoria', function () {
    $e = EventoAuditoria::factory()->create();
    expect(fn () => $e->delete())->toThrow(RuntimeException::class);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Imutabilidade`
Expected: FAIL (tabela/model/factory não existem).

- [ ] **Step 3: Criar a migração**

Com `sequencia` começando em 1, `hash_encadeado` não nulo, índices por `ator_id`, `recurso`, `ocorrido_em`, e `unique(sequencia)`.

- [ ] **Step 4: Criar o modelo com a guarda**

`backend/app/Domain/Auditoria/Models/EventoAuditoria.php`:

```php
<?php

namespace App\Domain\Auditoria\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use RuntimeException;

class EventoAuditoria extends Model
{
    use HasFactory;

    protected $table = 'evento_auditoria';
    protected $guarded = [];
    protected $casts = ['metadados_minimizados' => 'array', 'ocorrido_em' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Auditoria é append-only: update proibido.'));
        static::deleting(fn () => throw new RuntimeException('Auditoria é append-only: delete proibido.'));
    }
}
```

- [ ] **Step 5: Criar a factory e rodar migração**

```powershell
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan test --filter=Imutabilidade
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations backend/app/Domain/Auditoria backend/tests/Feature/Auditoria
git commit -m "adiciona tabela e modelo evento_auditoria append-only"
```

---

### Task 7: Serviço de registro com hash encadeado real

**Files:**
- Create: `backend/app/Domain/Auditoria/Contracts/RegistrarEvento.php`
- Create: `backend/app/Domain/Auditoria/Services/RegistradorAuditoria.php`
- Create: `backend/app/Domain/Auditoria/Support/SerializacaoCanonica.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Auditoria/CadeiaTest.php`

**Interfaces:**
- Produces: `App\Domain\Auditoria\Contracts\RegistrarEvento::registrar(array $evento): EventoAuditoria` — recebe `acao`, `recurso`, `ator_id`, `origem`, `escopo?`, `motivo?`, `metadados_minimizados?` e grava a linha calculando o hash. `SerializacaoCanonica::normalizar(array): string`.

- [ ] **Step 1: Escrever o teste que falha**

`backend/tests/Feature/Auditoria/CadeiaTest.php`:

```php
<?php

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Auditoria\Models\EventoAuditoria;

it('encadeia os hashes', function () {
    $svc = app(RegistrarEvento::class);
    $a = $svc->registrar(['acao' => 'criar', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);
    $b = $svc->registrar(['acao' => 'editar', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);

    expect($a->hash_anterior)->toBeNull()
        ->and($b->hash_anterior)->toBe($a->hash_encadeado)
        ->and($a->hash_encadeado)->toHaveLength(64);
});

it('detecta violacao da cadeia', function () {
    $svc = app(RegistrarEvento::class);
    $svc->registrar(['acao' => 'a', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);
    $b = $svc->registrar(['acao' => 'b', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);
    $svc->registrar(['acao' => 'c', 'recurso' => 'x', 'ator_id' => '1', 'origem' => 'web']);

    // Altera o meio da cadeia burlando a guarda via query builder.
    \DB::table('evento_auditoria')->where('id', $b->id)->update(['metadados_minimizados' => json_encode(['x' => 1])]);

    expect(fn () => $svc->verificarIntegridade())->toThrow(RuntimeException::class);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Cadeia`
Expected: FAIL.

- [ ] **Step 3: Implementar `SerializacaoCanonica`**

Chaves ordenadas, datas em UTC ISO-8601, JSON com `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`. Método `normalizar(array $evento): string` que monta a string determinística.

- [ ] **Step 4: Implementar `RegistradorAuditoria`**

`registrar()`: numa transação, pega o último evento (`orderByDesc('sequencia')`), calcula `sequencia = ultimo+1`, `hash_anterior = ultimo?->hash_encadeado`, e `hash_encadeado = hash('sha256', $hash_anterior . '|' . $sequencia . '|' . SerializacaoCanonica::normalizar($evento))`. Grava e devolve.

`verificarIntegridade()`: percorre todos os eventos em ordem de `sequencia`, recalcula cada hash e lança `RuntimeException` no primeiro que não fecha.

- [ ] **Step 5: Registrar o serviço no provider**

`DomainServiceProvider::register()`: `$this->app->bind(RegistrarEvento::class, RegistradorAuditoria::class);`

- [ ] **Step 6: Rodar os testes e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Cadeia`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Domain/Auditoria backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Auditoria/CadeiaTest.php
git commit -m "adiciona registrador de auditoria com hash encadeado real"
```

---

### Task 8: Módulo Identidade — ator semeado e login de mentira

**Files:**
- Create: `backend/app/Domain/Identidade/Models/Ator.php`
- Create: `backend/database/migrations/*_create_atores_table.php`
- Create: `backend/database/seeders/AtorSeeder.php`
- Create: `backend/app/Domain/Identidade/Contracts/AutenticarAtor.php`
- Create: `backend/app/Domain/Identidade/Services/AutenticadorFicticio.php`
- Test: `backend/tests/Feature/Identidade/LoginTest.php`

**Interfaces:**
- Produces: tabela `atores` (`id`, `nome`, `email` unique, `papel`, `timestamps`); `Ator` model; `AutenticarAtor::autenticar(string $email, string $senha): Ator` que lança `App\Support\ProblemException('unauthorized', 'Credenciais inválidas', 401)` quando o ator não existe ou a senha não confere.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Support\ProblemException;

it('autentica o ator semeado', function () {
    $ator = app(AutenticarAtor::class)->autenticar('coordenadora@ebi.test', 'senha');
    expect($ator)->not->toBeNull();
});

it('rejeita credenciais invalidas sem criar sessao', function () {
    expect(fn () => app(AutenticarAtor::class)->autenticar('ninguem@ebi.test', 'x'))
        ->toThrow(ProblemException::class);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Identidade`
Expected: FAIL.

- [ ] **Step 3: Criar migração, modelo e seeder do ator**

Seeder cria um ator fictício de Brasília: `nome: "Coordenadora EBI (fictícia)"`, `email: "coordenadora@ebi.test"`, `papel: "coordenadora"`, senha `Hash::make('senha')`.

- [ ] **Step 4: Implementar `AutenticarAtor` + `AutenticadorFicticio`**

Consulta o ator pelo e-mail; se não existir ou `Hash::check` falhar, lança `ProblemException('unauthorized', 'Credenciais inválidas', 401)`. Registrar no `DomainServiceProvider`.

- [ ] **Step 5: Rodar e ver passar; rodar o seeder**

```powershell
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan db:seed --class=AtorSeeder
docker compose run --rm app php artisan test --filter=Identidade
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Identidade backend/database backend/tests/Feature/Identidade
git commit -m "adiciona modulo Identidade com ator semeado e login de mentira"
```

---

### Task 9: Rotas da API, `/up` e `/api/v1/health`

**Files:**
- Create: `backend/routes/api.php` (ou ajustar), `backend/app/Http/Controllers/Api/V1/HealthController.php`
- Modify: `backend/bootstrap/app.php` (registrar `api.php` com prefixo `/api` já é padrão; adicionar prefixo `v1`)
- Test: `backend/tests/Feature/Api/HealthTest.php`

**Interfaces:**
- Produces: `GET /up` (200 vazio) e `GET /api/v1/health` → `{ "status": "ok", "checks": { "database": "ok", "redis": "ok" } }`; em falha, HTTP 503 com `status: "degraded"` e o check afetado em `"fail"`.

- [ ] **Step 1: Escrever o teste que falha (inclui o Review Focus 1)**

```php
<?php

it('responde ok quando banco e redis respondem', function () {
    $this->getJson('/api/v1/health')->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', 'ok')
        ->assertJsonPath('checks.redis', 'ok');
});

it('responde 503 quando uma dependencia cai', function () {
    // Simula queda do Redis trocando o host para um inválido em runtime.
    config(['database.redis.default.host' => '127.0.0.1', 'database.redis.default.port' => 1]);
    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.redis', 'fail');
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Health`
Expected: FAIL (rota inexistente).

- [ ] **Step 3: Implementar o `HealthController`**

`health()`: testa `DB::connection()->getPdo()` e `Redis::connection()->ping()` cada um em try/catch; monta `checks`; se algum falhar, responde 503 com `status: degraded`. O `/up` é o healthcheck padrão do Laravel skeleton (já existe em `bootstrap/app.php`).

- [ ] **Step 4: Registrar as rotas**

`routes/api.php`: `Route::prefix('v1')->group(...)` com `health`. Rotas de auth na Tarefa 10.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Health`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/routes backend/app/Http/Controllers backend/tests/Feature/Api/HealthTest.php backend/bootstrap/app.php
git commit -m "adiciona /up e /api/v1/health com verificacao de dependencias"
```

---

### Task 10: Login, logout e `/me` com guard real e sujeito fictício

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/AuthController.php`, `MeController.php`
- Create: `backend/app/Http/Resources/AtorResource.php`
- Create: `backend/app/Http/Middleware/ForceJsonResponse.php`
- Modify: `backend/routes/api.php`, `backend/bootstrap/app.php`
- Test: `backend/tests/Feature/Api/AuthTest.php`

**Interfaces:**
- Produces: `POST /api/v1/auth/login` (body `{email, password}`) → `201` com `{token, ator: {id, nome, email, papel}}`; `POST /api/v1/auth/logout` (autenticado) → `204`; `GET /api/v1/me` (autenticado) → `200` com o ator. Todas as falhas em `problem+json`. Usa Sanctum (`createToken`).

- [ ] **Step 1: Escrever o teste que falha (inclui Review Focus 2 e 3)**

```php
<?php

it('faz login e devolve token', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'coordenadora@ebi.test', 'password' => 'senha'])
        ->assertStatus(201)
        ->assertJsonStructure(['token', 'ator' => ['id', 'nome', 'email', 'papel']]);
});

it('responde 401 em problem+json para credenciais invalidas', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'x@y.z', 'password' => 'x'])
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'unauthorized');
});

it('protege /me sem token', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('devolve o ator com token valido', function () {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'coordenadora@ebi.test', 'password' => 'senha'])
        ->json('token');
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])
        ->assertOk()
        ->assertJsonPath('email', 'coordenadora@ebi.test');
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Auth`
Expected: FAIL.

- [ ] **Step 3: Configurar Sanctum e o guard**

Instalar a migração do Sanctum (`php artisan vendor:publish --tag=sanctum-migrations`, se necessário) e garantir que o `Ator` usa `HasApiTokens`. Definir o guard `sanctum` no `config/auth.php`.

- [ ] **Step 4: Implementar os controllers, o `AtorResource` e as rotas**

`AuthController::login` chama `AutenticarAtor`, emite `$ator->createToken('web')`, devolve `201`. `logout` revoga o token atual e devolve `204`. `MeController` devolve `AtorResource::make($request->user())`. Rotas de `logout`/`me` sob `auth:sanctum`.

- [ ] **Step 5: Mapear 401 para `problem+json`**

No `bootstrap/app.php`, renderizar `AuthenticationException` como `ProblemException('unauthorized', 'Não autenticado', 401)`, e `ValidationException` como 422 em `problem+json`.

- [ ] **Step 6: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Auth`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app backend/routes backend/bootstrap app.php backend/tests/Feature/Api/AuthTest.php
git commit -m "adiciona login, logout e /me com guard real e sujeito ficticio"
```

---

### Task 11: Rate limit por usuário e por IP

**Files:**
- Modify: `backend/bootstrap/app.php` (ou `AppServiceProvider`)
- Test: `backend/tests/Feature/Api/RateLimitTest.php`

**Interfaces:**
- Produces: limite nomeado `api` aplicado às rotas `/api/v1/*`; excedido → `429` com header `Retry-After`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

it('responde 429 ao exceder o limite', function () {
    for ($i = 0; $i < 61; $i++) {
        $r = $this->getJson('/api/v1/health');
    }
    $r->assertStatus(429)->assertHeader('Retry-After');
});
```

(Ajuste o número de iterações se o limite configurado for maior; o teste deve ultrapassar o teto por usuário/IP.)

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=RateLimit`
Expected: FAIL.

- [ ] **Step 3: Definir o rate limiter**

Em um provider, `RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()))`, e aplicar `throttle:api` ao grupo `/api/v1`.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=RateLimit`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app backend/bootstrap backend/tests/Feature/Api/RateLimitTest.php
git commit -m "adiciona rate limit por usuario e por IP nas rotas da API"
```

---

### Task 11b: Configuração twelve-factor (segredos por arquivo e logs JSON)

**Files:**
- Modify: `backend/config/database.php`, `backend/config/logging.php` (canal `stderr` JSON), `backend/.env.example`
- Test: `backend/tests/Unit/TwelveFactorTest.php`

**Interfaces:**
- Consumes: `DB_PASSWORD_FILE` (convenção já usada pelo `infra/compose.yml`).
- Produces: leitura de segredos por arquivo quando `*_FILE` está definido; canal de log `stderr` em JSON.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

it('le segredo de arquivo quando o sufixo _FILE existe', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'sec');
    file_put_contents($tmp, "senha-do-arquivo\n");
    putenv("DB_PASSWORD_FILE=$tmp");
    expect(env_from_file('DB_PASSWORD'))->toBe('senha-do-arquivo');
    putenv('DB_PASSWORD_FILE');
    @unlink($tmp);
});
```

Implemente `env_from_file()` (helper em `App\Support`) como `trim(file_get_contents(getenv("{$key}_FILE")))` quando o arquivo existir, senão cai no `env($key)`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=TwelveFactor`
Expected: FAIL.

- [ ] **Step 3: Aplicar o helper nos segredos e configurar o log JSON**

Usar `env_from_file` em `config/database.php` (password) e nos demais consumidores de `*_FILE`. Definir o canal `stderr` com formatter JSON (`Monolog\Formatter\JsonFormatter`) e `LOG_CHANNEL=stderr` no `.env.example`.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=TwelveFactor`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app backend/config backend/.env.example backend/tests/Unit/TwelveFactorTest.php
git commit -m "configura segredos por arquivo e logs JSON em stderr"
```

---

### Task 12: Horizon e scheduler no compose + esqueleto do frontend

**Files:**
- Create: `frontend/` (projeto Vite React TS)
- Modify: `docker-compose.yml` (confirmar serviços), `backend/config/horizon.php` (publicar)

**Interfaces:**
- Produces: frontend que builda (`npm run build`) e backend com Horizon configurado.

- [ ] **Step 1: Publicar a config do Horizon**

```powershell
docker compose run --rm app php artisan horizon:install
```

- [ ] **Step 2: Criar o frontend com Vite**

No host (Node 24 disponível):

```powershell
npm create vite@latest frontend -- --template react-ts
```

- [ ] **Step 3: Instalar dependências e verificar build**

```powershell
cd frontend
npm install
npm run build
```

Expected: build sem erro.

- [ ] **Step 4: Subir a stack completa pela primeira vez**

```powershell
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

Expected: `app`, `horizon`, `scheduler`, `postgres`, `redis`, `mailpit` saudáveis; `curl http://localhost:8000/up` responde 200.

- [ ] **Step 5: Commit**

```bash
git add frontend docker-compose.yml backend/config/horizon.php
git commit -m "esqueleto do portal React e configuracao do Horizon"
```

---

### Task 13: Cliente da API gerado do OpenAPI + tela de login de mentira

**Files:**
- Create: `frontend/src/api/client.ts` (gerado), `frontend/src/pages/Login.tsx`, `frontend/src/App.tsx`
- Modify: `frontend/package.json` (script `gen:api`), `docs/04-api/openapi.yaml` (adicionar `/health`, `/auth/logout`, `/me`)

**Interfaces:**
- Consumes: `openapi.yaml`.
- Produces: tipos + cliente `openapi-fetch`; `Login.tsx` que chama `POST /api/v1/auth/login` e, no sucesso, guarda o token e navega para uma tela que mostra o ator de `/me`.

- [ ] **Step 1: Adicionar as rotas novas ao `openapi.yaml`**

`/health` (GET, `security: []`), `/auth/logout` (POST 204), `/me` (GET → schema `Ator`). Adicionar o schema `Ator` e o schema de resposta do login.

- [ ] **Step 2: Marcar as rotas novas no `endpoints.md`**

Marcar as novas operações com `no OpenAPI` no catálogo e rodar o script:

```powershell
docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"
```

Expected: `OK: N operações alinhadas.` (Local sem Python: usar o contêiner, como acima.)

- [ ] **Step 3: Configurar o codegen do cliente**

```powershell
cd frontend
npm install openapi-fetch
npm install -D openapi-typescript
```

Adicionar em `package.json`: `"gen:api": "openapi-typescript ../docs/04-api/openapi.yaml -o src/api/schema.d.ts"`. Rodar `npm run gen:api`.

- [ ] **Step 4: Implementar `client.ts` e a tela de login**

`client.ts`: `createClient<paths>` com `baseUrl` de `import.meta.env.VITE_API_URL`. `Login.tsx`: formulário acessível (labels, foco, erro em `role="alert"`), chama o login e trata erro exibindo a `detail` do problema. `App.tsx`: rota simples login → home mostrando `/me`.

- [ ] **Step 5: Buildar e verificar**

```powershell
npm run build
```

Expected: build sem erro de tipo.

- [ ] **Step 6: Commit**

```bash
git add frontend docs/04-api
git commit -m "cliente da API gerado do OpenAPI e tela de login de mentira"
```

---

### Task 14: Build do SPA para dentro do backend e serviço pelo FrankenPHP

**Files:**
- Modify: `frontend/vite.config.ts` (outDir), `backend/routes/web.php` (ou config do FrankenPHP), `docker/php/Dockerfile`

**Interfaces:**
- Produces: `docker compose up` serve o portal em `/` e a API em `/api/v1`.

- [ ] **Step 1: Direcionar o build do Vite**

`vite.config.ts`: `build: { outDir: '../backend/public/spa', emptyOutDir: true }`.

- [ ] **Step 2: Servir o SPA pelo Laravel (padrão desta leva)**

Em `routes/web.php`, uma rota catch-all que devolve `public/spa/index.html` para qualquer caminho que não comece com `/api` nem `/up`. Padrão desta leva: catch-all no Laravel (mais simples, uma peça a menos). Se medir e preferir, mover o estático para o `Caddyfile` do FrankenPHP antes do PHP — mas isso é opcional e registrável no PR.

- [ ] **Step 3: Verificar ponta a ponta**

```powershell
cd frontend && npm run build
cd ..
docker compose up -d
```

Abrir `http://localhost:8000/` → tela de login; fazer login → tela com o ator. `http://localhost:8000/api/v1/health` → `ok`.

- [ ] **Step 4: Commit**

```bash
git add frontend/vite.config.ts backend/routes/web.php docker/php/Dockerfile
git commit -m "serve o portal buildado pelo FrankenPHP"
```

---

### Task 15: CI — lint, estático, testes, Spectral e check-endpoints

**Files:**
- Create: `.github/workflows/ci.yml`
- Create: `scripts/.spectral.yaml` (configuração do Spectral)

**Interfaces:**
- Produces: workflow que roda em PR para `main`; verde com o estado atual.

- [ ] **Step 1: Escrever o `ci.yml`**

Jobs: `backend` (postgres+redis como services; `composer install`; `pint --test`; `phpstan`; `php artisan test`), `frontend` (`npm ci`; `npm run lint`; `npm run build`), `openapi` (Spectral sobre `docs/04-api/openapi.yaml`; Python 3.12 com `pyyaml` rodando `scripts/check-endpoints.py`). Usar PHP 8.4 no setup (`shivammathur/setup-php@v2`, `php-version: '8.4'`) com extensões do backend.

- [ ] **Step 2: Escrever a config do Spectral**

Config `oas3`, com as regras ajustadas ao spec atual (não exigir campos que a v2.7 não preencheu). Se o Spectral acusar algo no spec existente fora do escopo desta leva, **ajustar a config e registrar a dívida** — não editar o spec de negócio.

- [ ] **Step 3: Verificar localmente antes de subir**

```powershell
docker compose run --rm app composer pint --test
docker compose run --rm app composer phpstan
docker compose run --rm app composer test
docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"
```

Expected: tudo verde.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/ci.yml scripts/.spectral.yaml
git commit -m "adiciona CI com lint, testes, Spectral e check-endpoints"
```

---

### Task 16: CI — build e publicação das imagens no GHCR por digest

**Files:**
- Create: `.github/workflows/publish.yml`

**Interfaces:**
- Produces: workflow que, no push para `main`, builda `ccb-ebi-api` e `ccb-ebi-web` e publica no GHCR; o job expõe o digest.

- [ ] **Step 1: Escrever o `publish.yml`**

Build de `docker/php/Dockerfile` → `ghcr.io/${{ github.repository_owner }}/ccb-ebi-api`. Build de um `frontend/Dockerfile` (nginx servindo o `dist`) → `ccb-ebi-web`. Usar `docker/build-push-action` com `push: true` e permissões `packages: write`. Registrar o digest no summary.

- [ ] **Step 2: Criar `frontend/Dockerfile`**

Imagem nginx servindo o `dist` buildado na porta 8080, `read_only`-friendly (o `infra/compose.yml` assume porta 8080 e tmpfs para `/var/cache/nginx`, `/var/run`, `/tmp`).

- [ ] **Step 3: Commit e abrir o PR de exemplo**

```bash
git add .github/workflows/publish.yml frontend/Dockerfile
git commit -m "build e publicacao das imagens no GHCR por digest"
```

O PR de exemplo (critério de pronto da Etapa 0) é este PR: ele passa por lint, testes e build, e a imagem aparece no GHCR.

---

### Task 17: Governança — CODEOWNERS, modelo de PR, CONTRIBUTING e proteção de main

**Files:**
- Create: `CODEOWNERS`, `.github/pull_request_template.md`, `CONTRIBUTING.md`
- Modify: `README.md` (seção de desenvolvimento)

**Interfaces:**
- Produces: a documentação de contribuição descrita na Etapa 0.

- [ ] **Step 1: Escrever `CODEOWNERS`**

Responsável padrão pelo repositório; regras específicas para `infra/`, `docker/` e `.github/`.

- [ ] **Step 2: Escrever o modelo de PR**

Com os campos: descrição, **"RF/CA atendidos"**, checklist (testes, Pint, PHPStan, docs), e captura para PRs de infra.

- [ ] **Step 3: Escrever `CONTRIBUTING.md`**

Ambiente: pré-requisitos (Docker), `docker compose up -d --build`, migração e seeder, como rodar lint/estático/testes, o fluxo de PR (branch → PR → CI verde → 1 revisão), e a regra de que PHP roda sempre em contêiner.

- [ ] **Step 4: Ativar a proteção do `main`**

Documentar em `CONTRIBUTING.md` a configuração esperada (só por PR, CI verde, 1 revisão, sem force-push). Como não há `gh` CLI, a configuração no GitHub é manual: deixar o passo escrito em `CONTRIBUTING.md` e pedir ao responsável para aplicá-la.

- [ ] **Step 5: Commit**

```bash
git add CODEOWNERS .github/pull_request_template.md CONTRIBUTING.md README.md
git commit -m "adiciona governanca: CODEOWNERS, modelo de PR e CONTRIBUTING"
```

---

### Task 18: Dependabot, secret scanning e fumaça final da Etapa 1

**Files:**
- Create: `.github/dependabot.yml`
- Modify: `CONTRIBUTING.md` (nota sobre secret scanning e Dependabot)

**Interfaces:**
- Produces: dependabot para `composer`, `npm`, `github-actions`, `docker`; notas de secret scanning.

- [ ] **Step 1: Escrever `.github/dependabot.yml`**

Ecosystems: `composer` (dir `/backend`), `npm` (dir `/frontend`), `github-actions`, `docker` (dir `/`).

- [ ] **Step 2: Documentar o secret scanning**

Nota em `CONTRIBUTING.md`: ativar secret scanning e push protection nas configurações do repositório (manual).

- [ ] **Step 3: Fumaça final do critério de pronto da Etapa 1**

Do zero:

```powershell
docker compose down -v
docker compose up -d --build
docker compose exec app php artisan migrate --seed
Invoke-RestMethod http://localhost:8000/up
Invoke-RestMethod http://localhost:8000/api/v1/health
docker compose exec app php artisan test
```

Expected: `/up` 200; `/health` `ok`; suíte toda verde; o teste de arquitetura passa; o teste de imutabilidade da auditoria passa.

- [ ] **Step 4: Commit**

```bash
git add .github/dependabot.yml CONTRIBUTING.md
git commit -m "adiciona dependabot e documenta secret scanning"
```

---

## Critérios de pronto (do plano, para conferência final)

- **Etapa 0:** um PR de exemplo passa por lint, testes e build, e a imagem aparece no GHCR. → Tasks 15, 16, 17.
- **Etapa 1:** `docker compose up` sobe tudo do zero, `/health` responde, um teste de arquitetura falha se um módulo violar fronteira, e o CI roda a suíte em menos de 10 minutos. → Tasks 1–14, 18, e a suíte do CI na Task 15.