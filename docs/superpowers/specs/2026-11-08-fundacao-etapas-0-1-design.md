# Desenho — Fundação técnica (Etapas 0 e 1)

**Data:** 08/11/2026 · **Status:** proposto, aguardando aprovação
**Abrange:** Etapa 0 (Repositório e CI) e Etapa 1 (Fundação técnica) do [plano de desenvolvimento](../../07-produto/plano-de-desenvolvimento.md)

## 1. Intenção e o porquê

O EBI tem uma especificação validada e um plano de desenvolvimento, mas ainda não tem código. Esta leva cria a **fundação executável**: um repositório que sobe com um comando, com o esqueleto modular do backend, o portal Web e a esteira de integração contínua. É a base sobre a qual todas as etapas de negócio (E02 em diante) serão construídas sem retrabalho de infraestrutura.

O objetivo desta leva **não** é funcionalidade de negócio. É chegar ao critério de pronto que o próprio plano define:

- Etapa 0: "um PR de exemplo passa por lint, testes e build, e a imagem aparece no GHCR".
- Etapa 1: "`docker compose up` sobe tudo do zero, `/health` responde, um teste de arquitetura falha se um módulo violar fronteira, e o CI roda a suíte em menos de 10 minutos".

## 2. Objetivos, não objetivos e anti-objetivos

**Objetivos**
- Estrutura de monorepo (`backend/`, `frontend/`, `docker/`, `infra/`, `docs/`, `scripts/`).
- Laravel 13 + PHP 8.4 sob **Octane/FrankenPHP**, Horizon e scheduler em contêineres.
- Esqueleto dos módulos do [ADR-0002](../../06-arquitetura/decisoes/ADR-0002-monolito-modular.md) com **teste de arquitetura** que impede um módulo de ler tabela de outro.
- Base comum da API: `/api/v1`, `problem+json` (RFC 9457), paginação por cursor, `Idempotency-Key`, rate limit, healthcheck.
- Base da trilha de auditoria (RF-16), *append-only*.
- Portal Web em React + TypeScript (Vite), cliente da API gerado do OpenAPI, login de mentira, layout responsivo e acessível.
- CI (GitHub Actions): Pint, PHPStan (nível alto), ESLint, testes, Spectral, `scripts/check-endpoints.py`, build e publicação no GHCR com digest.
- `CONTRIBUTING.md` com o ambiente de desenvolvimento.

**Não objetivos (nesta leva)**
- Qualquer regra de negócio (hierarquia, cadastro, presença, exceção etária...). Isso é E02 em diante.
- Autenticação real (MFA, papéis, `X-Acting-Scope`). O login é de mentira, como o plano define para a Etapa 1.
- Upload real de PDF, MinIO e ClamAV em uso (ficam **configurados**, exigidos só quando houver upload — E08).
- App nativo, notificações, relatórios.

**Anti-objetivos (o que faria esta leva falhar mesmo "funcionando")**
- Um esqueleto onde os módulos não tenham fronteira de verdade (violável sem o teste pegar).
- Um `docker compose up` que só funciona na máquina de quem escreveu (bind mount lento no Windows, `vendor/` no host).
- Segredos versionados ou em `docker-compose.yml` em texto claro.
- Dado pessoal em log.
- Um CI que não roda de forma reprodutível ou que passa "amarelo".

## 3. Restrições

- **PHP 8.4 sempre em contêiner.** O host tem PHP 8.3; nada depende do PHP do host. O `vendor/` fica em **volume Linux** (não no bind mount), replicando a solução do `nfegov2.0` que evita o boot de ~20s no Docker Desktop do Windows.
- **Reuso do que já existe**: `infra/compose.yml`, `infra/compose.dev.yml`, `infra/.env.example`, `infra/app.env.example` e `scripts/check-endpoints.py` já existem no repositório (versão 2.6/2.7) e devem ser respeitados/estendidos, não recriados.
- **PostgreSQL 17** (o `infra/compose.yml` já fixa `postgres:17`), **Redis 7**.
- **Nenhum dado real** antes da Etapa 8. Seeders só geram hierarquia fictícia de Brasília.
- **Monólito modular**: um banco, uma aplicação, módulos com interface pública.
- Idiomas: domínio e documentação em português; rotas e campos da API em inglês (ADR-0003, convenções da API).

## 4. Abordagens consideradas

**A) Octane/FrankenPHP (escolhida).** Backend roda `php artisan octane:frankenphp` num contêiner único de aplicação; não há nginx separado para a API. Respeita o ADR-0004 e o plano (Etapa 1). Menos uma peça no compose, boot mais rápido, e o próprio FrankenPHP serve os estáticos do portal Web buildado. Custo: menos familiar que o par php-fpm+nginx, e o modo Octane exige cuidado com estado persistente entre requisições (nada de estado global mutável em serviços).

**B) PHP-FPM + nginx (como o `nfegov2.0`).** O par clássico, já conhecido e testado no `nfegov2.0`. Contraria o ADR-0004, que teria de ser revisado com um novo ADR. Descartada porque a decisão de arquitetura do EBI já está tomada e documentada, e você confirmou que quer respeitá-la.

**C) Octane com RoadRunner/Swoole.** Também é Octane, mas com outro servidor. Descartada: o ADR-0004 e o plano fixam FrankenPHP explicitamente.

**Decisão:** Abordagem A. Você escolheu Octane/FrankenPHP.

## 5. Estrutura e decisões de como construir

### 5.1 Árvore do monorepo

```
ccb-ebi/
├── backend/                 # Laravel 13 + PHP 8.4
│   ├── app/
│   │   ├── Domain/          # módulos do ADR-0002 (modelos, contratos e serviços por módulo — ver 5.3)
│   │   ├── Http/            # Controllers, Middleware, Requests, Resources
│   │   ├── Policies/
│   │   ├── Providers/
│   │   └── Support/         # ProblemDetails, Cursor, IdempotencyKey
│   ├── database/            # migrations, seeders, factories
│   ├── routes/              # api.php, console.php
│   ├── tests/               # Feature, Unit, Arch (fronteira de módulo)
│   ├── config/
│   └── .env.example
├── frontend/                # React + Vite + TypeScript
├── docker/
│   ├── php/Dockerfile       # php:8.4-cli com FrankenPHP + extensões
│   └── Caddyfile            # (se aplicável ao FrankenPHP)
├── infra/                   # (existe) compose de homologação/produção
├── scripts/                 # (existe) check-endpoints.py
├── docs/                    # (existe) a especificação
├── docker-compose.yml       # DEV: app, horizon, scheduler, postgres, redis, mailpit
├── CONTRIBUTING.md
├── VERSION
└── README.md                # (existe) ganha a seção de desenvolvimento
```

### 5.2 Compose: três arquivos com papéis distintos

Para não sobrepor histórias de dev, os papéis ficam fixados assim:

| Arquivo | Papel | Imagem |
|---|---|---|
| `docker-compose.yml` (raiz) | **Dev local**: `docker compose up` sobe tudo do zero | build de `docker/php/Dockerfile` |
| `infra/compose.yml` | **Homologação/produção**: uma VM por ambiente | imagens GHCR (`ccb-ebi-api`, `ccb-ebi-web`) por digest |
| `infra/compose.dev.yml` | **Override** de `infra/compose.yml` para depurar a stack de deploy (Mailpit, portas em 127.0.0.1) | idem |

O `infra/compose.dev.yml` continua funcionando como está (override de `infra/compose.yml`); ele não é o caminho de "subir do zero" do dia a dia. Essa é a responsabilidade do `docker-compose.yml` da raiz. A imagem de dev e a de produção partem do mesmo `docker/php/Dockerfile` (single artifact, promovível por digest — o que ADR-0004/0006 exigem); o que muda entre dev e prod é o comando do contêiner e o volume do código.

Serviços do `docker-compose.yml` (dev):

| Serviço | Imagem/Comando | Papel |
|---|---|---|
| `app` | build `docker/php/Dockerfile`, `octane:frankenphp` | API + portal Web |
| `horizon` | mesma imagem, `php artisan horizon` | filas |
| `scheduler` | mesma imagem, `php artisan schedule:work` | agendamentos |
| `postgres` | `postgres:17-alpine` | banco |
| `redis` | `redis:7-alpine` | fila, cache e sessão |
| `mailpit` | `axllent/mailpit` | e-mail em dev |

MinIO e ClamAV ficam comentados/configurados, exigidos só quando houver upload (E08).

**`vendor/` em volume Linux** (`vendordata`), como no `nfegov2.0`, para o autoloader não ler pelo bind mount do Windows.

**Imagem única, comando variável.** Os serviços `app`, `horizon` e `scheduler` usam a **mesma imagem** (build do `docker/php/Dockerfile`), mudando só o `command`. Base: `php:8.4-cli` com FrankenPHP e as extensões do Laravel (pdo_pgsql, zip, intl, mbstring, bcmath, pcntl, opcache, gd, exif) + phpredis. O modo Octane exige **cuidado com estado persistente entre requisições**: serviços e singletons não guardam estado mutável por request.

### 5.3 Módulos do domínio (`app/Domain/`)

Espelham a [arquitetura de referência](../../06-arquitetura/arquitetura-de-referencia.md) e o ADR-0002:

`Identidade`, `Organizacao`, `Participantes`, `Agenda`, `Sessoes`, `Conteudo`, `Feedback`, `Indicadores`, `Notificacoes`, `Auditoria`.

**Onde vivem os modelos Eloquent.** Cada módulo é dono de suas tabelas e de seus **modelos**: `app/Domain/<Módulo>/Models/*.php`. Não há `app/Models/` compartilhado — isso evitaria justamente a fronteira que o ADR-0002 exige. Um modelo pertence ao módulo da sua tabela; ninguém mais o usa diretamente. O acesso de fora se dá por uma **interface pública** do módulo (um contrato de consulta/serviço que devolve DTOs, não modelos). O `app/Models/` só existe se um dia houver um modelo genuinamente transversal, e o teste de arquitetura impede que ele vire atalho para furar a fronteira.

**Alcance da regra de fronteira.** O teste de arquitetura (Pest `arch()` ou Deptrac — decidido no plano de implementação) verifica, no mínimo:

1. Nenhuma classe fora do módulo `X` pode depender de `App\Domain\X\Models\*` (consumo de modelo alheio).
2. Um módulo só pode depender da **superfície pública** de outro módulo (ex.: `App\Domain\X\Contracts\*` ou `App\Domain\X\PublicApi\*`), nunca das suas classes internas.
3. `app/Http/` (controllers, requests, resources) fala com o domínio só pelas interfaces públicas — não importa modelos internos de módulo.

Nesta leva os módulos nascem vazios (só a estrutura e o teste que os protege). Serão preenchidos nas etapas seguintes.

### 5.4 Base comum da API

- Prefixo `/api/v1`; JSON UTF-8; rotas e campos em inglês.
- Erros em `application/problem+json` (RFC 9457) com `type`, `title`, `status`, `detail`, `code` — implementado em `App\Support\ProblemDetails`.
- Paginação por cursor (`?cursor=&limit=`), `limit` máximo 100.
- `Idempotency-Key` para escritas críticas.
- Rate limit por usuário e por IP, com `429` e `Retry-After`.
- Healthcheck em `/up` (convenção Laravel) e um `/api/v1/health` que verifica banco e Redis.

**Endpoints concretos da Etapa 1** (os únicos; o resto é E02+):

| Método | Rota | Papel |
|---|---|---|
| `GET` | `/up` | liveness (convenção Laravel, sem auth) |
| `GET` | `/api/v1/health` | readiness: verifica banco e Redis, devolve `status` e `checks` |
| `POST` | `/api/v1/auth/login` | **login de mentira**: cria um ator fictício e devolve um token de sessão (escopo desta leva, ver §8) |
| `POST` | `/api/v1/auth/logout` | encerra a sessão de mentira |
| `GET` | `/api/v1/me` | devolve o ator autenticado, para o portal provar o fluxo ponta a ponta |

Todos entram no `docs/04-api/openapi.yaml` (fonte da verdade) **e** no catálogo `docs/04-api/endpoints.md`, de modo que o `scripts/check-endpoints.py` continue verde. Adicionar um endpoint de negócio em etapa futura é: OpenAPI → catálogo → código, nessa ordem.

**Cliente da API gerado do OpenAPI.** Sim: o frontend gera seu cliente a partir de `docs/04-api/openapi.yaml` via codegen (`openapi-typescript` para tipos + `openapi-fetch`, ou equivalente — decidido no plano de implementação). O CI regenera e falha se o spec mudou sem o cliente ser atualizado, garantindo que spec e portal não divirjam. O spec é a fonte da verdade declarada; o codegen é o que a torna executável.

### 5.5 Trilha de auditoria (RF-16)

Tabela `evento_auditoria` (campos do [modelo conceitual](../../03-dados/modelo-conceitual.md): `ator_id`, `ação`, `recurso`, `escopo`, `timestamp`, `origem`, `motivo`, `metadados_minimizados`, `hash_encadeado`). **Append-only**: sem update nem delete.

**O que entra nesta leva** (só a infraestrutura — sem eventos de negócio):

- **Migração** da tabela, com `hash_encadeado` e as colunas da cadeia (`hash_anterior`, `sequencia`), índices por `ator_id`/`recurso`/`timestamp`.
- **API de registro** (`App\Domain\Auditoria\...`): um serviço que recebe o evento de negócio e grava a linha. O *chaining* é **real e implementado**, não decorativo: cada evento calcula `hash_encadeado = SHA-256(normalização)` onde `normalização = hash_anterior + sequencia + campos_do_evento_canônicos`. A **serialização canônica** dos campos é fixada (JSON com chaves ordenadas, datas em UTC ISO-8601, `metadados_minimizados` só com o mínimo necessário) para o hash ser reprodutível entre PHP e qualquer verificador futuro.
- **Guarda de imutabilidade** no modelo Eloquent (`updating`/`deleting` lançam exceção) **e** teste que prova que um `UPDATE`/`DELETE` pela aplicação falha.
- **Teste de integridade da cadeia**: grava N eventos e verifica que a cadeia fecha (alterar uma linha no meio invalida as seguintes). O verificador de cadeia (comando/rota de auditoria) entra em etapa de negócio, não aqui.

O que **não** entra: eventos de negócio reais (nascem em E02+), retenção/expurgo, exportação. Assim a Etapa 1 entrega "nenhuma escrita sensível nasce sem registro", e o *chaining* já é verdadeiro desde o primeiro evento.

### 5.6 Portal Web

Projeto React + TypeScript com Vite; cliente da API **gerado do OpenAPI** (`openapi.yaml` é a fonte da verdade); uma tela de login de mentira; layout responsivo e acessível (alvo WCAG 2.2 AA, RNF-05). O build vai para `backend/public/spa` (padrão do `nfegov2.0`), servido pelo FrankenPHP.

### 5.7 CI (Etapa 0)

GitHub Actions com: Pint, PHPStan (nível alto), ESLint, testes (phpunit/Pest), validação do OpenAPI (Spectral), `scripts/check-endpoints.py`, build das imagens e publicação no GHCR com digest. Proteção de `main`: só por PR, com CI verde e uma revisão. Modelo de PR com campo "RF/CA atendidos", `CODEOWNERS`, Dependabot, secret scanning.

**Baseline verde no dia 1.** Verificado: `scripts/check-endpoints.py` **já passa hoje** (as 2 operações do `openapi.yaml` — `/auth/login` e `/sessions/{sessionId}/attendance` — estão marcadas no catálogo; a terceira linha "no OpenAPI" é texto de cabeçalho, não uma rota). O Spectral também deve nascer verde sobre o spec atual, com as regras ajustadas ao que o spec realmente é (`oas3`, sem exigir campos que a v2.7 ainda não preencheu). Os únicos jobs que estreiam "vazios" são os de lint/teste de código, que passam triviais com o esqueleto. Assim a Etapa 0 não tem dívida de CI "amarelo": o primeiro PR de exemplo já passa.

Se o Spectral acusar algo no spec existente que não é da Etapa 0 corrigir, a regra é **ajustar a configuração do Spectral ao spec atual e registrar a dívida**, nunca "consertar" o spec de negócio dentro desta leva.

### 5.8 Configuração twelve-factor

Segredos por arquivo (`*_FILE`), como o `infra/compose.yml` já faz; logs JSON em stdout, sem dados pessoais; configuração por variável de ambiente.

## 6. O que fica para o construtor decidir

- Versões exatas de Laravel 13.x, extensões PHP e dependências do frontend (usar as estáveis atuais).
- Ferramenta de teste de arquitetura: Pest `arch()` vs. Deptrac (ambas constam no plano; escolher e justificar no PR).
- Ferramenta de codegen do cliente (no plano de implementação).
- Nome/formato do `VERSION` (o plano não fixa SemVer; o `nfegov2.0` usa arquivo único).
- Modelo de PR exato e conteúdo do `CODEOWNERS`.
- Se o FrankenPHP serve o SPA estático ou se um serviço `web` mínimo (nginx) cuida disso — decidir ao montar, medindo.
- Formato exato do token e da sessão de mentira (§8) — contrato fixado, mecanismo a critério.

## 7. Riscos

- **Octane e estado entre requisições**: serviços com estado global podem vazar entre requisições. Mitigação: revisão e teste de concorrência básico.
- **Bind mount no Windows**: mitigado pelo `vendor/` em volume.
- **Escopo inflando para negócio**: mitigado pelos não objetivos acima; qualquer regra de negócio entra em etapa própria.

## 8. Login de mentira — decisão

**Escopo (decidido): guard de autenticação real, com sujeito fictício.** Existe middleware de auth de verdade; `POST /api/v1/auth/login` emite um token (Sanctum) para um **ator semeado** (fictício, de Brasília, criado por seeder); as rotas protegidas exigem esse token; `POST /api/v1/auth/logout` revoga a sessão. Isso dá, já na Etapa 1:

- **Rate limit por usuário** com sujeito real (além do por IP), com `429` e `Retry-After`.
- **`ator_id` verdadeiro** na trilha de auditoria, cumprindo "nenhuma escrita sensível nasce sem registro".

O token e o usuário são, por natureza, descartáveis: a autenticação real (MFA, papéis, `X-Acting-Scope`) entra em E3 e substitui este guard. Formato exato do token e da sessão ficam a critério do construtor (§6); o contrato das rotas acima fica fixado.

## 9. Onde este desenho vive

`docs/superpowers/specs/2026-11-08-fundacao-etapas-0-1-design.md`, versionado. Depois da aprovação, o próximo passo é o plano de implementação (skill `writing-plans`).