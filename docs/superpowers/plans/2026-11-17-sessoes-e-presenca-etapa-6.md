# Sessões e presença (E04) — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar a Etapa 6 — agenda do EBI, sessões previstas, operação do dia, presença idempotente com elegibilidade, estorno, histórico, contingência em papel e aviso de equipe mínima.

**Architecture:** Dois módulos novos no monolito modular (ADR-0002): `Agenda` (faixas semanais + exceções) e `Sessoes` (sessão, correção, presença). `Sessoes` consome apenas `Contracts` de `Agenda`, `Participantes` e `Organizacao`; o cancelamento de sessões por exceção de agenda é orquestrado no controller (`app/Http`), evitando ciclo entre módulos.

**Tech Stack:** Laravel 12 + Pest (backend, PHP 8.4 em Docker), React 19 + TypeScript + Vite + Tailwind v4 + shadcn (frontend, Node do host), Postgres em produção e SQLite em memória nos testes.

**Spec:** `docs/superpowers/specs/2026-11-13-sessoes-e-presenca-etapa-6-design.md`

## Global Constraints

- PHP roda **sempre** em contêiner: `docker compose run --rm app <comando>`; frontend usa o Node do host em `frontend/`.
- TDD no backend (Pest). Frontend sem runner de teste: valida por `npm run lint` e `npm run build` (ADR-0022).
- Contrato em ordem **OpenAPI → catálogo (`docs/04-api/endpoints.md`, linha com `no OpenAPI`) → código**; `scripts/check-endpoints.py` e Spectral verdes; `frontend/src/api/schema.d.ts` regenerado.
- Rotas em inglês (convenções), payloads em português como no código atual (`matricula`, `nome`, `participante_id`, `motivo`).
- IDs ULID (`ulid('id')->primary()`, `foreignUlid(...)`), timestamps `timestamptz` para instantes; data local da igreja decide idade.
- Escritas críticas aceitam `Idempotency-Key` (`IdempotenciaMiddleware`); presença também é idempotente pelo índice único parcial.
- Autorização pela matriz literal: `agenda.gerenciar` e `sessao.gerenciar` = adm_igreja + coordenadora; `presenca.registrar` = coordenadora + auxiliar; `presenca.estornar` = adm_igreja + coordenadora; `sessao.ler`/`presenca.ler` conforme a matriz; super_admin só lê.
- Fronteira modular: fora de `app/Domain` não se importa `Models` de módulo; módulos só usam `Contracts` de outros.
- Sem `react-router` (estado `Tela` em `App.tsx`); sem Laravel Boost.
- Contêineres Octane não recarregam PHP: reiniciar `app`, `horizon` e `scheduler` após mudanças de código.
- Não criar tabela de escala (E07) nem relatórios (E06).
- Comandos de verificação:
  - Testes: `docker compose run --rm app php artisan test tests/Feature/Agenda/<Arquivo>.php`
  - Pint: `docker compose run --rm app vendor/bin/pint --test`
  - PHPStan: `docker compose run --rm app composer phpstan`
  - Contrato: `npx --yes @stoplight/spectral-cli lint docs/04-api/openapi.yaml --ruleset scripts/.spectral.yaml` e `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"`
  - Frontend: em `frontend/`, `npm run gen:api`, `npm run lint`, `npm run build`

## Review Focus

1. **Fuso da igreja na virada do dia** — uma faixa às 19:30 em `America/Manaus` deve gerar sessão no dia local correto, não no dia UTC. Teste em Task 9.
2. **Duas presenças simultâneas da mesma criança na mesma sessão** — uma grava, a outra devolve a existente; nunca 500 de índice único. Teste de índice em Task 6 e de replay em Task 11.
3. **Nascido em 29/02 e sessão em fuso diferente** — aniversário em 01/03 de ano não bissexto, data local da sessão decide. Teste em Task 5 (integração `avaliarPorId`).
4. **Exceção criada depois da geração** — feriado cancela só `prevista` do dia; `aberta`/`encerrada` intactas e cancelada não ressuscita no comando. Testes em Tasks 8 e 9.
5. **Busca com acento/ç e termo curto** — 3+ caracteres, acento-insensível, máximo 20 resultados, finalidade obrigatória. Teste em Task 11.

---

### Task 1: Contrato — agenda, sessões e seletor de EBI

**Files:**
- Modify: `docs/04-api/openapi.yaml`
- Modify: `docs/04-api/endpoints.md`

**Interfaces:**
- Consumes: nada.
- Produces: operações e schemas que as Tasks 7–10 e 14 usam (`AgendaEbi`, `AgendaExcecao`, `Sessao`, `SessaoDetalhe`, `EquipeMinima`, `GeracaoSessoes`, `EbiResumo`).

- [ ] **Step 1: Adicionar parâmetros de path**

Em `components.parameters`, acrescentar (seguindo o padrão `SessaoId`):

```yaml
    AgendaId:
      name: id
      in: path
      required: true
      schema: { type: string }
    ExcecaoAgendaId:
      name: id
      in: path
      required: true
      schema: { type: string }
```

Não remover `SessionId` nesta task (a Task 2 troca o uso por `SessaoId`).

- [ ] **Step 2: Adicionar as paths (todas com `security: [bearerAuth: []]` e `X-Acting-Scope`)**

| Path | Método | Sucesso | Erros |
|---|---|---|---|
| `/me/ebis` | get | 200 `{data: [EbiResumo]}` | 401 |
| `/ebis/{id}/agenda` | get | 200 `{data: [AgendaEbi]}` | 401, 403 |
| `/ebis/{id}/agenda` | post | 201 `AgendaEbi` | 401, 403, 422 |
| `/agenda/{id}` | patch | 200 `AgendaEbi` | 401, 403, 404, 422 |
| `/agenda/{id}` | delete | 204 | 401, 403, 404 |
| `/ebis/{id}/agenda-exceptions` | get | 200 `{data: [AgendaExcecao]}` | 401, 403 |
| `/ebis/{id}/agenda-exceptions` | post | 201 `AgendaExcecao` | 401, 403, 422 |
| `/agenda-exceptions/{id}` | delete | 204 | 401, 403, 404 |
| `/ebis/{id}/sessions` | get | 200 `{data: [Sessao]}` | 401, 403, 422 |
| `/ebis/{id}/sessions/generate` | post | 201 `GeracaoSessoes` | 401, 403, 422 |
| `/sessions/{id}` | get | 200 `SessaoDetalhe` | 401, 403, 404 |
| `/sessions/{id}/open` | post | 200 `Sessao` | 401, 403, 404, 409 |
| `/sessions/{id}/close` | post | 200 `Sessao` | 401, 403, 404, 409, 422 |
| `/sessions/{id}/cancel` | post | 200 `Sessao` | 401, 403, 404, 409, 422 |
| `/sessions/{id}` | patch | 200 `Sessao` | 401, 403, 404, 409, 422 |

Descrições que o contrato fixa (copiar o sentido):
- `GET /ebis/{id}/sessions` exige `de` e `ate` (datas ISO), intervalo máximo de 92 dias, ordenado por `inicio_previsto`.
- `POST /ebis/{id}/sessions/generate` aceita `{de?, ate?, semanas?}` (padrão 4 semanas, máximo 12) e é idempotente.
- `POST /sessions/{id}/close` exige `realizada` (boolean) e aceita `fim_real?`.
- `PATCH /sessions/{id}` aceita `tema`, `passagem_biblica`, `inicio_real`, `fim_real`, `realizada`, `auxiliares_escalados` e `motivo` (obrigatório quando a sessão está `encerrada`).

- [ ] **Step 3: Adicionar os schemas**

```yaml
    EbiResumo:
      type: object
      properties:
        id: { type: string }
        nome: { type: string }
        igreja_id: { type: string }
        igreja_nome: { type: string }
        timezone: { type: string }
    AgendaEbi:
      type: object
      properties:
        id: { type: string }
        ebi_id: { type: string }
        dia_semana: { type: integer, minimum: 1, maximum: 7 }
        hora_inicio: { type: string, description: 'HH:MM no fuso da igreja' }
        hora_fim: { type: string }
        inicio_vigencia: { type: string, format: date }
        fim_vigencia: { type: [string, 'null'], format: date }
        vigente: { type: boolean }
    AgendaEbiInput:
      type: object
      required: [dia_semana, hora_inicio, hora_fim, inicio_vigencia]
      properties:
        dia_semana: { type: integer, minimum: 1, maximum: 7 }
        hora_inicio: { type: string, pattern: '^([01][0-9]|2[0-3]):[0-5][0-9]$' }
        hora_fim: { type: string, pattern: '^([01][0-9]|2[0-3]):[0-5][0-9]$' }
        inicio_vigencia: { type: string, format: date }
        fim_vigencia: { type: [string, 'null'], format: date }
    AgendaExcecao:
      type: object
      properties:
        id: { type: string }
        ebi_id: { type: string }
        agenda_ebi_id: { type: [string, 'null'] }
        data: { type: string, format: date }
        tipo: { type: string, enum: [feriado, cancelamento, horario_especial] }
        hora_inicio: { type: [string, 'null'] }
        hora_fim: { type: [string, 'null'] }
        motivo: { type: string }
    AgendaExcecaoInput:
      type: object
      required: [tipo, data, motivo]
      properties:
        tipo: { type: string, enum: [feriado, cancelamento, horario_especial] }
        data: { type: string, format: date }
        agenda_ebi_id: { type: [string, 'null'] }
        hora_inicio: { type: [string, 'null'] }
        hora_fim: { type: [string, 'null'] }
        motivo: { type: string, minLength: 3, maxLength: 500 }
    Sessao:
      type: object
      properties:
        id: { type: string }
        ebi_id: { type: string }
        ebi_nome: { type: string }
        igreja_id: { type: string }
        agenda_ebi_id: { type: [string, 'null'] }
        inicio_previsto: { type: string, format: date-time }
        fim_previsto: { type: string, format: date-time }
        inicio_real: { type: [string, 'null'], format: date-time }
        fim_real: { type: [string, 'null'], format: date-time }
        tema: { type: [string, 'null'] }
        passagem_biblica: { type: [string, 'null'] }
        status: { type: string, enum: [prevista, aberta, encerrada, cancelada] }
        realizada: { type: [boolean, 'null'] }
        motivo_cancelamento: { type: [string, 'null'] }
        auxiliares_escalados: { type: [integer, 'null'], minimum: 0 }
        presentes: { type: integer, minimum: 0 }
    EquipeMinima:
      type: object
      properties:
        presentes: { type: integer }
        participantes_por_auxiliar: { type: integer }
        auxiliares_necessarios: { type: integer }
        auxiliares_informados: { type: [integer, 'null'] }
        alerta: { type: boolean }
    SessaoDetalhe:
      allOf:
        - $ref: '#/components/schemas/Sessao'
        - type: object
          properties:
            equipe_minima: { $ref: '#/components/schemas/EquipeMinima' }
    SessaoFechamentoInput:
      type: object
      required: [realizada]
      properties:
        realizada: { type: boolean }
        fim_real: { type: [string, 'null'], format: date-time }
    SessaoCorrecaoInput:
      type: object
      properties:
        tema: { type: [string, 'null'], maxLength: 255 }
        passagem_biblica: { type: [string, 'null'], maxLength: 255 }
        inicio_real: { type: [string, 'null'], format: date-time }
        fim_real: { type: [string, 'null'], format: date-time }
        realizada: { type: [boolean, 'null'] }
        auxiliares_escalados: { type: [integer, 'null'], minimum: 0 }
        motivo: { type: [string, 'null'], minLength: 3, maxLength: 500 }
    GeracaoSessoes:
      type: object
      properties:
        criadas: { type: integer }
        ignoradas: { type: integer }
        de: { type: string, format: date }
        ate: { type: string, format: date }
```

- [ ] **Step 4: Marcar no catálogo**

Em `docs/04-api/endpoints.md`, na seção "Agenda, sessões e presença", acrescentar uma linha por operação nova, todas terminando com ``(`no OpenAPI`)``, por exemplo:

```markdown
| GET | `/me/ebis` | EBIs que o usuário pode operar *novo* (`no OpenAPI`) | RF-06 |
| GET/POST | `/ebis/{id}/agenda` | Faixas semanais do EBI *novo* (`no OpenAPI`) | RF-06 |
| PATCH/DELETE | `/agenda/{id}` | Alterar/encerrar faixa *novo* (`no OpenAPI`) | RF-06 |
| GET/POST | `/ebis/{id}/agenda-exceptions` | Exceções da agenda *novo* (`no OpenAPI`) | RF-06 |
| DELETE | `/agenda-exceptions/{id}` | Remover exceção *novo* (`no OpenAPI`) | RF-06 |
| GET | `/ebis/{id}/sessions` | Sessões do EBI no período (`no OpenAPI`) | RF-06 |
| POST | `/ebis/{id}/sessions/generate` | Gerar sessões previstas *novo* (`no OpenAPI`) | RF-06 |
| GET/PATCH | `/sessions/{id}` | Detalhe e correção da sessão *novo* (`no OpenAPI`) | RF-06 |
| POST | `/sessions/{id}/open`, `/sessions/{id}/close`, `/sessions/{id}/cancel` | Ciclo da sessão *novo* (`no OpenAPI`) | RF-06 |
```

Atenção: o script usa o **produto cartesiano** método × caminho da linha; `GET/PATCH` numa linha com **um** caminho gera duas operações, o que é o desejado aqui.

- [ ] **Step 5: Verificar contrato**

Run: `npx --yes @stoplight/spectral-cli lint docs/04-api/openapi.yaml --ruleset scripts/.spectral.yaml`
Expected: `No results with a severity of 'error' found!`

Run: `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"`
Expected: `OK: <N> operações alinhadas.`

- [ ] **Step 6: Commit**

```bash
git add docs/04-api/openapi.yaml docs/04-api/endpoints.md
git commit -m "docs(api): contrato de agenda, sessões e seletor de EBI (E04)"
```

---

### Task 2: Contrato — presença, estorno e histórico

**Files:**
- Modify: `docs/04-api/openapi.yaml`
- Modify: `docs/04-api/endpoints.md`
- Regenerate: `frontend/src/api/schema.d.ts`

**Interfaces:**
- Consumes: Task 1 (mesmo arquivo, tags e padrões).
- Produces: `CandidatoPresenca`, `Presenca`, `PresencaRegistroInput`, `PresencaContingenciaInput`, `EstornoInput`, `HistoricoPresenca`; tipos usados pelas Tasks 11–16.

- [ ] **Step 1: Reescrever a operação de presença existente**

Substituir `/sessions/{sessionId}/attendance` por `/sessions/{id}/attendance` (parâmetro `SessaoId`), com:
- `post`: body `PresencaRegistroInput` → `201 {presenca, aviso?}`, `200` descrição "Presença já existente", `403`, `404`, `409`, `422`.
- `get`: 200 `{data: [Presenca]}`.

Trocar o schema `Attendance` (rascunho em inglês) pelos schemas abaixo e remover `Attendance` e o parâmetro `SessionId` se ficarem sem uso.

- [ ] **Step 2: Adicionar as demais paths**

| Path | Método | Sucesso | Erros |
|---|---|---|---|
| `/sessions/{id}/participants` | get | 200 `{data: [CandidatoPresenca]}` | 401, 403, 404, 422 |
| `/sessions/{id}/contingency-attendance` | post | 201 `{presenca}` | 401, 403, 404, 409, 422 |
| `/attendance/{id}/reversal` | post | 200 `Presenca` | 401, 403, 404, 409, 422 |
| `/participants/{id}/attendance` | get | 200 `{data: [HistoricoPresenca]}` | 401, 403, 404 |
| `/me/participants/{id}/attendance` | get | 200 `{data: [HistoricoPresenca]}` | 401, 403, 404 |

`GET /sessions/{id}/participants` exige `busca` (3+ caracteres) e `finalidade` (parâmetro `FinalidadeLeitura`) e devolve no máximo 20 resultados.

- [ ] **Step 3: Adicionar os schemas**

```yaml
    CandidatoPresenca:
      type: object
      properties:
        id: { type: string }
        nome: { type: string }
        matricula: { type: string }
        igreja_referencia:
          oneOf:
            - type: object
              properties:
                id: { type: string }
                nome: { type: string }
            - type: 'null'
        elegivel: { type: boolean }
        motivo_bloqueio: { type: [string, 'null'] }
    Presenca:
      type: object
      properties:
        id: { type: string }
        sessao_id: { type: string }
        participante:
          type: object
          properties:
            id: { type: string }
            nome: { type: string }
            matricula: { type: string }
        registrado_por: { type: string }
        registrado_em: { type: string, format: date-time }
        origem: { type: string, enum: [web, app, contingencia] }
        status: { type: string, enum: [valida, estornada] }
        estornado_em: { type: [string, 'null'], format: date-time }
        motivo_estorno: { type: [string, 'null'] }
    PresencaRegistroInput:
      type: object
      required: [participante_id]
      properties:
        participante_id: { type: string }
        origem: { type: string, enum: [web, app], default: web }
    PresencaContingenciaInput:
      type: object
      required: [participante_id, motivo]
      properties:
        participante_id: { type: string }
        motivo: { type: string, minLength: 3, maxLength: 500 }
    EstornoInput:
      type: object
      required: [motivo]
      properties:
        motivo: { type: string, minLength: 3, maxLength: 500 }
    HistoricoPresenca:
      type: object
      properties:
        id: { type: string }
        sessao_id: { type: string }
        ebi:
          type: object
          properties:
            id: { type: string }
            nome: { type: string }
        inicio_previsto: { type: string, format: date-time }
        status: { type: string, enum: [valida, estornada] }
        registrado_em: { type: string, format: date-time }
```

- [ ] **Step 4: Marcar no catálogo**

```markdown
| GET | `/sessions/{id}/participants` | Buscar participantes elegíveis na sessão (finalidade obrigatória) *novo* (`no OpenAPI`) | RF-08 |
| GET | `/sessions/{id}/attendance` | Presenças da sessão *novo* (`no OpenAPI`) | RF-08 |
| POST | `/sessions/{id}/contingency-attendance` | Lançar presença de lista em papel *novo* (`no OpenAPI`) | RNF-03 |
| GET | `/participants/{id}/attendance` | Histórico de presenças (operador) *novo* (`no OpenAPI`) | RF-08 |
| GET | `/me/participants/{id}/attendance` | Histórico de presenças do dependente *novo* (`no OpenAPI`) | RF-08 |
```

Atualizar a linha existente de `POST /sessions/{id}/attendance` se necessário (deve continuar marcada `no OpenAPI`).

- [ ] **Step 5: Verificar contrato e regenerar tipos**

Run: `npx --yes @stoplight/spectral-cli lint docs/04-api/openapi.yaml --ruleset scripts/.spectral.yaml` → sem erros.
Run: `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"` → `OK`.
Run, em `frontend/`: `npm run gen:api` → `schema.d.ts` atualizado.
Run, em `frontend/`: `npm run lint` → sem erros.
Run, em `frontend/`: `npm run build` → build conclui (os tipos novos ainda não são usados).

- [ ] **Step 6: Commit**

```bash
git add docs/04-api/openapi.yaml docs/04-api/endpoints.md frontend/src/api/schema.d.ts
git commit -m "docs(api): contrato de presença, estorno e histórico (E04)"
```

---

### Task 3: Autorização das novas operações

**Files:**
- Modify: `backend/app/Domain/Identidade/Support/Operacoes.php`
- Modify: `backend/app/Domain/Identidade/Services/AutorizadorOperacao.php`
- Create: `backend/app/Policies/AgendaPolicy.php`
- Create: `backend/app/Policies/SessaoPolicy.php`
- Create: `backend/app/Policies/PresencaPolicy.php`
- Modify: `backend/app/Providers/ApiServiceProvider.php`
- Modify: `docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md`
- Test: `backend/tests/Feature/Agenda/AutorizacaoTest.php`

**Interfaces:**
- Consumes: `AutorizarOperacao::permite(ContextoAtuacao, string $operacao, ?string $tipoRecurso, ?string $recursoId): bool`.
- Produces: constantes `Operacoes::AGENDA_GERENCIAR`, `SESSAO_LER`, `SESSAO_GERENCIAR`, `PRESENCA_LER`, `PRESENCA_REGISTRAR`, `PRESENCA_ESTORNAR` e as abilities de Gate de mesmo nome, com assinatura de policy `(Authenticatable $usuario, ?string $tipoRecurso = null, ?string $recursoId = null): bool`.

- [ ] **Step 1: Escrever o teste de matriz**

```php
<?php

use App\Domain\Identidade\Services\AutorizadorOperacao;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Identidade\Support\Operacoes;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use Database\Seeders\PapelSeeder;

beforeEach(function () {
    $this->seed(PapelSeeder::class);

    $h = Mockery::mock(ConsultarHierarquia::class);
    $h->shouldReceive('ancestrais')->andReturn([
        'ebi' => 'e1', 'igreja' => 'i1', 'setor' => 's1', 'regional' => 'r1', 'central' => 'c1',
    ]);
    app()->instance(ConsultarHierarquia::class, $h);

    $this->autz = app(AutorizadorOperacao::class);
    $this->ctx = fn (string $papel, string $tipo = 'ebi', string $id = 'e1') =>
        new ContextoAtuacao(papelCodigo: $papel, tipoEscopo: $tipo, escopoId: $id);
});

it('aplica a matriz de agenda, sessão e presença', function () {
    $ctx = $this->ctx;
    $a = $this->autz;

    expect($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::AGENDA_GERENCIAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('coordenadora'), Operacoes::AGENDA_GERENCIAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('auxiliar'), Operacoes::AGENDA_GERENCIAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('super_admin', 'central', 'c1'), Operacoes::AGENDA_GERENCIAR, 'ebi', 'e1'))->toBeFalse()

        ->and($a->permite($ctx('auxiliar'), Operacoes::SESSAO_LER, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('auxiliar'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('adm_regional', 'regional', 'r1'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse()

        ->and($a->permite($ctx('auxiliar'), Operacoes::PRESENCA_REGISTRAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::PRESENCA_REGISTRAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('adm_igreja', 'igreja', 'i1'), Operacoes::PRESENCA_ESTORNAR, 'ebi', 'e1'))->toBeTrue()
        ->and($a->permite($ctx('auxiliar'), Operacoes::PRESENCA_ESTORNAR, 'ebi', 'e1'))->toBeFalse()
        ->and($a->permite($ctx('ministerio_local', 'igreja', 'i1'), Operacoes::PRESENCA_LER, 'ebi', 'e1'))->toBeTrue();
});

it('nega escopo horizontal em outra EBI', function () {
    $ctx = $this->ctx;

    expect($this->autz->permite($ctx('coordenadora', 'ebi', 'outra'), Operacoes::SESSAO_GERENCIAR, 'ebi', 'e1'))->toBeFalse();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/AutorizacaoTest.php`
Expected: FAIL — `Undefined constant App\Domain\Identidade\Support\Operacoes::AGENDA_GERENCIAR`.

- [ ] **Step 3: Implementar catálogo, matriz e policies**

Em `Operacoes`, acrescentar:

```php
    // E04 — Agenda, sessões e presença (RF-06, RF-08).
    public const AGENDA_GERENCIAR = 'agenda.gerenciar';
    public const SESSAO_LER = 'sessao.ler';
    public const SESSAO_GERENCIAR = 'sessao.gerenciar';
    public const PRESENCA_LER = 'presenca.ler';
    public const PRESENCA_REGISTRAR = 'presenca.registrar';
    public const PRESENCA_ESTORNAR = 'presenca.estornar';
```

Em `AutorizadorOperacao::MATRIZ`, acrescentar exatamente:

| Papel | Operações novas |
|---|---|
| super_admin | `SESSAO_LER`, `PRESENCA_LER` |
| adm_regional | `SESSAO_LER`, `PRESENCA_LER` |
| adm_setor | `SESSAO_LER`, `PRESENCA_LER` |
| adm_igreja | `AGENDA_GERENCIAR`, `SESSAO_LER`, `SESSAO_GERENCIAR`, `PRESENCA_LER`, `PRESENCA_ESTORNAR` |
| ministerio_local | `SESSAO_LER`, `PRESENCA_LER` |
| coordenadora | `AGENDA_GERENCIAR`, `SESSAO_LER`, `SESSAO_GERENCIAR`, `PRESENCA_LER`, `PRESENCA_REGISTRAR`, `PRESENCA_ESTORNAR` |
| auxiliar | `SESSAO_LER`, `PRESENCA_LER`, `PRESENCA_REGISTRAR` |

As três policies seguem o molde de `ParticipantePolicy` (injetam `AutorizarOperacao` e `Request`, leem `contexto_atuacao`):
- `AgendaPolicy::gerenciar` → `AGENDA_GERENCIAR`.
- `SessaoPolicy::ler` / `SessaoPolicy::gerenciar`.
- `PresencaPolicy::ler` / `registrar` / `estornar`.

Em `ApiServiceProvider::boot`, registrar:

```php
        Gate::define(Operacoes::AGENDA_GERENCIAR, [AgendaPolicy::class, 'gerenciar']);
        Gate::define(Operacoes::SESSAO_LER, [SessaoPolicy::class, 'ler']);
        Gate::define(Operacoes::SESSAO_GERENCIAR, [SessaoPolicy::class, 'gerenciar']);
        Gate::define(Operacoes::PRESENCA_LER, [PresencaPolicy::class, 'ler']);
        Gate::define(Operacoes::PRESENCA_REGISTRAR, [PresencaPolicy::class, 'registrar']);
        Gate::define(Operacoes::PRESENCA_ESTORNAR, [PresencaPolicy::class, 'estornar']);
```

Atualizar a tabela "Operações implementadas" em `docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md` com as seis linhas (operações, papéis e rotas das Tasks 7–13).

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/AutorizacaoTest.php`
Expected: PASS (2 testes).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Identidade/Support/Operacoes.php backend/app/Domain/Identidade/Services/AutorizadorOperacao.php backend/app/Policies/AgendaPolicy.php backend/app/Policies/SessaoPolicy.php backend/app/Policies/PresencaPolicy.php backend/app/Providers/ApiServiceProvider.php backend/tests/Feature/Agenda/AutorizacaoTest.php docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md
git commit -m "feat(authz): operações de agenda, sessão e presença (E04)"
```

---

### Task 4: Fronteira `ConsultarEbi` e `GET /me/ebis`

**Files:**
- Create: `backend/app/Domain/Organizacao/Contracts/EbiDados.php`
- Create: `backend/app/Domain/Organizacao/Contracts/ConsultarEbi.php`
- Create: `backend/app/Domain/Organizacao/Services/ConsultaEbi.php`
- Modify: `backend/app/Domain/Organizacao/Contracts/ConsultarOrganizacao.php`
- Modify: `backend/app/Domain/Organizacao/Services/ConsultaOrganizacao.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Create: `backend/app/Http/Resources/EbiResumoResource.php`
- Modify: `backend/app/Http/Controllers/Api/V1/MeController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Agenda/MeEbisTest.php`

**Interfaces:**
- Consumes: `ConsultarVinculos::vigentesDoUsuario(Authenticatable): array<int, array{papel: string, tipo_escopo: string, escopo_id: string}>`; `ContextoAtuacao` da request.
- Produces:
  - `EbiDados` (readonly): `string $id, string $nome, string $igrejaId, string $igrejaNome, string $timezone, int $participantesPorAuxiliar`.
  - `ConsultarEbi::dados(string $ebiId): ?EbiDados`, `ConsultarEbi::listarPorEscopo(string $tipoEscopo, string $escopoId): array<int, EbiDados>`, `ConsultarEbi::todasAtivas(): array<int, EbiDados>`.
  - `ConsultarOrganizacao::nomesDeIgrejas(array $igrejaIds): array<string, string>`.
  - `GET /me/ebis` → `{data: [{id, nome, igreja_id, igreja_nome, timezone}]}`.

- [ ] **Step 1: Escrever o teste**

```php
<?php

beforeEach(function () {
    $this->seed(Database\Seeders\PapelSeeder::class);
});

it('devolve apenas as EBIs alcancadas pelos vinculos do ator', function () {
    ['igreja' => $igrejaA, 'ebi' => $ebiA] = igrejaComEbi();
    ['igreja' => $igrejaB, 'ebi' => $ebiB] = igrejaComEbi();

    $setor = App\Domain\Organizacao\Models\Setor::factory()->create();
    foreach ([$igrejaA, $igrejaB] as $igreja) {
        App\Domain\Organizacao\Models\IgrejaVinculacao::query()->create([
            'igreja_id' => $igreja->id,
            'setor_id' => $setor->id,
            'inicio' => now()->subYear()->toDateString(),
            'fim' => null,
        ]);
    }

    $adm = criarUsuarioComPapel('adm_setor', 'setor', $setor->id);
    $auth = [
        'Authorization' => 'Bearer '.$adm->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=adm_setor;escopo=setor:{$setor->id}",
    ];

    $this->getJson('/api/v1/me/ebis', $auth)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $ebiA->id, 'igreja_nome' => $igrejaA->nome])
        ->assertJsonFragment(['id' => $ebiB->id]);
});

it('o contexto de atuacao restringe a lista', function () {
    ['igreja' => $igreja, 'ebi' => $ebi] = igrejaComEbi();
    $outra = igrejaComEbi()['ebi'];

    $coord = criarUsuarioComPapel('coordenadora', 'ebi', $ebi->id);
    App\Domain\Identidade\Models\VinculoAcesso::factory()->create([
        'usuario_id' => $coord->id,
        'papel_id' => App\Domain\Identidade\Models\Papel::query()->where('codigo', 'coordenadora')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $outra->id,
        'inicio' => now()->subDay(),
    ]);

    $auth = [
        'Authorization' => 'Bearer '.$coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra->id}",
    ];

    $this->getJson('/api/v1/me/ebis', $auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $outra->id);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/MeEbisTest.php`
Expected: FAIL (404 — rota inexistente).

- [ ] **Step 3: Implementar contratos, serviço e rota**

`EbiDados` e `ConsultarEbi` seguem o estilo de `ConsultarHierarquia` (docblocks curtos, somente IDs opacos + dados de exibição). `ConsultaEbi` usa os models de `Organizacao` e resolve a hierarquia por relação:

```php
match ($tipoEscopo) {
    'central' => Ebi::query(),
    'regional' => Ebi::query()->whereHas('igreja.vinculacaoVigente.setor', fn ($q) => $q->where('regional_id', $escopoId)),
    'setor' => Ebi::query()->whereHas('igreja.vinculacaoVigente', fn ($q) => $q->where('setor_id', $escopoId)->whereNull('fim')),
    'igreja' => Ebi::query()->where('igreja_id', $escopoId),
    'ebi' => Ebi::query()->whereKey($escopoId),
    default => throw new RuntimeException("Tipo de escopo desconhecido: {$tipoEscopo}"),
};
```

Ordenar por `nome`; mapear cada `Ebi` (com `igreja`) para `EbiDados`. `todasAtivas()` = `where('status', 'ativo')`.

`ConsultaOrganizacao::nomesDeIgrejas`: `Igreja::query()->whereIn('id', $ids)->pluck('nome', 'id')->all()`.

`MeController::ebis(Request $request)`:
1. Lê `contexto_atuacao`; se existir, usa **apenas** `[$contexto->tipoEscopo, $contexto->escopoId]`; senão usa todos os `vigentesDoUsuario`.
2. Para cada alvo chama `listarPorEscopo`, deduplica por `id`, ordena por `nome`.
3. Devolve `EbiResumoResource::collection(...)->resolve($request)` dentro de `{data: ...}`.

Rota (dentro do grupo `auth:sanctum`, sem gate):

```php
Route::get('me/ebis', [MeController::class, 'ebis'])->name('me.ebis');
```

Bind em `DomainServiceProvider`: `$this->app->bind(ConsultarEbi::class, ConsultaEbi::class);`

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/MeEbisTest.php`
Expected: PASS (2 testes).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Organizacao/Contracts/EbiDados.php backend/app/Domain/Organizacao/Contracts/ConsultarEbi.php backend/app/Domain/Organizacao/Services/ConsultaEbi.php backend/app/Domain/Organizacao/Contracts/ConsultarOrganizacao.php backend/app/Domain/Organizacao/Services/ConsultaOrganizacao.php backend/app/Providers/DomainServiceProvider.php backend/app/Http/Resources/EbiResumoResource.php backend/app/Http/Controllers/Api/V1/MeController.php backend/routes/api.php backend/tests/Feature/Agenda/MeEbisTest.php
git commit -m "feat(organizacao): ConsultarEbi e seletor GET /me/ebis (E04)"
```

---

### Task 5: Fronteiras públicas de Participantes

**Files:**
- Move: `backend/app/Domain/Participantes/Services/ResultadoElegibilidade.php` → `backend/app/Domain/Participantes/Contracts/ResultadoElegibilidade.php` (ajustar namespace e imports)
- Create: `backend/app/Domain/Participantes/Contracts/AvaliarElegibilidade.php`
- Create: `backend/app/Domain/Participantes/Contracts/ParticipanteResumo.php`
- Create: `backend/app/Domain/Participantes/Contracts/ConsultarParticipante.php`
- Create: `backend/app/Domain/Participantes/Contracts/VerificarPropriedadeParticipante.php`
- Modify: `backend/app/Domain/Participantes/Services/AvaliadorElegibilidade.php`
- Create: `backend/app/Domain/Participantes/Services/ConsultaParticipante.php`
- Modify: `backend/app/Domain/Participantes/Services/VerificadorPropriedadeParticipante.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Modify: testes existentes que importam `Services\ResultadoElegibilidade` (`grep` antes)
- Test: `backend/tests/Feature/Agenda/FronteirasParticipanteTest.php`

**Interfaces:**
- Consumes: `Participante` (modelo interno), `ExcecaoEtaria`.
- Produces:
  - `Contracts\ResultadoElegibilidade` (mesma API: `permitido`, `bloqueado`, `bool $permitido`, `string $motivo`).
  - `AvaliarElegibilidade::avaliarPorId(string $participanteId, DateTimeInterface $dataSessao, string $fusoIgreja): ResultadoElegibilidade`.
  - `ParticipanteResumo` (readonly): `string $id, string $nome, string $matricula, ?string $igrejaReferenciaId`.
  - `ConsultarParticipante::buscar(string $termo, int $limite = 20): array<int, ParticipanteResumo>`, `encontrar(string $participanteId): ?ParticipanteResumo`.
  - `VerificarPropriedadeParticipante::podeAcessar(string $usuarioId, string $participanteId): bool`.

- [ ] **Step 1: Escrever o teste**

```php
<?php

use App\Domain\Participantes\Contracts\AvaliarElegibilidade;
use App\Domain\Participantes\Contracts\ConsultarParticipante;
use App\Domain\Participantes\Contracts\VerificarPropriedadeParticipante;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;

it('busca por nome sem acento e por matricula, no maximo 20', function () {
    $ana = Participante::factory()->create(['nome' => 'Ana Conceição', 'matricula' => 'ABC12345']);
    Participante::factory()->count(25)->create(['nome' => 'Ana Maria']);

    $busca = app(ConsultarParticipante::class);

    expect($busca->buscar('conceicao'))->toHaveCount(1)
        ->and($busca->buscar('conceicao')[0]->id)->toBe($ana->id)
        ->and($busca->buscar('ABC1'))->toHaveCount(1)
        ->and($busca->buscar('ana'))->toHaveCount(20)
        ->and($busca->buscar('an'))->toBe([]);
});

it('avalia a elegibilidade pelo id usando a data local', function () {
    $avaliar = app(AvaliarElegibilidade::class);

    $onze = Participante::factory()->create(['data_nascimento' => '2015-06-10']);
    $doze = Participante::factory()->create(['data_nascimento' => '2014-06-10']);

    expect($avaliar->avaliarPorId($onze->id, new DateTimeImmutable('2026-06-09 23:00:00'), 'America/Sao_Paulo')->permitido)->toBeTrue()
        ->and($avaliar->avaliarPorId($doze->id, new DateTimeImmutable('2026-06-10 02:00:00'), 'America/Sao_Paulo')->permitido)->toBeFalse()
        ->and($avaliar->avaliarPorId($doze->id, new DateTimeImmutable('2026-06-10 02:00:00'), 'America/Sao_Paulo')->motivo)->toBe('excecao_ausente_ou_invalida');

    ExcecaoEtaria::factory()->create([
        'participante_id' => $doze->id,
        'status' => ExcecaoEtaria::STATUS_APROVADA,
        'decidido_em' => now(),
        'vigente_ate' => '2026-12-31',
    ]);

    expect($avaliar->avaliarPorId($doze->id, new DateTimeImmutable('2026-06-10 02:00:00'), 'America/Sao_Paulo')->permitido)->toBeTrue();
});

it('trata 29 de fevereiro em ano nao bissexto', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2020-02-29']);

    $avaliar = app(AvaliarElegibilidade::class);

    // Em 2023 (nao bissexto) o aniversario cai em 01/03: em 28/02 ainda tem 2 anos.
    expect($avaliar->avaliarPorId($p->id, new DateTimeImmutable('2023-02-28 12:00:00'), 'America/Sao_Paulo')->motivo)->toBe('idade_minima')
        ->and($avaliar->avaliarPorId($p->id, new DateTimeImmutable('2023-03-01 12:00:00'), 'America/Sao_Paulo')->permitido)->toBeTrue();
});

it('verifica propriedade apenas com vinculo aprovado e vigente', function () {
    $p = Participante::factory()->create();
    $responsavel = criarUsuarioComPapel('responsavel');

    $verificar = app(VerificarPropriedadeParticipante::class);
    expect($verificar->podeAcessar($responsavel->id, $p->id))->toBeFalse();

    ResponsavelParticipante::factory()->create([
        'usuario_id' => $responsavel->id,
        'participante_id' => $p->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_APROVADA,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
    ]);

    expect($verificar->podeAcessar($responsavel->id, $p->id))->toBeTrue();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/FronteirasParticipanteTest.php`
Expected: FAIL — interfaces/classes não existem.

- [ ] **Step 3: Implementar**

- Mover `ResultadoElegibilidade` para `Contracts` e corrigir imports (`rg "ResultadoElegibilidade" backend`).
- `AvaliadorElegibilidade implements AvaliarElegibilidade`:

```php
public function avaliarPorId(string $participanteId, DateTimeInterface $dataSessao, string $fusoIgreja): ResultadoElegibilidade
{
    $participante = Participante::query()->find($participanteId)
        ?? throw new RuntimeException("Participante nao encontrado: {$participanteId}");

    return $this->avaliar($participante, $dataSessao, $fusoIgreja, $participante->excecaoVigente());
}
```

- `ConsultaParticipante`: normaliza o termo com `Str::ascii` + `mb_strtolower` (mesmo critério de `DetectorDuplicidade`); busca candidatos com `where('matricula', 'like', $termo.'%')->orWhere('nome', 'like', '%'.substr($termoNormalizado, 0, 3).'%')`, limite 100, filtra em PHP pelo termo normalizado completo (é isso que torna a busca acento-insensível), ordena por nome normalizado e corta em `$limite`. Exclui `status = 'mesclado'`. Termo com menos de 3 caracteres devolve `[]`.
- `VerificadorPropriedadeParticipante implements Contracts\VerificarPropriedadeParticipante` (sem alterar a lógica).
- Bind: `AvaliarElegibilidade::class => AvaliadorElegibilidade::class` e `ConsultarParticipante::class => ConsultaParticipante::class`.

- [ ] **Step 4: Rodar a task e a suíte de participantes**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/FronteirasParticipanteTest.php`
Expected: PASS (4 testes).

Run: `docker compose run --rm app php artisan test tests/Feature/Participantes`
Expected: PASS (imports do `ResultadoElegibilidade` ajustados).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Participantes backend/app/Providers/DomainServiceProvider.php backend/tests
git commit -m "feat(participantes): contratos de elegibilidade, busca e propriedade (E04)"
```

---

### Task 6: Dados da etapa (migrations, models e factories)

**Files:**
- Create: `backend/database/migrations/2026_11_17_100001_create_agenda_tables.php`
- Create: `backend/database/migrations/2026_11_17_100002_create_sessao_presenca_tables.php`
- Create: `backend/app/Domain/Agenda/Models/AgendaEbi.php`
- Create: `backend/app/Domain/Agenda/Models/AgendaExcecao.php`
- Create: `backend/app/Domain/Sessoes/Models/Sessao.php`
- Create: `backend/app/Domain/Sessoes/Models/SessaoCorrecao.php`
- Create: `backend/app/Domain/Sessoes/Models/Presenca.php`
- Create: `backend/app/Domain/Agenda/Database/Factories/AgendaEbiFactory.php`
- Create: `backend/app/Domain/Agenda/Database/Factories/AgendaExcecaoFactory.php`
- Create: `backend/app/Domain/Sessoes/Database/Factories/SessaoFactory.php`
- Create: `backend/app/Domain/Sessoes/Database/Factories/PresencaFactory.php`
- Create: `backend/app/Domain/Agenda/README.md` e `backend/app/Domain/Sessoes/README.md`
- Test: `backend/tests/Feature/Agenda/EsquemaTest.php`

**Interfaces:**
- Produces (usado pelas demais tasks):
  - `AgendaEbi` (`$table = 'agenda_ebi'`), constantes `STATUS_*`? não; campos: `ebi_id, dia_semana, hora_inicio, hora_fim, inicio_vigencia, fim_vigencia`.
  - `AgendaExcecao` (`agenda_excecao`), constantes `TIPO_FERIADO`, `TIPO_CANCELAMENTO`, `TIPO_HORARIO_ESPECIAL`.
  - `Sessao` (`sessao`), constantes `STATUS_PREVISTA|ABERTA|ENCERRADA|CANCELADA`, relations `presencas()`, `correcoes()`.
  - `SessaoCorrecao` (`sessao_correcao`, `casts` json).
  - `Presenca` (`presenca`), constantes `STATUS_VALIDA|ESTORNADA`, `ORIGEM_WEB|APP|CONTINGENCIA`.

- [ ] **Step 1: Escrever o teste de esquema**

```php
<?php

use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Agenda\Models\AgendaExcecao;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Database\QueryException;

it('nao permite duas presencas validas do mesmo participante na sessao', function () {
    $sessao = Sessao::factory()->create();

    Presenca::factory()->create(['sessao_id' => $sessao->id, 'status' => Presenca::STATUS_VALIDA]);

    expect(fn () => Presenca::factory()->create(['sessao_id' => $sessao->id, 'status' => Presenca::STATUS_VALIDA]))
        ->toThrow(QueryException::class);
});

it('permite nova presenca depois do estorno', function () {
    $sessao = Sessao::factory()->create();
    $primeira = Presenca::factory()->create(['sessao_id' => $sessao->id, 'status' => Presenca::STATUS_VALIDA]);

    $primeira->update(['status' => Presenca::STATUS_ESTORNADA, 'motivo_estorno' => 'registro em duplicidade']);

    Presenca::factory()->create([
        'sessao_id' => $sessao->id,
        'participante_id' => $primeira->participante_id,
        'status' => Presenca::STATUS_VALIDA,
    ]);

    expect(Presenca::query()->where('sessao_id', $sessao->id)->count())->toBe(2);
});

it('nao permite duas sessoes no mesmo horario e permite depois de cancelar', function () {
    $sessao = Sessao::factory()->create();

    expect(fn () => Sessao::factory()->create([
        'ebi_id' => $sessao->ebi_id,
        'inicio_previsto' => $sessao->inicio_previsto,
        'status' => Sessao::STATUS_PREVISTA,
    ]))->toThrow(QueryException::class);

    $sessao->update(['status' => Sessao::STATUS_CANCELADA]);

    expect(Sessao::factory()->create([
        'ebi_id' => $sessao->ebi_id,
        'inicio_previsto' => $sessao->inicio_previsto,
        'status' => Sessao::STATUS_PREVISTA,
    ])->exists)->toBeTrue();
});

it('limita uma excecao de horario por faixa e dia', function () {
    $faixa = AgendaEbi::factory()->create();

    AgendaExcecao::factory()->create([
        'ebi_id' => $faixa->ebi_id,
        'agenda_ebi_id' => $faixa->id,
        'data' => '2026-12-24',
        'tipo' => AgendaExcecao::TIPO_HORARIO_ESPECIAL,
        'hora_inicio' => '09:00',
        'hora_fim' => '10:30',
    ]);

    expect(fn () => AgendaExcecao::factory()->create([
        'ebi_id' => $faixa->ebi_id,
        'agenda_ebi_id' => $faixa->id,
        'data' => '2026-12-24',
        'tipo' => AgendaExcecao::TIPO_HORARIO_ESPECIAL,
        'hora_inicio' => '11:00',
        'hora_fim' => '12:00',
    ]))->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/EsquemaTest.php`
Expected: FAIL — classes/tabelas inexistentes.

- [ ] **Step 3: Criar as migrations**

`create_agenda_tables`:
```php
Schema::create('agenda_ebi', function (Blueprint $table): void {
    $table->ulid('id')->primary();
    $table->foreignUlid('ebi_id')->constrained('ebis');
    $table->unsignedTinyInteger('dia_semana');
    $table->string('hora_inicio', 5);
    $table->string('hora_fim', 5);
    $table->date('inicio_vigencia');
    $table->date('fim_vigencia')->nullable();
    $table->timestamps();
    $table->index(['ebi_id', 'dia_semana']);
});

Schema::create('agenda_excecao', function (Blueprint $table): void {
    $table->ulid('id')->primary();
    $table->foreignUlid('ebi_id')->constrained('ebis');
    $table->foreignUlid('agenda_ebi_id')->nullable()->constrained('agenda_ebi');
    $table->date('data');
    $table->string('tipo');
    $table->string('hora_inicio', 5)->nullable();
    $table->string('hora_fim', 5)->nullable();
    $table->text('motivo');
    $table->timestamps();
    $table->index(['ebi_id', 'data']);
});

DB::statement("CREATE UNIQUE INDEX agenda_excecao_dia_unico ON agenda_excecao (ebi_id, data, tipo) WHERE agenda_ebi_id IS NULL");
DB::statement("CREATE UNIQUE INDEX agenda_excecao_faixa_unico ON agenda_excecao (agenda_ebi_id, data) WHERE tipo = 'horario_especial'");
```

`create_sessao_presenca_tables`: `sessao`, `sessao_correcao` e `presenca` conforme o design §6.2, com `timestampTz` para `inicio_previsto`, `fim_previsto`, `inicio_real`, `fim_real` e `registrado_em`/`estornado_em`. As três restrições parciais vão em `DB::statement`:

```php
DB::statement("CREATE UNIQUE INDEX sessao_ebi_inicio_unico ON sessao (ebi_id, inicio_previsto) WHERE status <> 'cancelada'");
DB::statement("CREATE UNIQUE INDEX presenca_valida_unica ON presenca (sessao_id, participante_id) WHERE status = 'valida'");
```

`down()`: `Schema::dropIfExists` na ordem inversa (os índices caem junto com a tabela).

- [ ] **Step 4: Criar models e factories**

Models com `HasFactory`, `HasUlids`, `$table`, `$fillable` e `$casts` de datas (`date`, `datetime`). Em `Sessao`:

```php
public const STATUS_PREVISTA = 'prevista';
public const STATUS_ABERTA = 'aberta';
public const STATUS_ENCERRADA = 'encerrada';
public const STATUS_CANCELADA = 'cancelada';

public function presencas(): HasMany { return $this->hasMany(Presenca::class); }
public function presencasValidas(): HasMany { return $this->presencas()->where('status', Presenca::STATUS_VALIDA); }
```

Factories:
- `AgendaEbiFactory`: `ebi_id => Ebi::factory()`, `dia_semana => 1`, `hora_inicio => '10:00'`, `hora_fim => '11:30'`, `inicio_vigencia => now()->subYear()->toDateString()`, `fim_vigencia => null`.
- `AgendaExcecaoFactory`: `ebi_id => Ebi::factory()`, `tipo => feriado`, `data => now()->addWeek()->toDateString()`, `motivo => 'feriado local'`.
- `SessaoFactory`: `ebi_id => Ebi::factory()`, `igreja_id => Igreja::factory()`, `inicio_previsto => now()->addDay()->setTime(10, 0)`, `fim_previsto => now()->addDay()->setTime(11, 30)`, `status => prevista`, `realizada => null`.
- `PresencaFactory`: `sessao_id => Sessao::factory()`, `participante_id => Participante::factory()`, `registrado_por => Usuario::factory()`, `registrado_em => now()`, `origem => web`, `status => valida`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/EsquemaTest.php`
Expected: PASS (4 testes).

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_11_17_100001_create_agenda_tables.php backend/database/migrations/2026_11_17_100002_create_sessao_presenca_tables.php backend/app/Domain/Agenda backend/app/Domain/Sessoes backend/tests/Feature/Agenda/EsquemaTest.php
git commit -m "feat(agenda,sessoes): tabelas, models e factories da E04"
```

---

### Task 7: Agenda — faixas semanais (serviço + endpoints)

**Files:**
- Create: `backend/app/Domain/Agenda/Contracts/FaixaAgenda.php`
- Create: `backend/app/Domain/Agenda/Contracts/GerirAgenda.php`
- Create: `backend/app/Domain/Agenda/Services/GerenciadorAgenda.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Create: `backend/app/Http/Requests/Agenda/AgendaFaixaRequest.php`
- Create: `backend/app/Http/Resources/AgendaFaixaResource.php`
- Create: `backend/app/Http/Controllers/Api/V1/AgendaController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Agenda/AgendaTest.php`

**Interfaces:**
- Consumes: models `AgendaEbi` (Task 6), `Operacoes::AGENDA_GERENCIAR` / `SESSAO_LER` (Task 3).
- Produces:
  - `FaixaAgenda` (readonly): `string $id, string $ebiId, int $diaSemana, string $horaInicio, string $horaFim, string $inicioVigencia, ?string $fimVigencia, bool $vigente`.
  - `GerirAgenda::faixas(string $ebiId): array<int, FaixaAgenda>`, `faixa(string $faixaId): ?FaixaAgenda`, `criarFaixa(string $ebiId, array $dados): FaixaAgenda`, `atualizarFaixa(string $faixaId, array $dados): FaixaAgenda`, `encerrarFaixa(string $faixaId): void`.
  - Erros `ProblemException`: `agenda_sobreposta` (422), `agenda_vigente` (422), `not_found` (404).

- [ ] **Step 1: Escrever o teste**

```php
<?php

use App\Domain\Agenda\Models\AgendaEbi;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
});

it('cria faixa e recusa sobreposicao no mesmo dia', function () {
    $dados = [
        'dia_semana' => 1,
        'hora_inicio' => '10:00',
        'hora_fim' => '11:30',
        'inicio_vigencia' => '2026-11-02',
    ];

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda", $dados, $this->auth)
        ->assertCreated()
        ->assertJsonPath('dia_semana', 1);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda", [...$dados, 'hora_inicio' => '11:00'], $this->auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'agenda_sobreposta');

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda", [...$dados, 'dia_semana' => 2], $this->auth)
        ->assertCreated();
});

it('edita faixa futura e recusa faixa vigente', function () {
    $futura = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'inicio_vigencia' => '2027-01-04',
        'fim_vigencia' => null,
    ]);

    $this->patchJson("/api/v1/agenda/{$futura->id}", ['hora_fim' => '12:00'], $this->auth)
        ->assertOk()
        ->assertJsonPath('hora_fim', '12:00');

    $vigente = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'dia_semana' => 3,
        'inicio_vigencia' => now()->subWeek()->toDateString(),
    ]);

    $this->patchJson("/api/v1/agenda/{$vigente->id}", ['hora_fim' => '12:00'], $this->auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'agenda_vigente');
});

it('delete encerra a vigencia e nao apaga a faixa', function () {
    $faixa = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'inicio_vigencia' => now()->subMonth()->toDateString(),
    ]);

    $this->deleteJson("/api/v1/agenda/{$faixa->id}", [], $this->auth)->assertNoContent();

    $faixa->refresh();
    expect($faixa->fim_vigencia?->toDateString())->toBe(now()->subDay()->toDateString());
});

it('auxiliar nao altera a agenda', function () {
    $aux = criarUsuarioComPapel('auxiliar', 'ebi', $this->ebi->id);
    $auth = [
        'Authorization' => 'Bearer '.$aux->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=auxiliar;escopo=ebi:{$this->ebi->id}",
    ];

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda", [
        'dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fim' => '11:30', 'inicio_vigencia' => '2026-11-02',
    ], $auth)->assertForbidden();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/AgendaTest.php`
Expected: FAIL (404 — rotas e serviço não existem).

- [ ] **Step 3: Implementar o serviço**

`GerenciadorAgenda implements GerirAgenda` com regras:
1. Valida `hora_fim > hora_inicio` e `fim_vigencia >= inicio_vigencia` (senão `422 agenda_invalida`).
2. Sobreposição: mesma EBI, mesmo `dia_semana`, vigências que se cruzam (`a.inicio <= b.fim OR b.fim IS NULL` dos dois lados) e horários que se cruzam (`hora_inicio < outra.hora_fim` e `hora_fim > outra.hora_inicio`). Ignora a própria faixa no update.
3. `atualizarFaixa`: 404 se não existir; se `inicio_vigencia <= now()->toDateString()` → `422 agenda_vigente`.
4. `encerrarFaixa`: se `inicio_vigencia > hoje` **e** não houver `sessao.agenda_ebi_id` apontando para ela → `delete()`; caso contrário `fim_vigencia = hoje->subDay()`.
5. `vigente` no DTO = `inicio_vigencia <= hoje && (fim_vigencia null || fim_vigencia >= hoje)`.

- [ ] **Step 4: Implementar controller e rotas**

`AgendaController::index/store/update/destroy` usando `GerirAgenda` e `AgendaFaixaResource`. `AgendaFaixaRequest` valida:

```php
'dia_semana' => ['required', 'integer', 'between:1,7'],
'hora_inicio' => ['required', 'date_format:H:i'],
'hora_fim' => ['required', 'date_format:H:i', 'after:hora_inicio'],
'inicio_vigencia' => ['required', 'date', 'date_format:Y-m-d'],
'fim_vigencia' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:inicio_vigencia'],
```

No `update`, use `Rule::requiredIf($this->isMethod('post'))` em `dia_semana`, `hora_inicio`, `hora_fim` e `inicio_vigencia` (e valide o conjunto mesclado no serviço).

Rotas:

```php
Route::get('ebis/{id}/agenda', [AgendaController::class, 'index'])
    ->middleware('can:sessao.ler,ebi,{id}')->name('agenda.index');
Route::post('ebis/{id}/agenda', [AgendaController::class, 'store'])
    ->middleware(['can:agenda.gerenciar,ebi,{id}', IdempotenciaMiddleware::class])->name('agenda.store');
Route::patch('agenda/{id}', [AgendaController::class, 'update'])->name('agenda.update');
Route::delete('agenda/{id}', [AgendaController::class, 'destroy'])->name('agenda.destroy');
```

`update`/`destroy` resolvem a faixa (`GerirAgenda::faixa`) e chamam `Gate::authorize(Operacoes::AGENDA_GERENCIAR, ['ebi', $faixa->ebiId])`; 404 via `ProblemException('not_found', 'Recurso não encontrado', 404)`.

Bind em `DomainServiceProvider`: `GerirAgenda::class => GerenciadorAgenda::class`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/AgendaTest.php`
Expected: PASS (4 testes).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Agenda backend/app/Providers/DomainServiceProvider.php backend/app/Http/Requests/Agenda/AgendaFaixaRequest.php backend/app/Http/Resources/AgendaFaixaResource.php backend/app/Http/Controllers/Api/V1/AgendaController.php backend/routes/api.php backend/tests/Feature/Agenda/AgendaTest.php
git commit -m "feat(agenda): faixas semanais do EBI com vigencia (RF-06)"
```

---

### Task 8: Exceções da agenda e cancelamento das sessões previstas

**Files:**
- Create: `backend/app/Domain/Agenda/Contracts/ExcecaoAgenda.php`
- Create: `backend/app/Domain/Agenda/Contracts/ConsultarAgenda.php`
- Create: `backend/app/Domain/Agenda/Contracts/OcorrenciaAgenda.php`
- Create: `backend/app/Domain/Agenda/Services/ConsultaAgenda.php`
- Modify: `backend/app/Domain/Agenda/Contracts/GerirAgenda.php`
- Modify: `backend/app/Domain/Agenda/Services/GerenciadorAgenda.php`
- Create: `backend/app/Domain/Sessoes/Contracts/CancelarSessoesPrevistas.php`
- Create: `backend/app/Domain/Sessoes/Services/CanceladorSessoesPrevistas.php`
- Create: `backend/app/Http/Requests/Agenda/AgendaExcecaoRequest.php`
- Create: `backend/app/Http/Resources/AgendaExcecaoResource.php`
- Create: `backend/app/Http/Controllers/Api/V1/AgendaExcecaoController.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Agenda/ExcecaoAgendaTest.php`

**Interfaces:**
- Consumes: `AgendaEbi`, `AgendaExcecao` (Task 6), `Sessao` (Task 6), `RegistrarEvento`, `Gate`.
- Produces:
  - `ExcecaoAgenda` (readonly): `string $id, string $ebiId, ?string $agendaEbiId, string $data, string $tipo, ?string $horaInicio, ?string $horaFim, string $motivo`.
  - `OcorrenciaAgenda` (readonly): `?string $agendaEbiId, string $ebiId, string $data, string $horaInicio, string $horaFim`.
  - `ConsultarAgenda::ocorrenciasDoDia(string $ebiId, string $dataIso): array<int, OcorrenciaAgenda>` — vazio em feriado/cancelamento.
  - `GerirAgenda::excecoes(string $ebiId, ?string $de = null, ?string $ate = null): array<int, ExcecaoAgenda>`, `excecao(string $id): ?ExcecaoAgenda`, `criarExcecao(string $ebiId, array $dados): ExcecaoAgenda`, `removerExcecao(string $excecaoId): void`.
  - `CancelarSessoesPrevistas::naData(string $ebiId, string $dataIso, string $motivo, string $autorId): int`.

- [ ] **Step 1: Escrever o teste**

```php
<?php

use App\Domain\Agenda\Contracts\ConsultarAgenda;
use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
});

it('feriado criado depois cancela apenas as sessoes previstas do dia', function () {
    $prevista = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-12-25 13:00:00',
        'fim_previsto' => '2026-12-25 14:30:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
    $aberta = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-12-25 15:00:00',
        'fim_previsto' => '2026-12-25 16:30:00',
        'status' => Sessao::STATUS_ABERTA,
    ]);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
        'tipo' => 'feriado',
        'data' => '2026-12-25',
        'motivo' => 'Natal',
    ], $this->auth)->assertCreated();

    expect($prevista->refresh()->status)->toBe(Sessao::STATUS_CANCELADA)
        ->and($prevista->motivo_cancelamento)->toBe('agenda: Natal')
        ->and($aberta->refresh()->status)->toBe(Sessao::STATUS_ABERTA)
        ->and(EventoAuditoria::query()->where('acao', 'sessao.cancelada')->where('motivo', 'agenda: Natal')->exists())->toBeTrue();
});

it('horario especial substitui a faixa naquele dia', function () {
    $faixa = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'dia_semana' => 4,
        'hora_inicio' => '10:00',
        'hora_fim' => '11:30',
        'inicio_vigencia' => '2026-01-01',
    ]);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
        'tipo' => 'horario_especial',
        'data' => '2026-12-24',
        'agenda_ebi_id' => $faixa->id,
        'hora_inicio' => '09:00',
        'hora_fim' => '10:30',
        'motivo' => 'véspera de Natal',
    ], $this->auth)->assertCreated();

    $ocorrencias = app(ConsultarAgenda::class)->ocorrenciasDoDia($this->ebi->id, '2026-12-24');

    expect($ocorrencias)->toHaveCount(1)
        ->and($ocorrencias[0]->horaInicio)->toBe('09:00')
        ->and($ocorrencias[0]->horaFim)->toBe('10:30');
});

it('feriado zera as ocorrencias do dia', function () {
    AgendaEbi::factory()->create(['ebi_id' => $this->ebi->id, 'dia_semana' => 5, 'inicio_vigencia' => '2026-01-01']);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
        'tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'Natal',
    ], $this->auth)->assertCreated();

    expect(app(ConsultarAgenda::class)->ocorrenciasDoDia($this->ebi->id, '2026-12-25'))->toBe([]);
});

it('recusa excecao repetida', function () {
    $dados = ['tipo' => 'feriado', 'data' => '2026-12-25', 'motivo' => 'Natal'];

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", $dados, $this->auth)->assertCreated();
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", $dados, $this->auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'agenda_excecao_duplicada');
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/ExcecaoAgendaTest.php`
Expected: FAIL (rotas/serviços ausentes).

- [ ] **Step 3: Implementar `ConsultarAgenda`**

```php
public function ocorrenciasDoDia(string $ebiId, string $dataIso): array
{
    $dia = CarbonImmutable::parse($dataIso)->dayOfWeekIso;

    $bloqueada = AgendaExcecao::query()
        ->where('ebi_id', $ebiId)
        ->whereDate('data', $dataIso)
        ->whereIn('tipo', [AgendaExcecao::TIPO_FERIADO, AgendaExcecao::TIPO_CANCELAMENTO])
        ->exists();

    if ($bloqueada) {
        return [];
    }

    $faixas = AgendaEbi::query()
        ->where('ebi_id', $ebiId)
        ->where('dia_semana', $dia)
        ->whereDate('inicio_vigencia', '<=', $dataIso)
        ->where(fn ($q) => $q->whereNull('fim_vigencia')->orWhereDate('fim_vigencia', '>=', $dataIso))
        ->orderBy('hora_inicio')
        ->get();

    $especiais = AgendaExcecao::query()
        ->where('ebi_id', $ebiId)
        ->whereDate('data', $dataIso)
        ->where('tipo', AgendaExcecao::TIPO_HORARIO_ESPECIAL)
        ->get()
        ->keyBy('agenda_ebi_id');

    return $faixas->map(function (AgendaEbi $faixa) use ($especiais, $ebiId, $dataIso): OcorrenciaAgenda {
        $especial = $especiais->get($faixa->id);

        return new OcorrenciaAgenda(
            agendaEbiId: (string) $faixa->id,
            ebiId: $ebiId,
            data: $dataIso,
            horaInicio: $especial?->hora_inicio ?? $faixa->hora_inicio,
            horaFim: $especial?->hora_fim ?? $faixa->hora_fim,
        );
    })->values()->all();
}
```

- [ ] **Step 4: Implementar exceções e cancelamento**

- `GerenciadorAgenda::criarExcecao` valida por tipo: feriado/cancelamento exigem `agenda_ebi_id` nulo e horários nulos; `horario_especial` exige `agenda_ebi_id`, `hora_inicio` e `hora_fim` (com `fim > inicio`). Colisão de índice → `422 agenda_excecao_duplicada` (checar antes com `exists()` por `(ebi_id,data,tipo)` ou `(agenda_ebi_id,data)`).
- `removerExcecao`: 404 se não existir; `delete()`.
- `CanceladorSessoesPrevistas` percorre as sessões `prevista` do EBI cuja data local (fuso da igreja) é `$dataIso`, atualiza `status = cancelada`, `motivo_cancelamento = "agenda: {$motivo}"`, `cancelada_por = $autorId` e audita cada uma (`acao = 'sessao.cancelada'`, `recurso = 'sessao'`, `motivo = "agenda: {$motivo}"`, `escopo = "ebi:{$ebiId}"`, metadados `sessao_id`). A comparação de data local é feita em PHP sobre `inicio_previsto` (a sessão sempre tem `igreja_id`; usar `ConsultarEbi::dados($ebiId)->timezone`).
- `AgendaExcecaoController::store` chama `criarExcecao` e, para `feriado`/`cancelamento`, `CancelarSessoesPrevistas::naData(...)`. `destroy` resolve a exceção, autoriza `Gate::authorize(Operacoes::AGENDA_GERENCIAR, ['ebi', $excecao->ebiId])` e remove.
- Rotas:

```php
Route::get('ebis/{id}/agenda-exceptions', [AgendaExcecaoController::class, 'index'])
    ->middleware('can:sessao.ler,ebi,{id}')->name('agenda-exceptions.index');
Route::post('ebis/{id}/agenda-exceptions', [AgendaExcecaoController::class, 'store'])
    ->middleware(['can:agenda.gerenciar,ebi,{id}', IdempotenciaMiddleware::class])->name('agenda-exceptions.store');
Route::delete('agenda-exceptions/{id}', [AgendaExcecaoController::class, 'destroy'])->name('agenda-exceptions.destroy');
```

Binds: `ConsultarAgenda::class => ConsultaAgenda::class` e `CancelarSessoesPrevistas::class => CanceladorSessoesPrevistas::class`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/ExcecaoAgendaTest.php`
Expected: PASS (4 testes).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Agenda backend/app/Domain/Sessoes/Contracts/CancelarSessoesPrevistas.php backend/app/Domain/Sessoes/Services/CanceladorSessoesPrevistas.php backend/app/Http/Requests/Agenda/AgendaExcecaoRequest.php backend/app/Http/Resources/AgendaExcecaoResource.php backend/app/Http/Controllers/Api/V1/AgendaExcecaoController.php backend/app/Providers/DomainServiceProvider.php backend/routes/api.php backend/tests/Feature/Agenda/ExcecaoAgendaTest.php
git commit -m "feat(agenda): excecoes por dia/faixa e cancelamento das previstas (RF-06)"
```

---

### Task 9: Geração idempotente de sessões previstas

**Files:**
- Create: `backend/app/Domain/Sessoes/Contracts/ResultadoGeracao.php`
- Create: `backend/app/Domain/Sessoes/Contracts/GerarSessoesPrevistas.php`
- Create: `backend/app/Domain/Sessoes/Services/GeradorSessoesPrevistas.php`
- Create: `backend/app/Console/Commands/GerarSessoes.php`
- Modify: `backend/routes/console.php`
- Create: `backend/app/Http/Requests/Agenda/GerarSessoesRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/SessaoController.php` (criar; `index` e `show` ficam na Task 10)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Agenda/GeracaoTest.php`

**Interfaces:**
- Consumes: `ConsultarAgenda::ocorrenciasDoDia` (Task 8), `ConsultarEbi::dados/todasAtivas` (Task 4), `RegistrarEvento`.
- Produces:
  - `ResultadoGeracao` (readonly): `int $criadas, int $ignoradas, string $de, string $ate`.
  - `GerarSessoesPrevistas::gerar(string $ebiId, string $de, string $ate): ResultadoGeracao` (máx. 84 dias).
  - Comando `sessoes:gerar {--dias=28}`.

- [ ] **Step 1: Escrever o teste**

```php
<?php

use App\Domain\Agenda\Models\AgendaEbi;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->auth = function (string $papel = 'coordenadora', ?string $ebiId = null) {
        $u = criarUsuarioComPapel($papel, 'ebi', $ebiId ?? $this->ebi->id);

        return [
            'Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken,
            'X-Acting-Scope' => "papel={$papel};escopo=ebi:".($ebiId ?? $this->ebi->id),
        ];
    };
});

it('gera no fuso da igreja, nao duplica e nao ressuscita cancelada', function () {
    $this->igreja->update(['timezone' => 'America/Manaus']);
    AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'dia_semana' => 1,
        'hora_inicio' => '19:30',
        'hora_fim' => '21:00',
        'inicio_vigencia' => '2026-01-01',
    ]);

    $auth = ($this->auth)();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 1);

    $sessao = Sessao::query()->firstOrFail();
    expect($sessao->inicio_previsto->utc()->format('Y-m-d H:i'))->toBe('2026-11-16 23:30');

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 0)->assertJsonPath('ignoradas', 1);

    $sessao->update(['status' => Sessao::STATUS_CANCELADA, 'motivo_cancelamento' => 'sem equipe']);

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 0);

    expect($sessao->refresh()->status)->toBe(Sessao::STATUS_CANCELADA);
});

it('respeita feriado e horario especial', function () {
    $this->igreja->update(['timezone' => 'America/Sao_Paulo']);
    $faixa = AgendaEbi::factory()->create([
        'ebi_id' => $this->ebi->id,
        'dia_semana' => 4,
        'inicio_vigencia' => '2026-01-01',
    ]);

    $auth = ($this->auth)();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
        'tipo' => 'horario_especial', 'data' => '2026-12-24', 'agenda_ebi_id' => $faixa->id,
        'hora_inicio' => '09:00', 'hora_fim' => '10:30', 'motivo' => 'véspera',
    ], $auth)->assertCreated();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-12-24', 'ate' => '2026-12-24',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 1);

    expect(Sessao::query()->firstOrFail()->inicio_previsto->setTimezone('America/Sao_Paulo')->format('H:i'))->toBe('09:00');

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/agenda-exceptions", [
        'tipo' => 'feriado', 'data' => '2026-12-31', 'motivo' => 'recesso',
    ], $auth)->assertCreated();

    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-12-31', 'ate' => '2026-12-31',
    ], $auth)->assertCreated()->assertJsonPath('criadas', 0);
});

it('recusa intervalo maior que doze semanas', function () {
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-01-01', 'ate' => '2026-06-01',
    ], ($this->auth)())->assertStatus(422)->assertJsonPath('code', 'intervalo_invalido');
});

it('o comando gera para todas as EBIs ativas', function () {
    $outra = igrejaComEbi()['ebi'];

    foreach ([$this->ebi, $outra] as $ebi) {
        AgendaEbi::factory()->create([
            'ebi_id' => $ebi->id,
            'dia_semana' => now()->dayOfWeekIso,
            'inicio_vigencia' => now()->subYear()->toDateString(),
        ]);
    }

    $this->artisan('sessoes:gerar', ['--dias' => 7])->assertSuccessful();

    expect(Sessao::query()->count())->toBeGreaterThanOrEqual(2);
});

it('auxiliar nao gera sessoes', function () {
    $this->postJson("/api/v1/ebis/{$this->ebi->id}/sessions/generate", [
        'de' => '2026-11-16', 'ate' => '2026-11-22',
    ], ($this->auth)('auxiliar'))->assertForbidden();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/GeracaoTest.php`
Expected: FAIL (rota/comando ausentes).

- [ ] **Step 3: Implementar `GeradorSessoesPrevistas`**

- Valida intervalo: `de <= ate` e `ate <= de + 84 dias`; senão `ProblemException('intervalo_invalido', 'Dados inválidos', 422, ...)`.
- Para cada data (`CarbonImmutable`), chama `ocorrenciasDoDia`; para cada ocorrência:
  1. `$inicio = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data} {$horaInicio}", $timezone)->utc();` (idem `fim`).
  2. Pula se já existir `Sessao` do EBI com o mesmo `inicio_previsto` (inclusive cancelada).
  3. Pula se houver intervalo sobreposto (`inicio_previsto < $fim` e `fim_previsto > $inicio`) em sessão com `status <> cancelada`.
  4. Cria `Sessao` com `status = prevista`, `igreja_id` do `EbiDados`, `agenda_ebi_id`.
- Ao final audita `sessao.gerada` com `motivo = null`, `escopo = "ebi:{$ebiId}"`, metadados `{criadas, ignoradas, de, ate}`.
- Retorna `ResultadoGeracao`.

- [ ] **Step 4: Comando, request, rota e controller**

`GerarSessoes` (`$signature = 'sessoes:gerar {--dias=28 : Janela a partir de hoje}'`) percorre `ConsultarEbi::todasAtivas()`, gera de hoje a hoje+dias e imprime `EBI <nome>: criadas X, ignoradas Y`.

`routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sessoes:gerar')->dailyAt('03:00');
```

`GerarSessoesRequest`:
```php
'de' => ['nullable', 'date', 'date_format:Y-m-d'],
'ate' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:de'],
'semanas' => ['nullable', 'integer', 'between:1,12'],
```
No controller: `de ?? today`, `ate ?? de + semanas(4) semanas`; chama o serviço e devolve 201 com o DTO.

Rota (Idempotency + gate de agenda):

```php
Route::post('ebis/{id}/sessions/generate', [SessaoController::class, 'gerar'])
    ->middleware(['can:agenda.gerenciar,ebi,{id}', IdempotenciaMiddleware::class])
    ->name('sessions.generate');
```

Bind: `GerarSessoesPrevistas::class => GeradorSessoesPrevistas::class`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/GeracaoTest.php`
Expected: PASS (5 testes).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Sessoes/Contracts/ResultadoGeracao.php backend/app/Domain/Sessoes/Contracts/GerarSessoesPrevistas.php backend/app/Domain/Sessoes/Services/GeradorSessoesPrevistas.php backend/app/Console/Commands/GerarSessoes.php backend/routes/console.php backend/app/Http/Requests/Agenda/GerarSessoesRequest.php backend/app/Http/Controllers/Api/V1/SessaoController.php backend/app/Providers/DomainServiceProvider.php backend/routes/api.php backend/tests/Feature/Agenda/GeracaoTest.php
git commit -m "feat(sessoes): geracao idempotente de sessoes previstas (RF-06, CA-23)"
```

---

### Task 10: Ciclo da sessão, detalhe e equipe mínima

**Files:**
- Create: `backend/app/Domain/Sessoes/Contracts/SessaoEbi.php`
- Create: `backend/app/Domain/Sessoes/Contracts/ConsultarSessao.php`
- Create: `backend/app/Domain/Sessoes/Contracts/GerirSessaoEbi.php`
- Create: `backend/app/Domain/Sessoes/Services/ConsultaSessao.php`
- Create: `backend/app/Domain/Sessoes/Services/GerenciadorSessao.php`
- Create: `backend/app/Domain/Sessoes/Services/CalculadoraEquipeMinima.php`
- Create: `backend/app/Http/Requests/Agenda/EncerrarSessaoRequest.php`
- Create: `backend/app/Http/Requests/Agenda/CancelarSessaoRequest.php`
- Create: `backend/app/Http/Requests/Agenda/CorrigirSessaoRequest.php`
- Create: `backend/app/Http/Resources/SessaoResource.php`
- Modify: `backend/app/Http/Controllers/Api/V1/SessaoController.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Agenda/SessaoTest.php`

**Interfaces:**
- Consumes: `Sessao`/`SessaoCorrecao` (Task 6), `ConsultarEbi` (Task 4), `RegistrarEvento`, `Gate`.
- Produces:
  - `SessaoEbi` (readonly): `string $id, string $ebiId, string $ebiNome, string $igrejaId, ?string $agendaEbiId, string $inicioPrevisto, string $fimPrevisto, ?string $inicioReal, ?string $fimReal, ?string $tema, ?string $passagemBiblica, string $status, ?bool $realizada, ?string $motivoCancelamento, ?int $auxiliaresEscalados, int $presentes`. Todos os instantes em ISO 8601 UTC.
  - `ConsultarSessao::listar(string $ebiId, string $de, string $ate, ?string $status = null): array<int, SessaoEbi>` e `detalhe(string $sessaoId): ?SessaoEbi`.
  - `GerirSessaoEbi::abrir/encerrar/cancelar/corrigir(...)` devolvendo `SessaoEbi`.
  - `CalculadoraEquipeMinima::calcular(int $presentes, int $porAuxiliar, ?int $informados): array{presentes:int, participantes_por_auxiliar:int, auxiliares_necessarios:int, auxiliares_informados:?int, alerta:bool}`.

- [ ] **Step 1: Escrever o teste**

```php
<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;
use App\Domain\Sessoes\Models\SessaoCorrecao;
use App\Domain\Sessoes\Services\CalculadoraEquipeMinima;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-18 13:00:00',
        'fim_previsto' => '2026-11-18 14:30:00',
        'status' => Sessao::STATUS_PREVISTA,
    ]);
});

it('abre, encerra e recusa transicoes invalidas', function () {
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/open", [], $this->auth)
        ->assertOk()->assertJsonPath('status', 'aberta');

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/open", [], $this->auth)
        ->assertStatus(409)->assertJsonPath('code', 'sessao_em_estado_invalido');

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/close", [], $this->auth)
        ->assertStatus(422);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/close", ['realizada' => false], $this->auth)
        ->assertOk()->assertJsonPath('status', 'encerrada')->assertJsonPath('realizada', false);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/cancel", ['motivo' => 'sem equipe'], $this->auth)
        ->assertStatus(409);
});

it('cancela prevista com motivo e audita', function () {
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/cancel", ['motivo' => 'sem equipe'], $this->auth)
        ->assertOk()->assertJsonPath('status', 'cancelada');

    expect($this->sessao->refresh()->motivo_cancelamento)->toBe('sem equipe')
        ->and(EventoAuditoria::query()->where('acao', 'sessao.cancelada')->exists())->toBeTrue();
});

it('corrigir encerrada exige motivo e guarda antes/depois', function () {
    $this->sessao->update(['status' => Sessao::STATUS_ENCERRADA, 'realizada' => true, 'fim_real' => now()]);

    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", ['tema' => 'Novo tema'], $this->auth)
        ->assertStatus(422)->assertJsonPath('code', 'motivo_obrigatorio');

    $this->patchJson("/api/v1/sessions/{$this->sessao->id}", [
        'tema' => 'Novo tema', 'motivo' => 'erro de digitacao',
    ], $this->auth)->assertOk()->assertJsonPath('tema', 'Novo tema');

    $correcao = SessaoCorrecao::query()->firstOrFail();
    expect($correcao->antes['tema'])->not->toBe('Novo tema')
        ->and($correcao->depois['tema'])->toBe('Novo tema')
        ->and(EventoAuditoria::query()->where('acao', 'sessao.corrigida')->exists())->toBeTrue();
});

it('detalhe traz a equipe minima sem bloquear', function () {
    $this->sessao->update([
        'status' => Sessao::STATUS_ABERTA,
        'auxiliares_escalados' => 2,
    ]);
    Presenca::factory()->count(9)->create([
        'sessao_id' => $this->sessao->id,
        'status' => Presenca::STATUS_VALIDA,
    ]);

    $this->getJson("/api/v1/sessions/{$this->sessao->id}", $this->auth)
        ->assertOk()
        ->assertJsonPath('equipe_minima.presentes', 9)
        ->assertJsonPath('equipe_minima.auxiliares_necessarios', 3)
        ->assertJsonPath('equipe_minima.auxiliares_informados', 2)
        ->assertJsonPath('equipe_minima.alerta', true);
});

it('calcula equipe minima quando nao ha valor informado', function () {
    $calc = new CalculadoraEquipeMinima;

    expect($calc->calcular(9, 4, null))->toMatchArray(['auxiliares_necessarios' => 3, 'alerta' => false])
        ->and($calc->calcular(0, 4, 1))->toMatchArray(['auxiliares_necessarios' => 0, 'alerta' => false])
        ->and($calc->calcular(1, 4, 0))->toMatchArray(['auxiliares_necessarios' => 1, 'alerta' => true]);
});

it('coordenadora de outra EBI nao abre a sessao', function () {
    $outra = igrejaComEbi()['ebi'];
    $intrusa = criarUsuarioComPapel('coordenadora', 'ebi', $outra->id);
    $auth = [
        'Authorization' => 'Bearer '.$intrusa->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra->id}",
    ];

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/open", [], $auth)->assertForbidden();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/SessaoTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar serviços**

- `CalculadoraEquipeMinima`: `$necessarios = $porAuxiliar > 0 ? (int) ceil($presentes / $porAuxiliar) : 0;`; `alerta = $informados !== null && $presentes > 0 && $informados < $necessarios`.
- `GerenciadorSessao`:
  - `abrir`: exige `prevista`; grava `inicio_real = now()`; audita `sessao.aberta` (`motivo = null`, `escopo = "ebi:{$ebiId}"`).
  - `encerrar`: exige `aberta`; `fim_real = $fimRealIso ? CarbonImmutable::parse(...) : now()`; valida `fim_real >= inicio_real`; grava `realizada`; audita `sessao.encerrada` com metadados `{realizada}`.
  - `cancelar`: exige `prevista` e motivo 3..500; grava `status`, `motivo_cancelamento`, `cancelada_por`; audita `sessao.cancelada` com `motivo`.
  - `corrigir`: `cancelada` → 409; `encerrada` sem `motivo` → `422 motivo_obrigatorio`; monta `antes`/`depois` com os campos alterados; grava `SessaoCorrecao` (autor, antes, depois, ocorrido_em) e audita `sessao.corrigida` com `motivo`. Campos permitidos: `tema`, `passagem_biblica`, `inicio_real`, `fim_real`, `realizada`, `auxiliares_escalados`.
  - Transições inválidas → `ProblemException('sessao_em_estado_invalido', 'Conflito de estado', 409, ...)`.
- `ConsultaSessao`: `listar` valida `de/ate` (máx. 92 dias), carrega `ebi` (nome via `ConsultarEbi`) e `withCount('presencasValidas as presentes')`; ordena por `inicio_previsto`. `detalhe` idem por id.

- [ ] **Step 4: Controller, requests, resource e rotas**

- `SessaoController::index/show/gerar/open/close/cancel/update`.
- `index` valida `de` e `ate` (obrigatórios, `date_format:Y-m-d`, intervalo máximo de 92 dias) e aceita `status` opcional em `prevista|aberta|encerrada|cancelada`, repassando para `ConsultarSessao::listar`.
- Autorização: `index` usa `can:sessao.ler,ebi,{id}`; nas rotas com `{id}` de sessão o controller resolve `detalhe()` (404), depois `Gate::authorize(Operacoes::SESSAO_LER|SESSAO_GERENCIAR, ['ebi', $sessao->ebiId])`. `open/close/cancel/update` usam `SESSAO_GERENCIAR` e `IdempotenciaMiddleware`.
- `SessaoResource`: mapeia `SessaoEbi`; aceita `?array $equipeMinima = null` no construtor e, quando presente, inclui `equipe_minima` no payload. `show` passa o resultado de `CalculadoraEquipeMinima::calcular($sessao->presentes, $ebi->participantesPorAuxiliar, $sessao->auxiliaresEscalados)`.
- Requests: `EncerrarSessaoRequest` (`realizada` required boolean, `fim_real` nullable date); `CancelarSessaoRequest` (`motivo` required 3..500); `CorrigirSessaoRequest` (campos `sometimes`/nullable, `motivo` nullable 3..500).

Rotas:

```php
Route::get('ebis/{id}/sessions', [SessaoController::class, 'index'])
    ->middleware('can:sessao.ler,ebi,{id}')->name('sessions.index');
Route::get('sessions/{id}', [SessaoController::class, 'show'])->name('sessions.show');
Route::post('sessions/{id}/open', [SessaoController::class, 'open'])
    ->middleware(IdempotenciaMiddleware::class)->name('sessions.open');
Route::post('sessions/{id}/close', [SessaoController::class, 'close'])
    ->middleware(IdempotenciaMiddleware::class)->name('sessions.close');
Route::post('sessions/{id}/cancel', [SessaoController::class, 'cancel'])
    ->middleware(IdempotenciaMiddleware::class)->name('sessions.cancel');
Route::patch('sessions/{id}', [SessaoController::class, 'update'])
    ->middleware(IdempotenciaMiddleware::class)->name('sessions.update');
```

Binds: `ConsultarSessao::class => ConsultaSessao::class`, `GerirSessaoEbi::class => GerenciadorSessao::class`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/SessaoTest.php`
Expected: PASS (6 testes).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Sessoes backend/app/Http/Requests/Agenda backend/app/Http/Resources/SessaoResource.php backend/app/Http/Controllers/Api/V1/SessaoController.php backend/app/Providers/DomainServiceProvider.php backend/routes/api.php backend/tests/Feature/Agenda/SessaoTest.php
git commit -m "feat(sessoes): ciclo da sessao, correcao e equipe minima (RF-06, CA-23)"
```

---

### Task 11: Presença — busca com elegibilidade e registro idempotente

**Files:**
- Create: `backend/app/Domain/Sessoes/Contracts/PresencaRegistrada.php`
- Create: `backend/app/Domain/Sessoes/Contracts/ResultadoPresenca.php`
- Create: `backend/app/Domain/Sessoes/Contracts/RegistrarPresenca.php`
- Create: `backend/app/Domain/Sessoes/Contracts/ConsultarPresenca.php`
- Create: `backend/app/Domain/Sessoes/Services/RegistradorPresenca.php`
- Create: `backend/app/Domain/Sessoes/Services/ConsultaPresenca.php`
- Create: `backend/app/Http/Requests/Agenda/RegistrarPresencaRequest.php`
- Create: `backend/app/Http/Resources/PresencaResource.php`
- Create: `backend/app/Http/Controllers/Api/V1/PresencaController.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Agenda/PresencaTest.php`

**Interfaces:**
- Consumes: `ConsultarSessao::detalhe` (Task 10), `ConsultarEbi::dados` (Task 4), `AvaliarElegibilidade::avaliarPorId` (Task 5), `ConsultarParticipante::buscar/encontrar` (Task 5), `ConsultarOrganizacao::nomesDeIgrejas` (Task 4).
- Produces:
  - `PresencaRegistrada` (readonly): `string $id, string $sessaoId, string $participanteId, ?string $participanteNome, ?string $matricula, string $registradoPor, string $registradoEm, string $origem, string $status, ?string $estornadoEm, ?string $motivoEstorno`.
  - `ResultadoPresenca` (readonly): `PresencaRegistrada $presenca, bool $nova, ?string $aviso`.
  - `RegistrarPresenca::registrar(string $sessaoId, string $participanteId, string $usuarioId, string $origem = 'web'): ResultadoPresenca`.
  - `ConsultarPresenca::daSessao(string $sessaoId): array<int, PresencaRegistrada>`, `ebiDaPresenca(string $presencaId): ?string`.
  - `GET /sessions/{id}/participants` → `{data: [{id, nome, matricula, igreja_referencia, elegivel, motivo_bloqueio}]}`.

- [ ] **Step 1: Escrever o teste**

```php
<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-18 13:00:00',
        'fim_previsto' => '2026-11-18 14:30:00',
        'status' => Sessao::STATUS_ABERTA,
    ]);
});

it('busca exige finalidade e termo com tres caracteres', function () {
    $this->getJson("/api/v1/sessions/{$this->sessao->id}/participants?busca=Ana", $this->auth)
        ->assertStatus(422)->assertJsonPath('code', 'finalidade_obrigatoria');

    $this->getJson("/api/v1/sessions/{$this->sessao->id}/participants?busca=An&finalidade=presenca", $this->auth)
        ->assertStatus(422)->assertJsonPath('code', 'busca_curta');
});

it('busca devolve elegibilidade e audita a leitura', function () {
    $elegivel = Participante::factory()->create(['nome' => 'Ana Clara', 'data_nascimento' => '2016-01-01', 'igreja_referencia_id' => $this->igreja->id]);
    $bloqueado = Participante::factory()->create(['nome' => 'Ana Paula', 'data_nascimento' => '2013-01-01', 'igreja_referencia_id' => $this->igreja->id]);
    Participante::factory()->create(['nome' => 'Ana Bebe', 'data_nascimento' => '2024-06-01', 'igreja_referencia_id' => $this->igreja->id]);

    $this->getJson("/api/v1/sessions/{$this->sessao->id}/participants?busca=Ana&finalidade=registro_presenca", $this->auth)
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['id' => $elegivel->id, 'elegivel' => true, 'motivo_bloqueio' => null])
        ->assertJsonFragment(['id' => $bloqueado->id, 'elegivel' => false, 'motivo_bloqueio' => 'excecao_ausente_ou_invalida']);

    $evento = EventoAuditoria::query()->where('acao', 'participante.buscado')->firstOrFail();
    expect($evento->motivo)->toBe('registro_presenca')
        ->and($evento->metadados_minimizados['sessao_id'])->toBe($this->sessao->id);
});

it('registra presenca uma unica vez e devolve a existente no replay', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);

    $primeira = $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", [
        'participante_id' => $p->id,
    ], $this->auth)->assertCreated()->json('presenca.id');

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", [
        'participante_id' => $p->id,
    ], $this->auth)->assertOk()->assertJsonPath('presenca.id', $primeira);

    expect(Presenca::query()->where('sessao_id', $this->sessao->id)->count())->toBe(1);
});

it('recusa sessao prevista e bloqueia 12 anos sem excecao', function () {
    $p = Participante::factory()->create(['data_nascimento' => '2013-01-01']);

    $this->sessao->update(['status' => Sessao::STATUS_PREVISTA]);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertStatus(409);

    $this->sessao->update(['status' => Sessao::STATUS_ABERTA]);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'inelegivel')
        ->assertJsonPath('motivo_bloqueio', 'excecao_ausente_ou_invalida');

    ExcecaoEtaria::factory()->create([
        'participante_id' => $p->id,
        'status' => ExcecaoEtaria::STATUS_APROVADA,
        'decidido_em' => now(),
        'vigente_ate' => '2027-01-01',
    ]);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertCreated();
});

it('avisa presenca simultanea em outra igreja no mesmo dia', function () {
    $outra = igrejaComEbi();
    $sessaoOutra = Sessao::factory()->create([
        'ebi_id' => $outra['ebi']->id,
        'igreja_id' => $outra['igreja']->id,
        'inicio_previsto' => '2026-11-18 18:00:00',
        'fim_previsto' => '2026-11-18 19:30:00',
        'status' => Sessao::STATUS_ABERTA,
    ]);
    $p = Participante::factory()->create(['data_nascimento' => '2016-01-01']);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", ['participante_id' => $p->id], $this->auth)
        ->assertCreated();

    $coordOutra = criarUsuarioComPapel('coordenadora', 'ebi', $outra['ebi']->id);
    $authOutra = [
        'Authorization' => 'Bearer '.$coordOutra->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra['ebi']->id}",
    ];

    $this->postJson("/api/v1/sessions/{$sessaoOutra->id}/attendance", ['participante_id' => $p->id], $authOutra)
        ->assertCreated()
        ->assertJsonPath('aviso', 'presenca_simultanea');
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/PresencaTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar `RegistradorPresenca`**

Fluxo de `registrar`:
1. `detalhe($sessaoId)` → 404 se nulo; status diferente de `aberta` → `409 sessao_em_estado_invalido`.
2. `avaliarPorId($participanteId, CarbonImmutable::parse($sessao->inicioPrevisto), $ebi->timezone)`; bloqueado → `ProblemException('inelegivel', 'Participante inelegível', 422, ..., ['motivo_bloqueio' => $resultado->motivo])`.
3. Existente `status = valida` → `ResultadoPresenca(existente, nova: false, aviso: null)`.
4. `DB::transaction`: cria `Presenca` (`registrado_por`, `registrado_em = now()`, `origem`); em `QueryException` de unicidade, busca a válida e devolve `nova: false`.
5. Audita `presenca.registrada` (`escopo = "ebi:{$ebiId}"`, metadados `sessao_id`, `participante_id`, `origem`).
6. Aviso de simultaneidade: existe presença válida do mesmo participante em outra sessão cujo intervalo local (no fuso da sessão atual) cai no **mesmo dia local** e `igreja_id` diferente → `aviso = 'presenca_simultanea'`.

`ConsultaPresenca::daSessao` carrega presenças com relação ao participante e monta o DTO (nome/matrícula). `ebiDaPresenca` resolve via join `sessao`.

- [ ] **Step 4: Controller, request, resource e rotas**

`PresencaController`:
- `participantes(Request, string $id)`: resolve sessão (404), `Gate::any([Operacoes::PRESENCA_REGISTRAR, Operacoes::PRESENCA_LER], ['ebi', $sessao->ebiId])`, exige finalidade (copiar o helper privado de `ParticipantController`), `busca` com 3+ caracteres (`422 busca_curta`), consulta `ConsultarParticipante::buscar($termo, 20)`, avalia cada um, resolve nomes de igreja em lote, audita `participante.buscado` e devolve `{data: [...]}`.
- `store(RegistrarPresencaRequest, string $id)`: autoriza `PRESENCA_REGISTRAR`, chama `registrar`, responde `201`/`200` com `{presenca, aviso}`.
- `index(string $id)`: autoriza `PRESENCA_LER`, devolve `{data: [PresencaResource]}`.

`RegistrarPresencaRequest`: `participante_id` required ulid existente em `participante`; `origem` nullable `in:web,app`.

Rotas:

```php
Route::get('sessions/{id}/participants', [PresencaController::class, 'participantes'])
    ->name('sessions.participants');
Route::get('sessions/{id}/attendance', [PresencaController::class, 'index'])
    ->name('sessions.attendance.index');
Route::post('sessions/{id}/attendance', [PresencaController::class, 'store'])
    ->middleware(IdempotenciaMiddleware::class)->name('sessions.attendance.store');
```

Binds: `RegistrarPresenca::class => RegistradorPresenca::class`, `ConsultarPresenca::class => ConsultaPresenca::class`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/PresencaTest.php`
Expected: PASS (5 testes).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Sessoes backend/app/Http/Requests/Agenda/RegistrarPresencaRequest.php backend/app/Http/Resources/PresencaResource.php backend/app/Http/Controllers/Api/V1/PresencaController.php backend/app/Providers/DomainServiceProvider.php backend/routes/api.php backend/tests/Feature/Agenda/PresencaTest.php
git commit -m "feat(presenca): busca com elegibilidade e registro idempotente (RF-08, CA-02)"
```

---

### Task 12: Estorno e histórico de presenças

**Files:**
- Create: `backend/app/Domain/Sessoes/Contracts/HistoricoPresenca.php`
- Create: `backend/app/Domain/Sessoes/Contracts/EstornarPresenca.php`
- Create: `backend/app/Domain/Sessoes/Services/EstornadorPresenca.php`
- Modify: `backend/app/Domain/Sessoes/Contracts/ConsultarPresenca.php`
- Modify: `backend/app/Domain/Sessoes/Services/ConsultaPresenca.php`
- Create: `backend/app/Support/Presenca/AutorizacaoHistoricoPresenca.php`
- Create: `backend/app/Http/Requests/Agenda/EstornarPresencaRequest.php`
- Create: `backend/app/Http/Controllers/Api/V1/HistoricoPresencaController.php`
- Modify: `backend/app/Http/Controllers/Api/V1/PresencaController.php` (método `estornar`)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Agenda/EstornoHistoricoTest.php`

**Interfaces:**
- Consumes: `Sessao`, `Presenca`, `ConsultarEbi`, `ConsultarParticipante::encontrar`, `VerificarPropriedadeParticipante`, `AutorizarOperacao`, `ConsultarHierarquia`.
- Produces:
  - `HistoricoPresenca` (readonly): `string $id, string $sessaoId, string $ebiId, string $ebiNome, string $inicioPrevisto, string $status, string $registradoEm`.
  - `EstornarPresenca::estornar(string $presencaId, string $motivo, string $usuarioId): PresencaRegistrada`.
  - `ConsultarPresenca::doParticipante(string $participanteId, ?string $de = null, ?string $ate = null): array<int, HistoricoPresenca>`.
  - `AutorizacaoHistoricoPresenca::exigirOperador(?ContextoAtuacao $contexto, string $participanteId, ?string $igrejaReferenciaId): void` e `exigirResponsavel(string $usuarioId, string $participanteId): void`.

- [ ] **Step 1: Escrever o teste**

```php
<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-18 13:00:00',
        'fim_previsto' => '2026-11-18 14:30:00',
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
    $this->participante = Participante::factory()->create(['igreja_referencia_id' => $this->igreja->id]);
    $this->presenca = Presenca::factory()->create([
        'sessao_id' => $this->sessao->id,
        'participante_id' => $this->participante->id,
        'status' => Presenca::STATUS_VALIDA,
    ]);
});

it('estorna preservando o original, com motivo e auditoria', function () {
    $registradoEm = $this->presenca->registrado_em;

    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'registro em duplicidade'], $this->auth)
        ->assertOk()
        ->assertJsonPath('status', 'estornada')
        ->assertJsonPath('motivo_estorno', 'registro em duplicidade');

    $this->presenca->refresh();
    expect($this->presenca->status)->toBe(Presenca::STATUS_ESTORNADA)
        ->and($this->presenca->registrado_em->equalTo($registradoEm))->toBeTrue()
        ->and($this->presenca->estornado_por)->not->toBeNull()
        ->and(EventoAuditoria::query()->where('acao', 'presenca.estornada')->exists())->toBeTrue();
});

it('exige motivo e recusa estorno repetido', function () {
    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'ab'], $this->auth)
        ->assertStatus(422);

    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'erro'], $this->auth)->assertOk();
    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'erro'], $this->auth)
        ->assertStatus(409)->assertJsonPath('code', 'presenca_ja_estornada');
});

it('permite nova presenca depois do estorno', function () {
    $this->postJson("/api/v1/attendance/{$this->presenca->id}/reversal", ['motivo' => 'erro'], $this->auth)->assertOk();

    $this->sessao->update(['status' => Sessao::STATUS_ABERTA]);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/attendance", [
        'participante_id' => $this->participante->id,
    ], $this->auth)->assertCreated();

    expect(Presenca::query()->where('sessao_id', $this->sessao->id)->count())->toBe(2);
});

it('historico do operador respeita o escopo e lista EBI e data', function () {
    $this->getJson("/api/v1/participants/{$this->participante->id}/attendance", $this->auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ebi.nome', $this->ebi->nome);

    $outra = igrejaComEbi()['ebi'];
    $intrusa = criarUsuarioComPapel('coordenadora', 'ebi', $outra->id);
    $auth = [
        'Authorization' => 'Bearer '.$intrusa->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$outra->id}",
    ];

    $this->getJson("/api/v1/participants/{$this->participante->id}/attendance", $auth)->assertForbidden();
});

it('responsavel ve so o historico do proprio dependente', function () {
    $resp = criarUsuarioComPapel('responsavel');
    $auth = ['Authorization' => 'Bearer '.$resp->createToken('t')->plainTextToken];

    $this->getJson("/api/v1/me/participants/{$this->participante->id}/attendance", $auth)->assertForbidden();

    ResponsavelParticipante::factory()->create([
        'usuario_id' => $resp->id,
        'participante_id' => $this->participante->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_APROVADA,
        'inicio' => now()->subMonth()->toDateString(),
        'fim' => null,
    ]);

    $this->getJson("/api/v1/me/participants/{$this->participante->id}/attendance", $auth)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/EstornoHistoricoTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

- `EstornadorPresenca`: 404 se não existir; `status = estornada` → `409 presenca_ja_estornada`; grava `estornado_por`, `estornado_em`, `motivo_estorno`; audita `presenca.estornada` (`motivo`, `escopo = "ebi:{$ebiId}"`, metadados `presenca_id`, `sessao_id`).
- `ConsultaPresenca::doParticipante`: `Presenca` + `sessao.ebi` ordenado por `registrado_em` desc, filtros `de/ate` por `registrado_em`; nome da EBI via `ConsultarEbi::dados` (cache por id no laço).
- `AutorizacaoHistoricoPresenca`:
  - `exigirOperador`: sem igreja de referência → permite só `super_admin`; com igreja, resolve `ConsultarHierarquia::ebiDaigreja` e chama `AutorizarOperacao::permite($contexto, Operacoes::PRESENCA_LER, 'ebi'|'igreja', $id)`; nega com 403.
  - `exigirResponsavel`: `VerificarPropriedadeParticipante::podeAcessar`; nega com 403.
- `PresencaController::estornar`: resolve `ConsultarPresenca::ebiDaPresenca` (404), autoriza `PRESENCA_ESTORNAR`, chama o serviço, devolve `PresencaResource` 200.
- `HistoricoPresencaController::operador` / `responsavel`: usam `ConsultarParticipante::encontrar` (404), a autorização acima e `ConsultarPresenca::doParticipante`, devolvendo `{data: [...]}` (EBI aninhada).
- Rotas:

```php
Route::post('attendance/{id}/reversal', [PresencaController::class, 'estornar'])
    ->middleware(IdempotenciaMiddleware::class)->name('attendance.reversal');
Route::get('participants/{id}/attendance', [HistoricoPresencaController::class, 'operador'])
    ->name('participants.attendance');
Route::get('me/participants/{id}/attendance', [HistoricoPresencaController::class, 'responsavel'])
    ->name('me.participants.attendance');
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/EstornoHistoricoTest.php`
Expected: PASS (5 testes).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Sessoes backend/app/Support/Presenca backend/app/Http/Requests/Agenda/EstornarPresencaRequest.php backend/app/Http/Controllers/Api/V1/HistoricoPresencaController.php backend/app/Http/Controllers/Api/V1/PresencaController.php backend/app/Providers/DomainServiceProvider.php backend/routes/api.php backend/tests/Feature/Agenda/EstornoHistoricoTest.php
git commit -m "feat(presenca): estorno auditado e historico por perfil (RF-08, CA-13)"
```

---

### Task 13: Contingência em papel

**Files:**
- Modify: `backend/app/Domain/Sessoes/Contracts/RegistrarPresenca.php`
- Modify: `backend/app/Domain/Sessoes/Services/RegistradorPresenca.php`
- Create: `backend/app/Http/Requests/Agenda/ContingenciaPresencaRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/PresencaController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Agenda/ContingenciaTest.php`

**Interfaces:**
- Consumes: Task 11 (`RegistradorPresenca`), `Operacoes::SESSAO_GERENCIAR`.
- Produces: `RegistrarPresenca::registrarContingencia(string $sessaoId, string $participanteId, string $motivo, string $usuarioId): ResultadoPresenca` (sempre `nova = true` ou replay idempotente) e `POST /sessions/{id}/contingency-attendance`.

- [ ] **Step 1: Escrever o teste**

```php
<?php

use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Sessoes\Models\Presenca;
use App\Domain\Sessoes\Models\Sessao;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coord = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->sessao = Sessao::factory()->create([
        'ebi_id' => $this->ebi->id,
        'igreja_id' => $this->igreja->id,
        'inicio_previsto' => '2026-11-18 13:00:00',
        'fim_previsto' => '2026-11-18 14:30:00',
        'status' => Sessao::STATUS_ENCERRADA,
        'realizada' => true,
    ]);
    $this->participante = Participante::factory()->create(['data_nascimento' => '2016-01-01']);
});

it('lanca presenca em papel em sessao encerrada com motivo e auditoria', function () {
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id,
        'motivo' => 'queda de energia — lista em papel',
    ], $this->auth)->assertCreated()->assertJsonPath('presenca.origem', 'contingencia');

    $evento = EventoAuditoria::query()->where('acao', 'presenca.contingencia')->firstOrFail();
    expect($evento->motivo)->toBe('queda de energia — lista em papel');
});

it('recusa sessao aberta ou cancelada', function () {
    $this->sessao->update(['status' => Sessao::STATUS_ABERTA]);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => 'papel',
    ], $this->auth)->assertStatus(409);

    $this->sessao->update(['status' => Sessao::STATUS_CANCELADA]);
    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => 'papel',
    ], $this->auth)->assertStatus(409);
});

it('respeita a elegibilidade e a unicidade', function () {
    $bloqueado = Participante::factory()->create(['data_nascimento' => '2013-01-01']);

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $bloqueado->id, 'motivo' => 'lista em papel',
    ], $this->auth)->assertStatus(422)->assertJsonPath('code', 'inelegivel');

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => 'lista em papel',
    ], $this->auth)->assertCreated();

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => 'lista em papel',
    ], $this->auth)->assertOk();

    expect(Presenca::query()->where('sessao_id', $this->sessao->id)->count())->toBe(1);
});

it('auxiliar nao lanca contingencia', function () {
    $aux = criarUsuarioComPapel('auxiliar', 'ebi', $this->ebi->id);
    $auth = [
        'Authorization' => 'Bearer '.$aux->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=auxiliar;escopo=ebi:{$this->ebi->id}",
    ];

    $this->postJson("/api/v1/sessions/{$this->sessao->id}/contingency-attendance", [
        'participante_id' => $this->participante->id, 'motivo' => 'lista em papel',
    ], $auth)->assertForbidden();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/ContingenciaTest.php`
Expected: FAIL.

- [ ] **Step 3: Implementar**

- `RegistradorPresenca::registrarContingencia` reaproveita a avaliação de elegibilidade e a checagem de existente, mas exige `status = encerrada` (qualquer outra → `409 sessao_em_estado_invalido`) e grava `origem = contingencia`; audita `presenca.contingencia` com `motivo` e metadados `{sessao_id, participante_id}`.
- `ContingenciaPresencaRequest`: `participante_id` required ulid existente; `motivo` required 3..500.
- `PresencaController::contingencia`: resolve a sessão (404), autoriza `Gate::authorize(Operacoes::SESSAO_GERENCIAR, ['ebi', $sessao->ebiId])`, chama o serviço e devolve 201/200.
- Rota:

```php
Route::post('sessions/{id}/contingency-attendance', [PresencaController::class, 'contingencia'])
    ->middleware(IdempotenciaMiddleware::class)->name('sessions.attendance.contingency');
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test tests/Feature/Agenda/ContingenciaTest.php`
Expected: PASS (4 testes).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Sessoes backend/app/Http/Requests/Agenda/ContingenciaPresencaRequest.php backend/app/Http/Controllers/Api/V1/PresencaController.php backend/routes/api.php backend/tests/Feature/Agenda/ContingenciaTest.php
git commit -m "feat(presenca): lancamento de lista em papel na sessao encerrada (RNF-03)"
```

---

### Task 14: Tela Sessões (lista, ciclo e painel da agenda)

**Files:**
- Create: `frontend/src/pages/Sessoes.tsx`
- Create: `frontend/src/components/agenda/PainelAgenda.tsx`
- Modify: `frontend/src/lib/formatos.ts` (rótulos de status de sessão e `formatarHora`)

**Interfaces:**
- Consumes: `api` (`openapi-fetch`), `cabecalhos()`, `lerErro`, `EstadoTabela`, `ConfirmarAcao`, `Dialog*`, `toast`; tipos `components['schemas']['EbiResumo' | 'Sessao' | 'AgendaEbi' | 'AgendaExcecao' | 'GeracaoSessoes']`.
- Produces: `export function Sessoes({ onAbrirPresenca, papeis }: { onAbrirPresenca: (sessaoId: string) => void; papeis: string[] })` e `export function PainelAgenda({ ebiId, podeGerenciar, onAgendaAlterada }: { ebiId: string; podeGerenciar: boolean; onAgendaAlterada?: () => void })`, usados pela Task 16.

- [ ] **Step 1: Acrescentar rótulos e hora em `lib/formatos.ts`**

```ts
/** `2026-11-18T13:00:00Z` → `13:00` (hora local do navegador). */
export function formatarHora(valor?: string | null): string {
  if (!valor) return '—'
  const data = new Date(valor)
  return Number.isNaN(data.getTime())
    ? valor
    : data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}
```

Em `STATUS`, acrescentar `prevista: 'Prevista'`, `aberta: 'Aberta'`, `encerrada: 'Encerrada'`, `cancelada: 'Cancelada'`, `valida: 'Válida'`, `estornada: 'Estornada'`, `feriado: 'Feriado'`, `cancelamento: 'Cancelamento'`, `horario_especial: 'Horário especial'`.

- [ ] **Step 2: Criar `Sessoes.tsx`**

Estrutura (estado e comportamento, não pixel):
1. `ebis` via `GET /me/ebis` no `useEffect`; `ebiId` inicial = primeiro da lista.
2. `de` = hoje, `ate` = hoje + 28 dias (ISO `YYYY-MM-DD`).
3. `GET /ebis/{id}/sessions?de&ate` a cada mudança de `ebiId`/período; estados `carregando`, `erro`, `sessoes`.
4. Cabeçalho: `<select>` de EBI (`id`, `nome — igreja_nome`) e dois `<input type="date">`.
5. Botão "Gerar próximas semanas" (só quando a lista está vazia): `POST /ebis/{id}/sessions/generate` com `{ de, ate }`; toast com `criadas`; recarrega.
6. Tabela agrupada por dia (`formatarData(inicio_previsto)`), colunas: horário (`formatarHora`), tema, situação (`rotuloStatus`), presenças (`presentes`), ações.
7. Ações por situação:
   - `prevista`: "Abrir" (`POST /sessions/{id}/open`), "Cancelar" (diálogo com `motivo` obrigatório → `/cancel`).
   - `aberta`: "Presença" (`onAbrirPresenca(id)`), "Encerrar" (diálogo com dois botões "Realizada"/"Não realizada" → `/close` `{realizada}`).
   - `encerrada`: "Presença" e "Corrigir" (diálogo com `tema`, `passagem_biblica`, `auxiliares_escalados`, `motivo` → `PATCH /sessions/{id}`; `motivo` obrigatório).
   - `cancelada`: sem ações; exibir `motivo_cancelamento`.
8. Erros: `lerErro(error, '...').detail` em `<Alert variant="destructive" role="alert">`; sucesso: `toast.success`.
9. `podeGerenciar = papeis.some((p) => ['adm_igreja', 'coordenadora'].includes(p))`; o botão "Gerar", as ações de ciclo e o painel da agenda só aparecem com `podeGerenciar` (a API continua sendo a autoridade). Renderizar `<PainelAgenda ebiId={ebiId} podeGerenciar={podeGerenciar} onAgendaAlterada={carregar} />` abaixo da tabela.

- [ ] **Step 3: Criar `PainelAgenda.tsx`**

Duas seções com `Tabs` ("Faixas" e "Exceções"):
- **Faixas**: `GET /ebis/{id}/agenda`. Tabela (dia da semana por extenso — array `['','Segunda',...]`, horário, vigência, situação "vigente"/"futura"/"encerrada"). Formulário de criação (`select` dia, `input type="time"` ×2, `input type="date"` ×2) → `POST`; botão "Encerrar" por faixa (`DELETE /agenda/{id}`) com `ConfirmarAcao`.
- **Exceções**: `GET /ebis/{id}/agenda-exceptions`. Tabela (data, tipo, horário ou "dia inteiro", motivo) e botão "Remover" (`DELETE /agenda-exceptions/{id}`). Formulário: `select` tipo, data, `select` de faixa (obrigatório só em `horario_especial`), horários (só em `horario_especial`), motivo → `POST`.
- `podeGerenciar=false`: esconde formulários e botões de escrita (a API continua sendo a autoridade).
- Após cada escrita, recarrega a seção e dispara `onAgendaAlterada` opcional via prop para a lista de sessões recarregar (declare `onAgendaAlterada?: () => void`).

- [ ] **Step 4: Verificar tipos e lint**

Run, em `frontend/`: `npm run lint`
Expected: sem erros.

Run, em `frontend/`: `npm run build`
Expected: build conclui (a tela ainda não está montada no `App`; `tsc -b` verifica o arquivo).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/pages/Sessoes.tsx frontend/src/components/agenda/PainelAgenda.tsx frontend/src/lib/formatos.ts
git commit -m "feat(web): tela de sessoes com ciclo e painel da agenda (E04)"
```

---

### Task 15: Tela Presença (3 toques, estorno, contingência e equipe mínima)

**Files:**
- Create: `frontend/src/pages/Presenca.tsx`

**Interfaces:**
- Consumes: `GET /sessions/{id}` (`SessaoDetalhe`), `GET /sessions/{id}/attendance`, `GET /sessions/{id}/participants?busca&finalidade`, `POST /sessions/{id}/attendance`, `POST /sessions/{id}/contingency-attendance`, `POST /attendance/{id}/reversal`.
- Produces: `export function Presenca({ sessaoId, onVoltar, papeis }: { sessaoId: string; onVoltar: () => void; papeis: string[] })`, usada pela Task 16.

- [ ] **Step 1: Montar o fluxo**

1. Carrega detalhe e presenças em paralelo; `sessao.status` decide o modo.
2. Cabeçalho: `onVoltar` ("Voltar para sessões"), nome da EBI, `formatarDataHora(inicio_previsto)`, selo de situação.
3. Busca: `form` com `input` (nome ou matrícula) e `input` de finalidade (default `registro_presenca`, com `Sugestoes` de `SUGESTOES_FINALIDADE` estendida com `{ valor: 'registro_presenca', rotulo: 'Registrar presença na sessão' }`). Debounce de 300 ms via `useEffect` + `setTimeout`; menos de 3 caracteres limpa resultados sem chamar a API.
4. Resultados em tabela: nome, matrícula, igreja de referência, selo "Elegível" (`Badge` verde) ou "Bloqueado: <motivo>" (`Badge` destructive). Linha é um `button` que seleciona o candidato (toque 2).
5. Barra fixa com "Confirmar presença de <nome>" (toque 3) chama `POST` (ou contingência, no modo contingência); `201`/`200` → toast, limpa seleção/busca, recarrega presenças e detalhe.
6. Lista de presentes: hora (`formatarHora(registrado_em)`), nome, matrícula, selo "contingência" quando `origem === 'contingencia'`, botão "Estornar" (diálogo com `motivo` 3..500 → `/reversal`). Presenças `estornada` aparecem riscadas com motivo.
7. Faixa de equipe mínima: quando `sessao.equipe_minima.alerta`, `<Alert>` com "Equipe mínima: N presentes exigem X auxiliares; Y informados."; sem alerta, texto discreto com os números.
8. Contingência: botão "Lançar lista em papel" visível quando `status === 'encerrada'` e `papeis` contém `adm_igreja` ou `coordenadora`; alterna `modoContingencia` (faixa de aviso + campo `motivo` obrigatório antes de confirmar cada presença, enviando para `/contingency-attendance`). O registro normal (botão de confirmar) só aparece quando `papeis` contém `coordenadora` ou `auxiliar`.
9. Sessão `cancelada` não mostra busca nem registro; mostra apenas os presentes (se houver) e o motivo do cancelamento.
10. Erros via `lerErro(...).detail` em `Alert`; tudo com `aria-live="polite"` na região de resultados.

- [ ] **Step 2: Verificar tipos e lint**

Run, em `frontend/`: `npm run lint`
Expected: sem erros.

Run, em `frontend/`: `npm run build`
Expected: build conclui.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/pages/Presenca.tsx
git commit -m "feat(web): tela de presenca com busca, estorno e contingencia (E04)"
```

---

### Task 16: Navegação, histórico no perfil e verificação manual dos 3 toques

**Files:**
- Create: `frontend/src/components/HistoricoPresencas.tsx`
- Modify: `frontend/src/pages/Participantes.tsx`
- Modify: `frontend/src/pages/Dependentes.tsx`
- Modify: `frontend/src/components/layout/ListaNav.tsx`
- Modify: `frontend/src/App.tsx`

**Interfaces:**
- Consumes: Tasks 14 e 15 (`Sessoes`, `Presenca`), `GET /participants/{id}/attendance` e `GET /me/participants/{id}/attendance`.
- Produces: telas `sessoes` e `presenca` alcançáveis pela casca; `HistoricoPresencas({ participanteId, rota })`.

- [ ] **Step 1: Criar `HistoricoPresencas.tsx`**

`Dialog` com gatilho `Button variant="ghost" size="sm"` "Histórico". Ao abrir, busca:
- `rota === 'operador'` → `GET /participants/{id}/attendance`
- `rota === 'responsavel'` → `GET /me/participants/{id}/attendance`

Tabela: data (`formatarDataHora(inicio_previsto)`), EBI (`ebi.nome`), situação (`rotuloStatus(status)`). `EstadoTabela` com `colunas={3}`; erro em `Alert`.

- [ ] **Step 2: Adicionar a coluna de ações**

Em `Participantes.tsx`, acrescentar `<TableHead>Ações</TableHead>` e `<TableCell><HistoricoPresencas participanteId={p.id} rota="operador" /></TableCell>`; `EstadoTabela colunas={4}`.

Em `Dependentes.tsx`, o mesmo com `rota="responsavel"` e `colunas={4}`.

- [ ] **Step 3: Nav e `App.tsx`**

`ListaNav.tsx`:
- `IdTela` ganha `'sessoes' | 'presenca'`.
- Novo item: `{ id: 'sessoes', rotulo: 'Sessões', descricao: 'Abra, encerre e registre presença.', icone: CalendarDaysIcon, grupo: 'Participantes', papeis: SESSOES }`, com `const SESSOES = ['super_admin','adm_regional','adm_setor','adm_igreja','coordenadora','auxiliar','ministerio_local']`.

`App.tsx`:
- importar `Sessoes`, `Presenca`.
- `ROTULOS`: `sessoes: 'Sessões'`, `presenca: 'Presença'`.
- novo estado `const [sessaoAtiva, setSessaoAtiva] = useState<string | null>(null)`.
- render:

```tsx
{tela === 'sessoes' && (
  <Sessoes
    papeis={usuario.papeis ?? []}
    onAbrirPresenca={(id) => {
      setSessaoAtiva(id)
      setTela('presenca')
    }}
  />
)}
{tela === 'presenca' && sessaoAtiva && (
  <Presenca
    sessaoId={sessaoAtiva}
    papeis={usuario.papeis ?? []}
    onVoltar={() => setTela('sessoes')}
  />
)}
```

- [ ] **Step 4: Verificar build**

Run, em `frontend/`: `npm run lint` → sem erros.
Run, em `frontend/`: `npm run build` → build grava em `backend/public/spa` (base `/spa/`).

- [ ] **Step 5: Verificação manual (3 toques e critérios)**

Run: `docker compose up -d` e, depois de mudanças PHP, `docker compose restart app horizon scheduler`.
Abrir `http://localhost:8000/spa/`, entrar com `coordenadora@ebi.test` / `senha` e o TOTP de demonstração `JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP` (seeder `MfaDemoSeeder`).

Checklist:
1. Sessões lista as sessões do EBI; "Gerar próximas semanas" cria sem duplicar ao repetir.
2. Abrir → registrar presença: digitar o nome (1), tocar no participante (2), confirmar (3). Nenhuma navegação de tela.
3. Participante de 13 anos sem exceção aparece como bloqueado e o registro devolve 422; com exceção vigente registra.
4. Estorno pede motivo, some da contagem e permite registrar de novo.
5. Sessão encerrada mostra "Lançar lista em papel" só para coordenadora/adm_igreja; presença entra com origem contingência.
6. Faixa de equipe mínima aparece quando `auxiliares_escalados` é menor que o necessário.
7. Histórico no perfil do participante (operador) e em Dependentes (responsável) lista data, EBI e situação.
8. Agenda: criar faixa, criar feriado e ver a sessão prevista daquele dia cancelada; tentar editar faixa vigente e receber o erro.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/components/HistoricoPresencas.tsx frontend/src/pages/Participantes.tsx frontend/src/pages/Dependentes.tsx frontend/src/components/layout/ListaNav.tsx frontend/src/App.tsx
git commit -m "feat(web): navegacao de sessoes/presenca e historico no perfil (E04)"
```

---

### Task 17: Documentação e gates completos

**Files:**
- Modify: `docs/CHANGELOG.md`
- Modify: `docs/03-dados/modelo-conceitual.md`
- Modify: `README.md`
- Modify: `docs/superpowers/specs/2026-11-13-sessoes-e-presenca-etapa-6-design.md` (status)

**Interfaces:**
- Consumes: todas as tasks.
- Produces: etapa documentada e verificada.

- [ ] **Step 1: Atualizar o changelog**

Em `docs/CHANGELOG.md`, nova seção no topo:

```markdown
## 2.18 — 17/11/2026

Etapa 6 — Sessões e presença (E04).

- **Agenda (RF-06):** faixas semanais por EBI (mais de uma no mesmo dia), vigência sem reescrever o passado, exceções `feriado`/`cancelamento` (dia inteiro) e `horario_especial` (por faixa). Feriado criado depois cancela as sessões `prevista` do dia, com autor e auditoria.
- **Sessões previstas (RF-06, CA-23):** geração idempotente pelo comando `sessoes:gerar` (diário no `scheduler`) e por `POST /ebis/{id}/sessions/generate`; sem duplicar, sem sobrepor e sem ressuscitar sessão cancelada.
- **Ciclo da sessão:** abrir, encerrar informando se foi realizada, cancelar com motivo e corrigir sessão encerrada com motivo, `antes/depois` em `sessao_correcao` e evento de auditoria.
- **Presença (RF-08, CA-02):** busca nacional auditada (finalidade obrigatória, 3+ caracteres, 20 resultados) com elegibilidade calculada no fuso da igreja; registro idempotente (índice único parcial), uma presença válida por participante/sessão, aviso de presença simultânea em outra igreja.
- **Estorno e histórico (CA-13):** estorno com motivo, autor e data preservando o original; histórico por participante para operador (escopo) e responsável (propriedade).
- **Contingência (RNF-03):** lançamento posterior da lista em papel em sessão encerrada, com motivo e auditoria.
- **Equipe mínima (ADR-0010):** aviso não bloqueante a partir de `auxiliares_escalados` informado na sessão (a escala nominal é a E07).
- **Contrato e portal:** 22 operações novas no OpenAPI e telas de Sessões e Presença; histórico no perfil do participante e do dependente.
- **Dívidas:** CA-27 (presença no escopo de mesclagem/correção), contingência em lote, `finalidade` nas listas/histórico (E06), aviso dos 12 anos (RF-15).
```

- [ ] **Step 2: Atualizar o modelo conceitual**

Em `docs/03-dados/modelo-conceitual.md`: incluir `auxiliares_escalados` na linha de `sessao`, `sessao_correcao` na lista de tabelas, `contingencia` no enum de `presenca.origem` e a restrição `sessao(ebi_id, inicio_previsto)` UNIQUE parcial onde `status <> 'cancelada'` na lista de índices.

- [ ] **Step 3: Atualizar README e status do design**

- `README.md`, seção Desenvolvimento: acrescentar "Sessões previstas: `docker compose exec scheduler php artisan sessoes:gerar --dias=28` (o `scheduler` já roda o comando diariamente)."
- No design, trocar `**Status:** proposto` por `**Status:** aprovado em 17/11/2026`.

- [ ] **Step 4: Gates completos**

```bash
docker compose up -d
docker compose restart app horizon scheduler
docker compose run --rm app php artisan test
docker compose run --rm app vendor/bin/pint --test
docker compose run --rm app composer phpstan
npx --yes @stoplight/spectral-cli lint docs/04-api/openapi.yaml --ruleset scripts/.spectral.yaml
docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"
```

Em `frontend/`: `npm run lint` e `npm run build`.

Expected: suíte inteira verde (inclui `tests/Arch/ModularBoundariesTest.php`), Pint sem diff, PHPStan nível 8 sem erro, Spectral sem erro, `check-endpoints` OK, lint/build sem erro.

- [ ] **Step 5: Commit**

```bash
git add docs/CHANGELOG.md docs/03-dados/modelo-conceitual.md README.md docs/superpowers/specs/2026-11-13-sessoes-e-presenca-etapa-6-design.md
git commit -m "docs: changelog e modelo da E04; gates completos"
```

