# Relatórios básicos (Etapa 7) — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** entregar o relatório único de presença (M-01, M-02 a M-05, M-09, ativos), com navegação pela hierarquia, supressão em cascata, exportação CSV e o corte de 5 na lista de participantes.

**Architecture:** um serviço novo `Relatorios` só orquestra. `Organizacao` entrega a hierarquia (filhos e igrejas da subárvore), `Sessoes` entrega os totais de presença e sessão, `Participantes` entrega o mapa de mesclagens e as contagens por igreja de referência. A supressão é aplicada depois, de baixo para cima, no serviço.

**Tech Stack:** Laravel 11 (PHP 8.4), Pest, PostgreSQL, OpenAPI + openapi-typescript, React 19 + Vite + Tailwind, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-19-relatorios-basicos-etapa-7-design.md`

## Global Constraints

- Fronteira de módulos (ADR-0002): nenhum módulo lê tabela de outro; só contratos.
- OpenAPI antes do código: rota no `openapi.yaml`, marca "no OpenAPI" em `endpoints.md`, depois regenerar `schema.d.ts`.
- Autorização no servidor: 403 por papel sem `relatorio.ler`; 404 para nó inexistente e para nó fora do escopo.
- Limiar de supressão: contagens de 1 a 4 → `valor: null`, `suprimido: true`; zero é zero.
- Supressão só para quem atua acima da igreja (papel + tipo de escopo `igreja`/`ebi`).
- M-09 nunca é suprimido e não dispara cascata.
- Período: `inicio`/`fim` inclusivos, `YYYY-MM-DD`, máximo 366 dias, padrão últimos 30 dias.
- Nenhum valor suprimido no CSV; textos neutralizados contra fórmula (`=`, `+`, `-`, `@`).
- Datas de sessão pelo fuso da igreja (`igrejas.timezone`).
- Texto ao usuário em pt-BR; sem acentos nos comentários de código quando o arquivo vizinho não tem (o repositório mistura, siga o arquivo).

## Review Focus

- Sessão realizada sem presença precisa contar em M-05 (protege contra `INNER JOIN`).
- Borda do período no fuso da igreja: 23h30 do último dia entra, 00h30 do dia seguinte não.
- Participante mesclado conta uma vez; desfazer volta a contar duas.
- Nó sem sessão no período: zero, não `null` nem erro.
- Cadeia de mesclagem (B→A, A→C) e ciclo não podem quebrar a contagem.

---

### Task 1: Operação `relatorio.ler` na matriz

**Files:**
- Modify: `backend/app/Domain/Identidade/Support/Operacoes.php`
- Modify: `backend/app/Domain/Identidade/Services/AutorizadorOperacao.php`
- Test: `backend/tests/Feature/Relatorios/AutorizacaoRelatorioTest.php`

**Interfaces:**
- Produces: `Operacoes::RELATORIO_LER = 'relatorio.ler'`; a matriz concede a `super_admin`, `adm_regional`, `adm_setor`, `adm_igreja`, `coordenadora`, `ministerio_central`, `ministerio_regional`, `ministerio_setorial`, `ministerio_local`.

- [ ] **Step 1: escrever o teste que falha**

```php
it('concede relatorio.ler aos nove papeis e nega a auxiliar e responsavel', function (string $papel) {
    expect(papelPermite($papel, Operacoes::RELATORIO_LER))->toBeTrue();
})->with(['super_admin', 'adm_regional', 'adm_setor', 'adm_igreja', 'coordenadora',
    'ministerio_central', 'ministerio_regional', 'ministerio_setorial', 'ministerio_local']);

it('nega relatorio.ler a auxiliar e responsavel', function (string $papel) {
    expect(papelPermite($papel, Operacoes::RELATORIO_LER))->toBeFalse();
})->with(['auxiliar', 'responsavel']);
```

Usar o mesmo helper de montagem de contexto dos testes de `AutorizacaoOperacaoTest.php`.

- [ ] **Step 2: rodar e ver falhar**

Run: `cd backend && php artisan test --filter=AutorizacaoRelatorio`
Expected: FAIL — `Undefined constant Operacoes::RELATORIO_LER`.

- [ ] **Step 3: implementar**

Constante em `Operacoes` e a operação nas listas dos nove papéis da `MATRIZ`.

- [ ] **Step 4: rodar e ver passar**, depois `php artisan test --filter=Autorizacao` para não quebrar a matriz existente.

- [ ] **Step 5: commit** — `feat(identidade): operacao relatorio.ler na matriz`.

---

### Task 2: Hierarquia — filhos e igrejas da subárvore

**Files:**
- Modify: `backend/app/Domain/Organizacao/Contracts/ConsultarHierarquia.php`
- Modify: `backend/app/Domain/Organizacao/Services/ConsultaHierarquia.php`
- Modify: `backend/app/Domain/Organizacao/Contracts/ConsultarOrganizacao.php` e `Services/ConsultaOrganizacao.php` (nomes dos nós)
- Test: `backend/tests/Feature/Relatorios/HierarquiaRelatorioTest.php`

**Interfaces:**
- Produces em `ConsultarHierarquia`:
  - `filhos(string $tipo, string $id): array` → lista de `['tipo' => string, 'id' => string, 'nome' => string]`, vazia para `ebi`;
  - `igrejasDaSubarvore(string $tipo, string $id): array` → `array<int, string>` de ids de igreja (para `ebi`, lista vazia);
  - `nosEbiDaSubarvore(string $tipo, string $id): array` → `array<int, string>` de ids de EBI sob o nó; para `ebi`, `[$id]`.
- Produces em `ConsultarOrganizacao`: `nomes(string $tipo, array $ids): array` → `id => nome` para `regional`, `setor`, `igreja`. Ignora ids inexistentes.

- [ ] **Step 1: teste que falha** — árvore Central → 2 regionais → setores → igrejas → EBIs; afirmar `filhos('central', …)` devolve as regionais e `filhos('ebi', …)` devolve `[]`; `igrejasDaSubarvore('setor', $s)` devolve as igrejas com vínculo vigente; igreja transferida sai do setor antigo; `nosEbiDaSubarvore('igreja', $i)` devolve os EBIs da igreja.

- [ ] **Step 2: rodar e ver falhar** — `php artisan test --filter=HierarquiaRelatorio`.

- [ ] **Step 3: implementar** nos serviços de consulta, usando só os modelos de `Organizacao`.

- [ ] **Step 4: rodar e ver passar.**

- [ ] **Step 5: commit** — `feat(organizacao): filhos e igrejas da subarvore e nomes`.

---

### Task 3: Indicadores de presença e sessão

**Files:**
- Create: `backend/app/Domain/Sessoes/Contracts/ConsultarIndicadoresPresenca.php`
- Create: `backend/app/Domain/Sessoes/Services/ConsultaIndicadoresPresenca.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Relatorios/IndicadoresPresencaTest.php`

**Interfaces:**
- Produces `ConsultarIndicadoresPresenca`:
  - `totais(array $igrejaIds, array $ebiIds, string $inicio, string $fim, array $canonico): array` → `array<string, array{participantes_distintos:int, presencas:int, sessoes_realizadas:int}>` com chave `"igreja:{id}"` ou `"ebi:{id}"`;
  - `participantesComPresencaDesde(array $canonico, CarbonImmutable $desde): array` → `array<int,string>` de ids canônicos;
  - `ebisComSessaoDesde(CarbonImmutable $desde): array` → `array<int,string>` de ids de EBI.
- `$canonico` é `map id => id canônico` (do módulo Participantes). Presenças de ids ausentes do mapa contam por si mesmas.

- [ ] **Step 1: teste que falha** — cobrir: presença válida conta, estornada não; sessão `encerrada` + `realizada` conta em M-05 mesmo sem presença; presença em sessão fora do período não conta; sessão às 23h30 do último dia no fuso da igreja entra; 00h30 do dia seguinte não; EBI com sessão há 89 dias em `ebisComSessaoDesde(hoje-90)` e há 91 dias fora; `DISTINCT` sobre id canônico (duas presenças do mesmo participante → 1).

- [ ] **Step 2: rodar e ver falhar.**

- [ ] **Step 3: implementar** com uma consulta agregada por conjunto (`join` sessão × presença, `COUNT(DISTINCT)`), datas convertidas por `timezone` da igreja no filtro de período (`inicio_previsto` AT TIME ZONE).

- [ ] **Step 4: rodar e ver passar**; rodar `php artisan test tests/Feature/Agenda` para não regredir.

- [ ] **Step 5: commit** — `feat(sessoes): contrato de indicadores de presenca`.

---

### Task 4: Participantes — mapa canônico e contagens

**Files:**
- Create: `backend/app/Domain/Participantes/Contracts/ConsultarIndicadoresParticipante.php`
- Create: `backend/app/Domain/Participantes/Services/ConsultaIndicadoresParticipante.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Relatorios/IndicadoresParticipanteTest.php`

**Interfaces:**
- Produces `ConsultarIndicadoresParticipante`:
  - `mapaCanonico(): array` → `map id => id final`, resolvido transitivamente e sem ciclo (ciclo: menor id do componente);
  - `cadastradosPorIgreja(): array` → `igreja_id => int` (só `status = ativo`, só com `igreja_referencia_id`);
  - `cadastrados(): int` → soma de `cadastradosPorIgreja()`;
  - `idsAtivosDesde(array $canonico, CarbonImmutable $desdePresenca, CarbonImmutable $desdeCadastro, array $presencaIds): array` → ids de `ativo` que satisfazem presença recente ou cadastro recente.

- [ ] **Step 1: teste que falha** — mesclar B em A: mapa `B => A`; presenças de B contam 1 em M-02 junto com A. Mesclar A em C depois: `B => C`, `A => C`. Ciclo A↔B: ambos map para o menor id. Desfazer volta `B => B`. Inativo e mesclado fora de `cadastradosPorIgreja`; sem igreja de referência fora da contagem.

- [ ] **Step 2: rodar e ver falhar.**

- [ ] **Step 3: implementar** resolvendo cadeia por iteração com conjunto visitado. Dívida registrada: o `GerenciadorMesclagem` continua permitindo cadeia e ciclo.

- [ ] **Step 4: rodar e ver passar.**

- [ ] **Step 5: commit** — `feat(participantes): mapa canonico de mesclagem e contagens`.

---

### Task 5: Serviço de relatório com supressão em cascata

**Files:**
- Create: `backend/app/Domain/Relatorios/Contracts/ConsultarRelatorio.php`
- Create: `backend/app/Domain/Relatorios/Services/RelatorioPresenca.php`
- Create: `backend/app/Domain/Relatorios/Support/Visible` (value object ou `array` puro: `valor`, `suprimido`, `motivo`)
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Relatorios/RelatorioPresencaTest.php`

**Interfaces:**
- Produces `ConsultarRelatorio::presenca(string $tipo, string $id, string $inicio, string $fim, bool $suprimir): array` → o payload de `data` da spec (decisão 1), sem wrapper HTTP.
- `$suprimir` já vem decidido pelo controller (decisão 6: papel + tipo de escopo).

- [ ] **Step 1: teste que falha** — cobrir, em nó setor/regional/central: totais recalculados da subárvore, não somados; M-02 nacional com participante em duas regionais = 1; média = M-03 ÷ M-05 com uma casa, `null` quando M-05 = 0; supressão de 1 a 4; zero visível; cascata (EBI com 3 suprime igreja, setor, regional e central; mesmo nó suprimido aberto ou na linha do pai); `media_por_sessao` some com M-03 ou M-05 suprimido; M-09 = 1 aparece e não suprime o pai; `nao_se_aplica` em M-01/ativos no nó EBI; CA-18 (igreja transferida de setor: números migram).

- [ ] **Step 2: rodar e ver falhar.**

- [ ] **Step 3: implementar** — avaliar de baixo para cima; os totais do nó vêm direto de `Sessoes` sobre a subárvore (nunca somando filhos); os filhos da resposta também saem de baixo para cima.

- [ ] **Step 4: rodar e ver passar.**

- [ ] **Step 5: commit** — `feat(relatorios): servico de relatorio de presenca com cascata`.

---

### Task 6: Endpoint JSON

**Files:**
- Modify: `backend/routes/api.php`
- Create: `backend/app/Http/Controllers/Api/V1/ReportController.php`
- Create: `backend/app/Http/Requests/Relatorios/RelatorioPresencaRequest.php`
- Create: `backend/app/Http/Resources/RelatorioPresencaResource.php`
- Modify: `openapi.yaml`, `docs/api/endpoints.md`
- Test: `backend/tests/Feature/Relatorios/RelatorioEndpointTest.php`

**Interfaces:**
- Rota: `GET /api/v1/reports/attendance`, middleware `auth:sanctum` + `can:relatorio.ler`, nome `reports.attendance`.

- [ ] **Step 1: teste que falha** — 403 sem papel; 404 de nó inexistente; 404 de nó fora do escopo; 200 sem `nivel` usando o nó do contexto; validação de `inicio`/`fim` (obrigatórias, `fim >= inicio`, ≤ 366 dias) → 422; `X-Acting-Scope` revogado → 403 `acting_scope_invalido`; resposta traz `no`, `periodo`, `fuso`, `gerado_em`, `criterios`, `totais`, `filhos`.

- [ ] **Step 2: rodar e ver falhar.**

- [ ] **Step 3: implementar** — três checagens em ordem da decisão 3; `suprimir` calculado de `ContextoAtuacao` (papel `coordenadora|adm_igreja|ministerio_local` **e** `tipoEscopo` `igreja|ebi`).

- [ ] **Step 4: rodar e ver passar.**

- [ ] **Step 5: OpenAPI** — documentar a rota; remover `/reports/participants`; `npm run gen` (ou o script existente) e conferir o `schema.d.ts`.

- [ ] **Step 6: commit** — `feat(relatorios): endpoint de relatorio de presenca`.

---

### Task 7: Exportação CSV

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/ReportController.php`
- Create: `backend/app/Support/Relatorios/ExportadorCsv.php`
- Test: `backend/tests/Feature/Relatorios/ExportacaoCsvTest.php`

**Interfaces:**
- `formato=csv` no mesmo endpoint; `Content-Type: text/csv; charset=UTF-8`; colunas da decisão 10.

- [ ] **Step 1: teste que falha** — uma linha para o nó e uma por filho; valores iguais ao JSON; nenhum 1–4 nem total escondido para quem atua acima da igreja; nome iniciado por `=` sai neutralizado; evento `relatorio.exportado` registrado; sem `relatorio.ler` → 403; coluna `criterios` presente.

- [ ] **Step 2: rodar e ver falhar.**

- [ ] **Step 3: implementar** — reaproveita o payload; neutraliza textos com prefixo de fórmula.

- [ ] **Step 4: rodar e ver passar.**

- [ ] **Step 5: commit** — `feat(relatorios): exportacao csv`.

---

### Task 8: Corte de 5 na lista de participantes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/ParticipantController.php`
- Modify: `openapi.yaml` (resposta de `GET /participants` com `suprimido` e `motivo`)
- Test: `backend/tests/Feature/Relatorios/ListaParticipantesCorteTest.php`

**Interfaces:**
- Para contexto acima da igreja: agrupar por `igreja_referencia_id`, contar só `ativo`, remover grupos com 1 a 4 ativos ou nenhum ativo; resposta `{ data, suprimido: true }` quando algo foi removido; zero resultados sem `suprimido`.

- [ ] **Step 1: teste que falha** — 3 da igreja A e 6 da B → só B; 3 ativos + 2 inativos → grupo some; grupo sem igreja de referência nunca aparece; zero → `data: []` sem `suprimido`; coordenadora e adm_igreja na própria igreja veem tudo; participantes fora do território não contam para o limite.

- [ ] **Step 2: rodar e ver falhar.**

- [ ] **Step 3: implementar** depois do filtro territorial e antes de montar a resposta.

- [ ] **Step 4: rodar e ver passar**; rodar a suíte de Participantes.

- [ ] **Step 5: commit** — `feat(participantes): corte de 5 por igreja de referencia na busca`.

---

### Task 9: Tela de Relatórios

**Files:**
- Create: `frontend/src/pages/Relatorios.tsx`
- Modify: `frontend/src/App.tsx`, `frontend/src/components/layout/ListaNav.tsx`
- Test: `frontend/src/pages/Relatorios.test.tsx`

**Interfaces:**
- Item "Relatórios" no grupo "Administração"; papéis com `relatorio.ler`; client gerado a partir do `schema.d.ts`.

- [ ] **Step 1: teste que falha** — "< 5" para `limiar`; "Não divulgado" com a explicação para `filho_suprimido`; "—" para `nao_se_aplica`; trilha volta só até o nó do contexto; período padrão de 30 dias; rodapé com fuso, critérios e "gerado em"; botão de exportação.

- [ ] **Step 2: rodar e ver falhar** — `cd frontend && npm test -- Relatorios`.

- [ ] **Step 3: implementar**, reaproveitando a tabela de `Dependentes.tsx`.

- [ ] **Step 4: rodar e ver passar**; `npm run build`.

- [ ] **Step 5: commit** — `feat(frontend): tela de relatorios`.

---

### Task 10: Documentação

**Files:**
- Modify: `docs/02-regras-de-negocio/metricas-e-indicadores.md`
- Modify: `docs/06-arquitetura/decisoes/ADR-0020-supressao-e-definicao-de-ativo.md`
- Modify: `docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md`
- Modify: `docs/api/endpoints.md`

- [ ] **Step 1: aplicar as alterações da decisão 9 da spec** (linhas 21, 30, 34 e 7; ADR: fronteira acima da igreja, cascata, M-09 fora, M-01 separado de "ativo"; matriz: linha `relatorio.ler`; catálogo).

- [ ] **Step 2: conferir por busca** que não sobrou "acima do EBI" nem "equipe do próprio EBI".

- [ ] **Step 3: commit** — `docs: supressao acima da igreja e operacao relatorio.ler`.

---

### Task 11: Verificação final

- [ ] **Step 1:** `cd backend && ./vendor/bin/pest` — suíte inteira verde.
- [ ] **Step 2:** `cd frontend && npm test` e `npm run build` — verde.
- [ ] **Step 3:** `cd backend && ./vendor/bin/pint --test` — sem alterações pendentes.
- [ ] **Step 4:** chamar o agente `testador` com a spec e o diff `git merge-base main HEAD..HEAD`.
