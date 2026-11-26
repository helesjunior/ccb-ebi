# Plano de implementação — Escalas: equipes da coordenadora e pedido de vaga (E07, evolução)

> **Para quem executa sem contexto desta conversa:** use `superpowers:subagent-driven-development`. Um subagente novo por tarefa, revisão independente depois de cada tarefa, revisão da branch inteira no fim e o `testador` contra os critérios de aceite. As tarefas são sequenciais; cada uma termina com commit próprio.

**Spec:** [`docs/superpowers/specs/2026-11-24-escalas-equipes-e-vagas-design.md`](../specs/2026-11-24-escalas-equipes-e-vagas-design.md)
**Branch:** `feat/escalas-equipes-e-vagas`, criada de `main` (`d95052a`)
**Worktree:** `C:\Users\arl0001\Documents\Projetos\ccb-ebi\.worktrees\escalas-equipes-e-vagas`
**Base:** [`2026-11-20-escalas-e07-design.md`](../specs/2026-11-20-escalas-e07-design.md), ADR-0033, RF-07
**Estrutura:** 3 fases (fundação → escala com dona → vaga e telas) e 15 tarefas, da 0 à 14.

---

## Regras globais (valem para todas as tarefas)

### Comportamento

- **TDD obrigatório.** Escrever o teste, rodar e ver **falhar** pelo motivo esperado, implementar, rodar e ver passar. Sem exceção para "mudança pequena".
- **Cada tarefa termina com uma suíte verde.** Se ao terminar uma tarefa a suíte do módulo quebrar, conserte ali, não empurre para a tarefa seguinte. A única exceção prevista é a **Task 5**, que deixa **um** teste do frontend vermelho de propósito (o contrato de `equipe_id` só existe no servidor), com nota no código e conserto na **Task 13**.
- **Sem mudança silenciosa de spec.** Se o código real contradisser a spec, pare e relate; não decida sozinho.
- **OpenAPI antes do código:** rotas novas ou alteradas entram em `docs/04-api/openapi.yaml` e `docs/04-api/endpoints.md` **antes** da implementação, e o `schema.d.ts` é regenerado com `npm run gen:api`.
- **Auditoria (RN-10):** um evento por transição, `metadados_minimizados` só com ids. Nome, observação de vaga e texto livre nunca entram na auditoria.
- **Atomicidade:** escritas que mexem em escala, vaga ou oferta rodam em `TravaEscala` (lock da linha `escala` + `DB::transaction`). Toda guarda depende do estado relido **depois** do lock. Equipe **não** usa a `TravaEscala`: transação própria e `UNIQUE` traduzido para 409.
- **Gravar e responder erro:** quando uma operação precisa gravar (cancelar vaga) **e** responder erro, o callback devolve um resultado e o erro é lançado **depois** do commit (`ResultadoVaga::indisponivel()`).
- **Nada de nome de pessoa em texto de resposta nova** além do necessário dito na spec; leitura de nomes sempre por `ConsultarEquipeEbi::nomes` (uma chamada por resposta).

### Commits

- Um commit por tarefa. Prefixos permitidos: `feat(escalas):`, `fix(escalas):`, `refactor(escalas):`, `test(escalas):`, `chore(escalas):`, `docs(escalas):`.
- Mensagem em português, sem acento no assunto.

### Schema (decisão do construtor, aprovada)

- **A migration do E07 é editada**, não emendada por outra (`2026_11_20_100002_create_escala_tables.php`):
  - não há banco em produção (base recriada com `migrate:fresh --seed`);
  - acrescentar coluna `NOT NULL` com FK numa tabela existente se comporta diferente no SQLite e no PostgreSQL;
  - evita uma migration intermediária com a coluna opcional.
- `equipe` é criada **antes** de `escala` na mesma migration; `escala.equipe_id` nasce `NOT NULL` com `restrictOnDelete`.

### Ambiente (Docker)

O `docker-compose.yml` monta `./backend:/app` com caminho relativo: rodando **de dentro do worktree**, o container usa o código do worktree. O problema é a colisão com o ambiente principal (`ebi-dev`, portas 8000/5433). A **Task 0** cria um `docker-compose.worktree.yml` **local, sem commit**.

Comando padrão do backend, a partir da raiz do worktree:

```powershell
docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec app php artisan test --filter=NOME_DA_CLASSE
```

- O `-f` explícito deixa de fora o `docker-compose.override.yml` (S3 e ClamAV), que o E07 não usa.
- O volume nomeado `vendordata` nasce vazio e **sombra** o `vendor/` do host: `composer install` uma vez por volume novo (Task 0), senão `php artisan test` responde `Command "test" is not defined`.
- `docker compose exec` **não aceita `-e`**: variável de ambiente passa pelo `compose` pai (`$env:TESTE_PGSQL='1'; docker compose ... exec app ... ; Remove-Item Env:TESTE_PGSQL`).
- **Suíte inteira** (o `composer test` estoura o timeout de 300 s do Composer, e `pest --parallel` dá 81 falsos negativos porque os helpers são funções globais dos arquivos de teste):

```powershell
docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec -T app sh -c "php -d memory_limit=1536M vendor/bin/pest 2>&1 | tail -8"
```

- **Nunca** usar `--filter` com caminho de arquivo: helpers como `cenarioEscala()` só carregam com a suíte inteira lida. Sempre `--filter=ClasseDoTeste` ou `--filter="A|B|C"`.
- Depois de mexer em `MATRIZ`, `Operacoes` ou bindings: `... exec app php artisan optimize:clear`.

Frontend, no host, a partir de `frontend/`:

```powershell
npx vitest run src/pages/Escalas.test.tsx
npm run lint
npx tsc -b
```

---

## Review Focus (casos que atravessam tarefas)

Nenhum deles cabe numa única tarefa. Cada um tem o teste que o fixa na tarefa **dona**; as demais tarefas não podem quebrá-lo.

| # | Caso | Tarefa dona | Teste que fixa |
|---|---|---|---|
| RF-1 | **Ator com vários vínculos:** quem decide é o papel em que a pessoa está atuando (`X-Acting-Scope`), não a pessoa. A dona atuando como auxiliar **não** escolhe oferta nem escreve como dona. | Task 8 | `DonaDaEscalaTest`: `a dona atuando como auxiliar nao escreve na propria escala`; `a chefe que e dona da equipe pode escolher oferta` |
| RF-2 | **Vínculo só na igreja:** escopo `igreja:` vale para oferecer e para ser dona/membro de equipe. | Task 7 (equipe) e Task 11 (oferecer) | `EquipeEscalaTest`: `coordenadora com vinculo de igreja cria a propria equipe`; `VagaDominioTest`: `vinculo de igreja vale para oferecer` |
| RF-3 | **Borda de data e fuso:** a sessão começa às 23:00 UTC (20:00 local); conta a **data local**. `fim >= data`; vínculo com `inicio` no futuro não autoriza hoje. | Task 11 | `VagaDominioTest`: `elegibilidade usa a data local da sessao`; `vinculo com inicio futuro nao autoriza oferecer`; `EquipeEscalaTest`: `vinculo com inicio futuro nao entra na equipe` |
| RF-4 | **`Idempotency-Key`** no oferecer e no escolher não duplica oferta nem membro. | Task 12 | `VagaApiTest`: `repetir oferta e aceite com a mesma Idempotency-Key nao duplica` |
| RF-5 | **Pessoa já entrou na escala por outro caminho** antes do aceite: 422 `membro_ja_escalado`, **nada gravado**, oferta segue `pendente`. | Task 11 (domínio) + Task 12 (leitura `/me`) | `VagaDominioTest`: `oferta de quem ja entrou na escala nao pode ser escolhida`; `VagaApiTest`: `quem ja e membro ativo nao ve a vaga em /me` |

---

## Estrutura de arquivos

### Backend — novos

| Arquivo | Responsabilidade |
|---|---|
| `app/Domain/Escalas/Models/Equipe.php`, `EquipeMembro.php` | equipe da coordenadora e seu quadro |
| `app/Domain/Escalas/Models/VagaEscala.php`, `OfertaVagaEscala.php` | vaga e oferta |
| `app/Domain/Escalas/Database/Factories/{EquipeFactory,EquipeMembroFactory,VagaEscalaFactory,OfertaVagaEscalaFactory}.php` | fixtures |
| `app/Domain/Escalas/Services/VinculoEquipe.php` | autorização atual e elegibilidade na data (RF-2, RF-3) |
| `app/Domain/Escalas/Services/GerenciadorEquipe.php`, `ConsultaEquipeEscala.php` | regras e leitura da equipe |
| `app/Domain/Escalas/Services/DonaDaEscala.php` | quem escreve e quem escolhe oferta (RF-1) |
| `app/Domain/Escalas/Services/EncerradorVaga.php` | cancelamento de vaga fora da vaga |
| `app/Domain/Escalas/Services/GerenciadorVaga.php`, `ResultadoVaga.php` | ciclo da vaga e da oferta |
| `app/Domain/Escalas/Services/CancelamentoTroca.php` | cancelar trocas pendentes (extraído da alteração) |
| `app/Domain/Escalas/Services/ConsultaVagaEscala.php` | leitura e projeções (decisão 9) |
| `app/Domain/Escalas/Contracts/{GerirEquipe,ConsultarEquipeEscala,EquipeRef,GerirVagaEscala,ConsultarVagaEscala,ProjecaoVaga}.php` | contratos do módulo |
| `app/Http/Controllers/Api/V1/{EquipeEscalaController,VagaEscalaController,OfertaVagaController,MinhasVagasController}.php` | HTTP |
| `app/Http/Requests/Escalas/{CriarEquipeRequest,IncluirMembroEquipeRequest,AbrirVagaRequest}.php` | validação |
| `database/migrations/2026_11_25_100000_create_vaga_escala_tables.php` | vaga e oferta |
| `tests/Feature/Escalas/*.php` | ver cada tarefa |

### Backend — alterados

- `app/Domain/Escalas/Models/EscalaMembro.php`: constante única `PAPEIS_POR_FUNCAO`.
- `app/Domain/Escalas/Models/Escala.php`: `equipe_id`, `equipe()`, `vagas()`.
- `app/Domain/Escalas/Services/GerenciadorEscala.php`, `AvaliadorEquipe.php`: usar a constante única; `equipe_id`; dona; acréscimo; integrações de vaga.
- `app/Domain/Escalas/Services/GerenciadorTrocaEscala.php`: cancelar vaga ao aprovar troca.
- `app/Domain/Escalas/Services/ConsultaEscala.php`, `Contracts/EscalaVisao.php`, `Http/Support/AutorizacaoEscala.php`: `equipe`, `pode_gerir`, `pode_escolher_oferta`, `vagas`.
- `app/Domain/Escalas/Database/Factories/EscalaFactory.php`: cria equipe.
- `app/Domain/Identidade/{Support/Operacoes.php,Services/AutorizadorOperacao.php}`: `EQUIPE_GERENCIAR` e `MATRIZ`.
- `app/Domain/Identidade/{Contracts/ConsultarEquipeEbi.php,Services/ConsultaEquipeEbi.php}`: `escoposDeEquipeVigentes`.
- `app/Domain/Escalas/Contracts/ConsultarEquipeEscalada.php` + service, `Http/Controllers/Api/V1/SessaoController.php`, `Http/Resources/SessaoResource.php`: `escala_id`.
- `app/Providers/DomainServiceProvider.php`, `routes/api.php`.
- `database/migrations/2026_11_20_100002_create_escala_tables.php`.

### Frontend

| Arquivo | Ação |
|---|---|
| `src/api/escalas.ts` | + 12 funções (equipe, vaga, oferta) |
| `src/api/schema.d.ts` | gerado |
| `src/pages/EquipesEscala.tsx` + `.test.tsx` | novo |
| `src/pages/Escalas.tsx` + `.test.tsx` | montar pela equipe, vagas, painel |
| `src/pages/MinhasEscalas.tsx` + `.test.tsx` | "Vagas abertas" |
| `src/pages/Presenca.tsx` + `.test.tsx` | usa `sessao.escala_id` |
| `src/components/layout/ListaNav.tsx` + `.test.tsx` | item "Equipes de escala" |
| `src/App.tsx` | rota `equipes-escala` |
| `src/lib/formatos.ts` | rótulos de status da vaga |

### Documentação

`docs/06-arquitetura/decisoes/ADR-0034-...md` (novo), `ADR-0033-...md` (emendas), `docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md`, `docs/03-dados/{estados,modelo-conceitual}.md`, `docs/01-requisitos/funcionais/RF-07-escalas.md`, `docs/CHANGELOG.md`.

---

### Tarefa 0: ambiente de teste isolado do worktree

**Files:**
- Create (local, **sem commit**): `<worktree>/docker-compose.worktree.yml`
- Modify: nada no repositório

**Interfaces:**
- Produces: o comando padrão do backend (`docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec app ...`), usado por **todas** as tarefas seguintes.

- [x] **Step 1: Escrever o override local**

No worktree, criar `docker-compose.worktree.yml`:

```yaml
# Override do worktree escalas-equipes-e-vagas (LOCAL, nao versionado).
# So troca as portas do host para nao colidir com o ambiente principal (ebi-dev).
# `depends_on` do app inclui mailpit: ele precisa estar na lista do `up` e com
# porta trocada; se a 8025 do principal estiver tomada, o compose aborta.
# `!override` substitui a lista do arquivo base; sem ele o compose CONCATENA e
# a porta do principal continua reservada.
services:
  app:
    ports: !override ["8001:8000"]
  postgres:
    ports: !override ["5434:5432"]
  mailpit:
    ports: !override ["8026:8025"]
```

Antes do primeiro `up`: `backend/.env` é ignorado pelo git e **não existe** no worktree; copiar de `../../backend/.env` (os valores `DB_HOST=postgres` e `REDIS_HOST=redis` já apontam para os serviços do compose e servem iguais).

Conferir que o arquivo não entra no git: `git status --short` não deve listá-lo. Se listar, acrescentar `docker-compose.worktree.yml` a `.git/info/exclude`; **não** mexer no `.gitignore` versionado (e dentro do worktree `.git` é um arquivo, não uma pasta: usar `git rev-parse --git-path info/exclude` para achar o caminho real).

- [x] **Step 2: Subir e rodar a suíte de escalas intacta**

```powershell
docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml up -d app postgres mailpit redis
docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec -T app composer install --no-interaction
docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec app php artisan optimize:clear
docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec app php artisan test --filter="EscalaGestaoTest|TrocaEscalaTest"
```

Expected: PASS. O container precisa montar **o código do worktree**; conferir com `... exec app grep -c "PAPEIS_POR_FUNCAO" app/Domain/Escalas/Services/GerenciadorEscala.php` (valor ≥ 1), e não por caminho absoluto.

- [x] **Step 3: Registrar e seguir**

Nada a commitar. Registrar no ledger de progresso que o comando padrão funciona.

---

### Tarefa 1: Fundação — papéis por função num lugar só e `equipe.gerenciar`

**Files:**
- Modify: `backend/app/Domain/Escalas/Models/EscalaMembro.php` (constante pública `PAPEIS_POR_FUNCAO`)
- Modify: `backend/app/Domain/Escalas/Services/GerenciadorEscala.php` (remover a constante privada e o `use` de `EscalaMembro` que ficou órfão)
- Modify: `backend/app/Domain/Escalas/Services/AvaliadorEquipe.php` (remover a constante privada)
- Modify: `backend/app/Domain/Identidade/Support/Operacoes.php` (`EQUIPE_GERENCIAR`)
- Modify: `backend/app/Domain/Identidade/Services/AutorizadorOperacao.php` (`MATRIZ`)
- Test: `backend/tests/Feature/Escalas/PapeisPorFuncaoTest.php`

**Interfaces:**
- Produces:
  - `EscalaMembro::PAPEIS_POR_FUNCAO = ['coordenadora' => ['coordenadora','coordenadora_chefe'], 'auxiliar' => ['auxiliar','coordenadora','coordenadora_chefe']]`
  - `Operacoes::EQUIPE_GERENCIAR = 'equipe.gerenciar'`
  - `AutorizadorOperacao::MATRIZ['equipe.gerenciar']`: `coordenadora` (entra em `OPERACOES_COORDENADORA`, a chefe herda por superconjunto), `adm_igreja`.

- [x] **Step 1: Espelhar o teste que falha**

| Teste | Expectativa |
|---|---|
| `it('papeis por funcao fica num lugar so')` | `EscalaMembro::PAPEIS_POR_FUNCAO` tem as duas funções e os papéis exatos |
| `it('escala e avaliador usam a constante unica')` | `grep` no código não acha outra definição de `PAPEIS_POR_FUNCAO` fora de `EscalaMembro` |
| `it('equipe.gerenciar esta na matriz para coordenadora, chefe e adm_igreja')` | `permite()` verde para os três; vermelho para `auxiliar`, `ministerio_local` e `ministerio_central` |
| `it('superconjunto da chefe inclui coordenadora')` | `OPERACOES_COORDENADORA ⊆ OPERACOES_CHEFE` (teste existente de superconjunto continua verde com a nova operação) |

Reaproveitar o arnês existente de matriz (`tests/Feature/...` que já valida superconjunto); não duplicar.

- [x] **Step 2: Rodar e ver falhar**

Run: `docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec app php artisan test --filter=PapeisPorFuncaoTest`
Expected: FAIL — `Undefined constant App\Domain\Escalas\Models\EscalaMembro::PAPEIS_POR_FUNCAO` e `equipe.gerenciar` ausente.

- [x] **Step 3: Implementar**

- Mover o mapa para `EscalaMembro` (constante **pública**), atualizar os três usos (`GerenciadorEscala` em `garantirElegivel`, `vigenteNaData`, `funcaoValida`; `AvaliadorEquipe` em `vigenteEm`).
- `Operacoes` + `MATRIZ`, rodar `... exec app php artisan optimize:clear`.

- [x] **Step 4: Rodar e ver passar**

Run: `... exec app php artisan test --filter="PapeisPorFuncaoTest|EscalaGestaoTest|AvaliadorEquipeTest|AprovacaoEscalaTest"`
Expected: PASS.

- [x] **Step 5: Commit**

```powershell
git add backend/app/Domain/Escalas/Models/EscalaMembro.php backend/app/Domain/Escalas/Services/GerenciadorEscala.php backend/app/Domain/Escalas/Services/AvaliadorEquipe.php backend/app/Domain/Identidade/Support/Operacoes.php backend/app/Domain/Identidade/Services/AutorizadorOperacao.php backend/tests/Feature/Escalas/PapeisPorFuncaoTest.php
git commit -m "refactor(escalas): papeis por funcao numa constante unica e equipe.gerenciar na matriz"
```

---

### Tarefa 2: Schema da equipe e `escala.equipe_id` obrigatório

**Files:**
- Modify: `backend/database/migrations/2026_11_20_100002_create_escala_tables.php`
- Create: `backend/app/Domain/Escalas/Models/Equipe.php`, `backend/app/Domain/Escalas/Models/EquipeMembro.php`
- Create: `backend/app/Domain/Escalas/Database/Factories/{EquipeFactory,EquipeMembroFactory}.php`
- Modify: `backend/app/Domain/Escalas/Models/Escala.php` (`equipe_id` no `$fillable`, `equipe(): BelongsTo`)
- Modify: `backend/app/Domain/Escalas/Database/Factories/EscalaFactory.php`
- Modify: `backend/app/Domain/Escalas/Services/GerenciadorEscala.php` (`criar` recebe/grava `equipe_id`)
- Modify: `backend/app/Domain/Escalas/Contracts/GerirEscala.php` (`criar` com `?string $equipeId` no fim)
- Modify: `backend/app/Http/Controllers/Api/V1/EscalaController.php` (`equipe_id` do request)
- Modify: `backend/app/Http/Requests/Escalas/CriarEscalaRequest.php` (`equipe_id` nullable nesta tarefa; obrigatório na Task 8)
- Test: `backend/tests/Feature/Escalas/EquipeSchemaTest.php`

**Interfaces:**
- Produces:
  - `Equipe`: `$fillable ['ebi_id','igreja_id','coordenadora_id','criado_por']`, `membros(): HasMany`, constantes opcionais.
  - `EquipeMembro`: `$fillable ['equipe_id','usuario_id','incluido_por']`.
  - `EquipeFactory`: cria igreja + EBI + coordenadora vigente coerentes (usa `igrejaComEbi()` e `membroEscala('coordenadora','ebi',…)`).
  - `EscalaFactory::definition()` cria `Equipe::factory()` para o EBI e grava `equipe_id`.
  - `GerirEscala::criar(string $sessaoId, int $estimativa, array $membros, ?string $autorId, ?string $equipeId = null)`: `null` nesta tarefa → 422 `equipe_obrigatoria` (o erro final; a regra de dona vem na Task 8).

- [x] **Step 1: Escrever os testes que falham**

| Teste | Expectativa |
|---|---|
| `it('tabelas de equipe sobem com as FKs da decisao 1')` | apagar a equipe com membro → membro some (cascade); apagar um usuário **não** afeta `equipe_membro` (sem FK) |
| `it('banco recusa escala sem equipe_id')` | `DB::table('escala')->insert([...sem equipe_id...])` lança |
| `it('banco recusa equipe duplicada para a mesma coordenadora')` | segundo insert `(ebi_id, coordenadora_id)` iguais lança |
| `it('equipe com escala nao pode ser apagada')` | `restrictOnDelete`: delete da equipe usada lança |
| `it('EscalaFactory cria equipe e escala com equipe_id')` | `Escala::factory()->create()` tem `equipe_id` não nulo |
| `it('criar escala sem equipe responde 422 equipe_obrigatoria')` | POST `POST /sessions/{id}/schedules` sem `equipe_id` → 422 `equipe_obrigatoria` (temporário: antes a rota aceitava) |
| `it('criar escala com equipe grava equipe_id')` | POST com `equipe_id` → 201 e `escala.equipe_id` gravado |

Nasce com a base atual do E07, sem vaga ainda; usar `cenarioEscala()`.

- [x] **Step 2: Rodar e ver falhar**

Run: `... exec app php artisan test --filter=EquipeSchemaTest`
Expected: FAIL — `Class "App\Domain\Escalas\Models\Equipe" not found` e coluna `equipe_id` inexistente.

- [x] **Step 3: Implementar**

- Migration: `equipe` e `equipe_membro` **antes** de `escala` (ordem do arquivo), com `UNIQUE` e índices da decisão 1; `escala` ganha `equipe_id` `NOT NULL` `restrictOnDelete` indexado.
- Models e factories da interface.
- `GerenciadorEscala::criar` grava `equipe_id` e, nesta tarefa, valida só presença (não vazio → `equipe_obrigatoria`).
- Rodar `... exec app php artisan migrate:fresh --seed` no banco de desenvolvimento do container (ou `migrate` nos testes já cobre).

- [x] **Step 4: Rodar e ver passar**

Run: `... exec app php artisan test --filter="EquipeSchemaTest|EscalaGestaoTest|TrocaEscalaTest|PresencaEquipeTest|ConflitoEscalaTest|CoberturaEscalaTest|AprovacaoEscalaTest"`
Expected: PASS — a `EscalaFactory` absorve `equipe_id` nos testes existentes.

- [x] **Step 5: Commit**

```powershell
git add backend/database/migrations/2026_11_20_100002_create_escala_tables.php backend/app/Domain/Escalas/Models backend/app/Domain/Escalas/Database/Factories backend/app/Domain/Escalas/Services/GerenciadorEscala.php backend/app/Domain/Escalas/Contracts/GerirEscala.php backend/app/Http/Controllers/Api/V1/EscalaController.php backend/app/Http/Requests/Escalas/CriarEscalaRequest.php backend/tests/Feature/Escalas/EquipeSchemaTest.php
git commit -m "feat(escalas): schema da equipe e escala obrigatoriamente vinculada a uma equipe"
```

---

### Tarefa 3: Equipe — domínio e API

**Files:**
- Create: `backend/app/Domain/Escalas/Services/VinculoEquipe.php`
- Create: `backend/app/Domain/Escalas/Contracts/{GerirEquipe,ConsultarEquipeEscala,EquipeRef}.php`
- Create: `backend/app/Domain/Escalas/Services/{GerenciadorEquipe,ConsultaEquipeEscala}.php`
- Create: `backend/app/Http/Controllers/Api/V1/EquipeEscalaController.php`
- Create: `backend/app/Http/Requests/Escalas/{CriarEquipeRequest,IncluirMembroEquipeRequest}.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`, `backend/routes/api.php`
- Modify: `docs/04-api/openapi.yaml`, `docs/04-api/endpoints.md`, `frontend/src/api/schema.d.ts` (gerado)
- Test: `backend/tests/Feature/Escalas/EquipeEscalaTest.php`

**Interfaces:**
- Consumes: `ConsultarEquipeEbi::vigencias` / `nomes`, `AvaliadorEquipe::vigenteEm`, `ContextoDecisao`, `AutorizacaoEscala::garantir`.
- Produces:
  - `VinculoEquipe::vigenteHoje(string $usuarioId, string $ebiId, string $igrejaId, array $papeis): bool` — papel ∈ `$papeis`, `fim === null`, `substr(inicio,0,10) <= now()->toDateString()`.
  - `VinculoEquipe::elegivelNaData(string $usuarioId, string $ebiId, string $igrejaId, string $dataLocal, string $funcao): bool` — delega ao `AvaliadorEquipe::vigenteEm`.
  - `final readonly class EquipeRef(string $id, string $ebiId, string $igrejaId, string $coordenadoraId)` — espelha o `EscalaRef` existente (`Contracts/EscalaRef.php`), para o controller autorizar sem carregar modelo.
  - `GerirEquipe::criar(string $ebiId, string $igrejaId, string $coordenadoraId, ContextoDecisao $contexto, ?string $autorId): string`
  - `GerirEquipe::incluirMembro(string $equipeId, string $usuarioId, ContextoDecisao $contexto, ?string $autorId): void`
  - `GerirEquipe::retirarMembro(string $equipeId, string $usuarioId, ContextoDecisao $contexto, ?string $autorId): void`
  - `ConsultarEquipeEscala::referencia(string $equipeId): ?EquipeRef`
  - `ConsultarEquipeEscala::listarDoEbi(string $ebiId): list<array{id,ebi_id,coordenadora_id,coordenadora_nome,coordenadora_sem_vinculo:bool,quantidade_membros:int}>`
  - `ConsultarEquipeEscala::detalhe(string $equipeId): ?array{...,membros:list<array{usuario_id,nome,sem_vinculo:bool}>}`

| Método e rota | Nome | Operação | Resposta |
|---|---|---|---|
| `GET /ebis/{id}/equipes` | `equipes.index` | `escala.ler` | `{data:[...]}` |
| `POST /ebis/{id}/equipes` | `equipes.store` | `equipe.gerenciar` | 201 `{data:equipe}` |
| `GET /equipes/{id}` | `equipes.show` | `escala.ler` | `{data:equipe}` |
| `POST /equipes/{id}/members` | `equipes.members.store` | `equipe.gerenciar` | 200 `{data:equipe}` |
| `DELETE /equipes/{id}/members/{usuarioId}` | `equipes.members.destroy` | `equipe.gerenciar` | 200 `{data:equipe}` |

- [x] **Step 1: OpenAPI e `endpoints.md`**

Descrever as 5 rotas, os schemas `Equipe`, `EquipeResumo` e `MembroEquipe`, e os erros 403, 404, 409 e 422 desta tarefa. Depois: `cd frontend; npm run gen:api; cd ..`.

- [x] **Step 2: Escrever os testes que falham (`EquipeEscalaTest.php`)**

Base: `cenarioEscala()`, `authEscala()`, `escopoAprovacao()`. A equipe do cenário é criada explicitamente para `$c['coord']`. Os testes de criação usam `$nova = membroEscala('coordenadora','ebi',$c['ebi']->id)`.

| Teste | Expectativa |
|---|---|
| `it('coordenadora cria a propria equipe')` | POST `{coordenadora_id:$nova->id}` como `$nova` → 201; `data.coordenadora_id = $nova->id`; evento `equipe.criada` com `equipe_id` |
| `it('segunda equipe da mesma coordenadora responde 409')` | → 409 `equipe_existente` |
| `it('coordenadora nao cria equipe para outra')` | `$c['coord']` cria para `$nova` → 403 `nao_e_dona_da_equipe` |
| `it('chefe e adm_igreja criam para qualquer coordenadora vigente')` | dataset `[chefe, adm_igreja]` → 201 |
| `it('coordenadora alvo sem vinculo vigente responde 422')` | `fim` do vínculo = ontem → 422 `coordenadora_invalida` |
| `it('coordenadora com vinculo de igreja cria a propria equipe')` | **RF-2**: `membroEscala('coordenadora','igreja',$c['igreja']->id)` → 201 |
| `it('coordenadora inclui e retira na propria equipe')` | POST `{usuario_id:auxi}` → 200, 1 membro; DELETE → 200, 0; eventos `equipe.membro_incluido` e `equipe.membro_retirado` |
| `it('coordenadora nao mexe na equipe alheia')` | `$nova` inclui na equipe de `coord` → 403 `nao_e_dona_da_equipe` |
| `it('pessoa sem vinculo de equipe vigente hoje nao entra')` | `ministerio_local` → 422 `membro_inelegivel` |
| `it('vinculo com inicio futuro nao entra na equipe')` | **RF-3**: `inicio` = amanhã e `fim` nulo → 422 `membro_inelegivel` |
| `it('incluir de novo responde 409')` | → 409 `membro_ja_na_equipe` |
| `it('retirar quem nao e membro responde 404')` | → 404 `not_found` |
| `it('revogada aparece sem_vinculo')` | `fim` = ontem no vínculo da auxiliar → GET `data.membros.0.sem_vinculo = true` |
| `it('a mesma auxiliar fica em duas equipes')` | inclusão em duas equipes distintas → 200 nas duas |
| `it('leem equipes no territorio')` | dataset `ministerio_local`, `adm_igreja`, `adm_setor`, chefe, coord → GET 200 |
| `it('fora do territorio responde 404')` | coordenadora de outro EBI → 404 |
| `it('sem escala.ler recebe 403 na leitura')` | dataset `auxiliar`, `ministerio_central`, `ministerio_regional`, `ministerio_setorial` → 403 |
| `it('ministerio_local nao escreve')` | POST → 403 `sem_permissao` |
| `it('auditoria da equipe sai sem nomes')` | `metadados_minimizados` só com `equipe_id`, `ebi_id`, `coordenadora_id`, `usuario_id` |

- [x] **Step 3: Rodar e ver falhar**

Run: `... exec app php artisan test --filter=EquipeEscalaTest`
Expected: FAIL — rotas `/api/v1/ebis/{id}/equipes` respondem 404.

- [x] **Step 4: Implementar**

- `VinculoEquipe` e `GerenciadorEquipe` (ordem das guardas):
  - `criar`: papel `coordenadora` e `coordenadoraId !== contexto->usuarioId` → 403 `nao_e_dona_da_equipe`; `! vigenteHoje(coordenadora, …, PAPEIS_POR_FUNCAO['coordenadora'])` → 422 `coordenadora_invalida`; `DB::transaction` + evento `equipe.criada`; `UniqueConstraintViolationException` → 409 `equipe_existente`.
  - `incluirMembro`/`retirarMembro`: equipe inexistente → 404; não dona → 403; incluir exige `vigenteHoje(…, PAPEIS_POR_FUNCAO['auxiliar'])` senão 422 `membro_inelegivel`; unique → 409 `membro_ja_na_equipe`; retirar: delete físico, não membro → 404.
- `ConsultaEquipeEscala` com `sem_vinculo = ! vigenteHoje(...)` e nomes numa chamada `nomes()`.
- Controller: uma chamada de `garantir` por rota (a assinatura recebe **uma** operação, como no `EscalaController`): GETs com `Operacoes::ESCALA_LER`; POST/DELETE com `Operacoes::EQUIPE_GERENCIAR`. Para `/equipes/{id}`, o `ebiId` vem de `referencia($id)` (`null` → 404). O `igrejaId` necessário à elegibilidade vem do `EbiRef` do `ConsultarEbi` (o mesmo caminho de `GerenciadorEscala::ebi()`), nunca do corpo da requisição.
- Bindings no `DomainServiceProvider`; rotas: GETs fora do grupo de idempotência, POST/DELETE dentro.
- `... exec app php artisan optimize:clear`.

- [x] **Step 5: Rodar e ver passar**

Run: `... exec app php artisan test --filter="EquipeEscalaTest|EquipeEbiTest"`
Expected: PASS.

- [x] **Step 6: Commit**

```powershell
git add backend/app/Domain/Escalas/Services/VinculoEquipe.php backend/app/Domain/Escalas/Contracts/GerirEquipe.php backend/app/Domain/Escalas/Contracts/ConsultarEquipeEscala.php backend/app/Domain/Escalas/Contracts/EquipeRef.php backend/app/Domain/Escalas/Services/GerenciadorEquipe.php backend/app/Domain/Escalas/Services/ConsultaEquipeEscala.php backend/app/Http/Controllers/Api/V1/EquipeEscalaController.php backend/app/Http/Requests/Escalas/CriarEquipeRequest.php backend/app/Http/Requests/Escalas/IncluirMembroEquipeRequest.php backend/app/Providers/DomainServiceProvider.php backend/routes/api.php backend/tests/Feature/Escalas/EquipeEscalaTest.php docs/04-api/openapi.yaml docs/04-api/endpoints.md frontend/src/api/schema.d.ts
git commit -m "feat(escalas): equipe da coordenadora com dominio e API"
```

---

### Tarefa 4: Acréscimo preserva a aprovação

> Antecipada em relação à ordem da spec porque a **vaga preenchida** (Task 8) também é acréscimo: o mesmo ponto do código serve para as duas.

**Files:**
- Modify: `backend/app/Domain/Escalas/Services/GerenciadorEscala.php` (`salvarAlteracao` ~:298; `incluirMembro` ~:114)
- Create: `backend/app/Domain/Escalas/Services/CancelamentoTroca.php` (extraído de `salvarAlteracao`)
- Test: `backend/tests/Feature/Escalas/AcrescimoEscalaTest.php`

**Interfaces:**
- Produces:
  - `GerenciadorEscala::salvarAlteracao(Escala $escala, IntervaloSessao $sessao, ?string $autorId, bool $acrescimo = false): Escala` (**privado**)
  - `CancelamentoTroca::cancelarPendentes(string $escalaId, ?string $membroId, string $motivoDecisao, ?string $autorId): int` — cancela trocas `pendente` (todas, ou só as do membro), registra `escala.troca_cancelada` e devolve a quantidade.
- Consumes: `cenarioAprovacao()` e `cenarioSemChefe()` de `AprovacaoEscalaTest.php`.

- [x] **Step 1: Escrever os testes que falham**

| Teste | Expectativa |
|---|---|
| `it('incluir membro em escala aprovada mantem a aprovacao inteira')` | divisor do EBI muda de 4 para 2 depois da aprovação; incluir → `status = aprovada`, `aprovado_por` e `aprovado_em` iguais, `participantes_por_auxiliar = 4`; evento `escala.alterada` com `voltou_prevista = false` |
| `it('incluir nao cancela troca pendente')` | troca `pendente` continua `pendente` |
| `it('membro incluido entra escalado')` | `status = escalado` |
| `it('retirar, mudar funcao e estimativa voltam a prevista')` | dataset dos 3 → `prevista`, `aprovado_*` nulos, divisor recopiado (2), troca pendente `cancelada` (regressão do E07) |
| `it('incluir com sessao encerrada responde 409')` | 409 `sessao_em_estado_invalido` |

- [x] **Step 2: Rodar e ver falhar**

Run: `... exec app php artisan test --filter=AcrescimoEscalaTest`
Expected: FAIL no primeiro teste, com `Expected 'prevista' to be 'aprovada'`.

- [x] **Step 3: Implementar**

- `salvarAlteracao(..., bool $acrescimo = false)`: quando `true`, **não** toca em `status`, `aprovado_por/em` nem `participantes_por_auxiliar`, **não** cancela trocas; evento `escala.alterada` sai com `voltou_prevista = false`.
- `incluirMembro` chama com `acrescimo: true`.
- O trecho que cancela trocas migra para `CancelamentoTroca::cancelarPendentes($escalaId, null, 'escala alterada', $autorId)` sem mudar comportamento; binding no `DomainServiceProvider`.

- [x] **Step 4: Rodar e ver passar**

Run: `... exec app php artisan test --filter="AcrescimoEscalaTest|EscalaGestaoTest|AprovacaoEscalaTest|TrocaEscalaTest"`
Expected: PASS.

- [x] **Step 5: Commit**

```powershell
git add backend/app/Domain/Escalas/Services/GerenciadorEscala.php backend/app/Domain/Escalas/Services/CancelamentoTroca.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Escalas/AcrescimoEscalaTest.php
git commit -m "feat(escalas): incluir membro preserva a aprovacao da escala"
```

---

### Tarefa 5: Fundação da fundação — o frontend passa a receber `equipe_id`

> Esta tarefa existe para o frontend **não quebrar esperando a Task 8**: a rota já exige `equipe_id` (Task 2), mas a montagem pela equipe só chega na Task 13. Aqui o mínimo é feito para a tela continuar enviando o campo (vazio até a Task 13). Esta é a **única tarefa que deixa um teste vermelho**, e o motivo é o contrato que ainda não existe.

**Files:**
- Modify: `frontend/src/api/escalas.ts` (`criarEscala` aceita `equipe_id: string`)
- Modify: `frontend/src/pages/Escalas.tsx` (envia `equipe_id: ''` — **provisório**, marcado `// TODO(Tarefa 13): substituir pela equipe real`)
- Modify: `frontend/src/pages/Escalas.test.tsx` **um** teste fica **vermelho de propósito**
- Test: `frontend/src/pages/Escalas.test.tsx`

- [x] **Step 1: Ajustar a tela e o teste**

- O teste `montar pre-seleciona a dona e os membros vigentes da equipe` é escrito **agora**, com a expectativa final (`criarEscala` recebe o `equipe_id` real da equipe da dona), e fica **vermelho de propósito**. `it.skip` **não** é permitido: o teste tem de rodar e falhar, para não virar esquecimento. No arquivo, ao lado do teste: `// Fixado pela Task 13.`
- Na tela, enviar `equipe_id: ''` com o mesmo marcador `// TODO(Tarefa 13): montar pela equipe`.

- [x] **Step 2: Rodar e ver falhar**

Run: `cd frontend; npx vitest run src/pages/Escalas.test.tsx; cd ..`
Expected: FAIL só no teste fixado; os demais passam.

- [x] **Step 3: Commit**

```powershell
git add frontend/src/api/escalas.ts frontend/src/pages/Escalas.tsx frontend/src/pages/Escalas.test.tsx
git commit -m "chore(escalas): tela de escalas envia equipe_id provisorio ate a montagem pela equipe"
```

---

### Tarefa 6: Schema da vaga e `EncerradorVaga` integrado

**Files:**
- Create: `backend/database/migrations/2026_11_25_100000_create_vaga_escala_tables.php`
- Create: `backend/app/Domain/Escalas/Models/{VagaEscala,OfertaVagaEscala}.php` + factories
- Create: `backend/app/Domain/Escalas/Services/EncerradorVaga.php`
- Modify: `backend/app/Domain/Escalas/Models/Escala.php` (`vagas(): HasMany`)
- Modify: `backend/app/Domain/Escalas/Services/GerenciadorEscala.php` (`retirarMembro`, `mudarFuncao`)
- Modify: `backend/app/Domain/Escalas/Services/GerenciadorTrocaEscala.php` (`aprovar` ~:253)
- Modify: `backend/app/Providers/DomainServiceProvider.php` (binding)
- Test: `backend/tests/Feature/Escalas/VagaSchemaTest.php`

**Interfaces:**
- Produces:
  - `VagaEscala`: `STATUS_ABERTA`, `STATUS_PREENCHIDA`, `STATUS_CANCELADA`; `MOTIVO_GESTAO='cancelada_pela_gestao'`, `MOTIVO_RETIRADO='membro_retirado'`, `MOTIVO_FUNCAO='funcao_alterada'`, `MOTIVO_TROCADO='membro_trocado'`, `MOTIVO_INDISPONIVEL='original_indisponivel'`; `escala()`, `ofertas()`.
  - `OfertaVagaEscala`: `STATUS_PENDENTE`, `STATUS_ACEITA`, `STATUS_RETIRADA`, `STATUS_DESCARTADA`.
  - `EncerradorVaga::cancelar(VagaEscala $vaga, string $motivo, ?string $autorId): void`; `EncerradorVaga::cancelarAbertasDoMembro(string $membroId, string $motivo, ?string $autorId): int`. O chamador já está dentro da `TravaEscala`; o encerrador não abre trava.

- [x] **Step 1: Escrever os testes que falham**

| Teste | Expectativa |
|---|---|
| `it('tabelas de vaga sobem com as FKs da decisao 1')` | apagar a escala apaga vagas e ofertas (cascade); apagar o membro substituído → `substitui_membro_id = null`, `substituido_usuario_id` mantido; apagar o membro criado → `membro_criado_id = null` e a oferta continua `aceita` |
| `it('retirar membro cancela a vaga aberta dele antes do delete')` | vaga `cancelada` (`membro_retirado`); oferta `descartada`; `substituido_usuario_id` mantido |
| `it('mudar funcao cancela a vaga aberta')` | `funcao_alterada` |
| `it('troca aprovada cancela a vaga aberta do original')` | `membro_trocado` |
| `it('alteracao que volta a prevista nao cancela vaga')` | PATCH estimativa → vaga continua `aberta` |
| `it('evento de cancelamento leva so ids e motivo')` | metadados sem `observacao` |

- [x] **Step 2: Rodar e ver falhar**

Run: `... exec app php artisan test --filter=VagaSchemaTest`
Expected: FAIL — `Class "App\Domain\Escalas\Models\VagaEscala" not found`.

- [x] **Step 3: Implementar**

- Migration da decisão 1 (`observacao` `string(280)` nullable; `funcao` default `'auxiliar'`; índices `(escala_id,status)`, `(vaga_id,status)`, `(usuario_id,status)`).
- Integrações: `retirarMembro` → `cancelarAbertasDoMembro($id, MOTIVO_RETIRADO, …)` **antes** do delete; `mudarFuncao` → `MOTIVO_FUNCAO`; `GerenciadorTrocaEscala::aprovar` → `MOTIVO_TROCADO`, logo depois de marcar o original como `substituido`.
- `... exec app php artisan migrate`.

- [x] **Step 4: Rodar e ver passar**

Run: `... exec app php artisan test --filter="VagaSchemaTest|TrocaEscalaTest|EscalaGestaoTest"`
Expected: PASS.

- [x] **Step 5: Commit**

```powershell
git add backend/database/migrations/2026_11_25_100000_create_vaga_escala_tables.php backend/app/Domain/Escalas/Models backend/app/Domain/Escalas/Database/Factories backend/app/Domain/Escalas/Services/EncerradorVaga.php backend/app/Domain/Escalas/Services/GerenciadorEscala.php backend/app/Domain/Escalas/Services/GerenciadorTrocaEscala.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Escalas/VagaSchemaTest.php
git commit -m "feat(escalas): tabelas de vaga e cancelamento automatico na retirada, funcao e troca"
```

---

### Tarefa 7: Escala nasce com a equipe e só a dona escreve

**Files:**
- Create: `backend/app/Domain/Escalas/Services/DonaDaEscala.php`
- Modify: `backend/app/Domain/Escalas/Services/GerenciadorEscala.php` (`criar` com equipe; escritas com `ContextoDecisao`)
- Modify: `backend/app/Domain/Escalas/Contracts/GerirEscala.php` (`criar(..., string $equipeId, ContextoDecisao $contexto, ?string $autorId)`; escritas com `ContextoDecisao`)
- Modify: `backend/app/Http/Requests/Escalas/CriarEscalaRequest.php` (`equipe_id` `required`)
- Modify: `backend/app/Domain/Escalas/Services/ConsultaEscala.php` (`detalheDaEscala`)
- Modify: `backend/app/Domain/Escalas/Contracts/EscalaVisao.php` e `backend/app/Http/Support/AutorizacaoEscala.php` (`visao` carrega o contexto)
- Modify: `backend/app/Http/Controllers/Api/V1/{EscalaController,EscalaMembroController}.php`
- Modify: `docs/04-api/openapi.yaml`, `docs/04-api/endpoints.md`, `frontend/src/api/schema.d.ts`
- Modify: testes do E07 que escrevem como ator que não é a dona
- Test: `backend/tests/Feature/Escalas/DonaDaEscalaTest.php`

**Interfaces:**
- Consumes: `VinculoEquipe::vigenteHoje`, `ConsultarEquipeEscala::referencia`, `EscalaMembro::PAPEIS_POR_FUNCAO`, `ContextoDecisao(usuarioId, papelCodigo, tipoEscopo, escopoId)`.
- Produces:
  - `DonaDaEscala::donaId(string $escalaId): string`
  - `DonaDaEscala::podeGerir(string $escalaId, ContextoDecisao $ctx): bool` — chefe ou `adm_igreja` → `true`; `coordenadora` → `usuarioId === dona`; demais → `false`.
  - `DonaDaEscala::garantirGerir(string $escalaId, ContextoDecisao $ctx): void` — 403 `nao_e_dona_da_escala` com `extra ['dona_id' => …]`.
  - `DonaDaEscala::podeEscolherOferta(string $escalaId, ContextoDecisao $ctx): bool` — `usuarioId === dona`, `papelCodigo ∈ PAPEIS_POR_FUNCAO['coordenadora']` e `vigenteHoje(dona, …, PAPEIS_POR_FUNCAO['coordenadora'])` (RF-1).
  - `DonaDaEscala::garantirEscolher(string $escalaId, ContextoDecisao $ctx): void`
  - `EscalaVisao` ganha `?ContextoDecisao $contexto = null` e `bool $temEscalaLer = false`; `::interna()` continua igual.
  - Detalhe da escala: `equipe: { id, coordenadora_id, coordenadora_nome }`, `pode_gerir: bool`, `pode_escolher_oferta: bool` (falsos sem contexto).

- [x] **Step 1: OpenAPI**

- `Escala`: `equipe`, `pode_gerir`, `pode_escolher_oferta`; `equipe_id` `required` em `POST /sessions/{id}/schedules`; escritas da escala ganham 403 `nao_e_dona_da_escala`. Depois `npm run gen:api`.

- [x] **Step 2: Escrever os testes que falham (`DonaDaEscalaTest.php`)**

| Teste | Expectativa |
|---|---|
| `it('coordenadora cria escala so com a propria equipe')` | `$nova` cria com equipe de `$c['coord']` → 422 `equipe_invalida` |
| `it('chefe cria com qualquer equipe do EBI')` | → 201 |
| `it('equipe de outro EBI responde 422')` | → 422 `equipe_invalida` |
| `it('equipe com dona sem vinculo vigente responde 422')` | `fim` da dona = ontem → 422 `equipe_invalida` |
| `it('PATCH ignora equipe_id')` | equipe da escala não muda após PATCH |
| `it('coordenadora nao dona nao escreve')` | dataset PATCH estimativa, POST membro, DELETE membro, PATCH função, PUT presença → 403 `nao_e_dona_da_escala`, com `dona_id` |
| `it('chefe e adm_igreja escrevem em qualquer escala do EBI')` | → 2xx |
| `it('a dona atuando como auxiliar nao escreve na propria escala')` | **RF-1**: dona com segundo vínculo `auxiliar`, atuando no escopo auxiliar → 403 (a matriz já recusa `escala.gerenciar` ao auxiliar, antes do serviço; verificar **qual** código retorna e fixá-lo no teste) |
| `it('a chefe que e dona da equipe pode escolher oferta')` | **RF-1**: `podeEscolherOferta` `true` para a chefe dona, **atuando como chefe** |
| `it('a dona atuando como auxiliar nao escolhe oferta')` | **RF-1**: mesma pessoa, atuando no vínculo `auxiliar` → `podeEscolherOferta` `false` (a atuação é lida do `ContextoDecisao`, nunca dos vínculos da pessoa) |
| `it('chefe nao dona e adm_igreja nao escolhem oferta')` | `podeEscolherOferta` `false` |
| `it('dona revogada nao escolhe')` | `fim` = hoje → `false` |
| `it('detalhe traz equipe, pode_gerir e pode_escolher_oferta')` | como dona → `true`/`true`; como `ministerio_local` → `false`/`false`; `equipe.coordenadora_nome` preenchido |
| `it('escala.criada leva equipe_id')` | metadados |

- [x] **Step 3: Rodar e ver falhar**

Run: `... exec app php artisan test --filter=DonaDaEscalaTest`
Expected: FAIL — `Class "App\Domain\Escalas\Services\DonaDaEscala" not found`; escritas da não dona respondem 2xx.

- [x] **Step 4: Implementar**

- `criar`: `equipe_id` ausente → 422 `equipe_obrigatoria`; `coordenadora` com equipe de outra → 422 `equipe_invalida`; equipe de outro EBI → 422; dona sem vínculo vigente → 422 `equipe_invalida`. Grava `equipe_id` e o evento leva `equipe_id`.
- Cada escrita da escala chama `garantirGerir` **dentro** da `TravaEscala`, antes das demais guardas.
- Controllers: `autorizacao->contexto($request)` em todas as escritas; `AutorizacaoEscala::visao` preenche `contexto` e `temEscalaLer`; `detalheDaEscala` calcula os booleanos.
- Testes do E07: atores que escrevem e não são a dona passam a ser `$c['coord']` ou a chefe. Achá-los com `--filter="EscalaGestaoTest|PresencaEquipeTest|ConflitoEscalaTest|AprovacaoEscalaTest"`.

- [x] **Step 5: Rodar e ver passar**

Run: `... exec app composer test`
Expected: PASS na suíte inteira.

- [x] **Step 6: Commit**

```powershell
git add backend/app/Domain/Escalas backend/app/Http backend/tests/Feature docs/04-api/openapi.yaml docs/04-api/endpoints.md frontend/src/api/schema.d.ts
git commit -m "feat(escalas): escala nasce com equipe e so a dona escreve"
```

---

### Tarefa 8: Domínio da vaga e da oferta

**Files:**
- Create: `backend/app/Domain/Escalas/Contracts/GerirVagaEscala.php`
- Create: `backend/app/Domain/Escalas/Services/{GerenciadorVaga,ResultadoVaga}.php`
- Modify: `backend/app/Providers/DomainServiceProvider.php`
- Test: `backend/tests/Feature/Escalas/VagaDominioTest.php`

**Interfaces:**
- Consumes: `TravaEscala`, `DonaDaEscala`, `VinculoEquipe`, `EncerradorVaga`, `CancelamentoTroca`, `DetectorConflito::conflitos(array $usuarioIds, IntervaloSessao $sessao, string $escalaIdIgnorada): array`, `ConsultarSessao`.
- Produces:
  - `GerirVagaEscala::abrir(string $escalaId, ?string $substituiMembroId, ?string $observacao, ContextoDecisao $ctx, ?string $autorId): string`
  - `GerirVagaEscala::cancelar(string $vagaId, ContextoDecisao $ctx, ?string $autorId): void`
  - `GerirVagaEscala::oferecer(string $vagaId, string $usuarioId): string`
  - `GerirVagaEscala::retirarOferta(string $ofertaId, string $usuarioId): void`
  - `GerirVagaEscala::escolher(string $ofertaId, ContextoDecisao $ctx, ?string $autorId): string` — devolve o id do membro criado
  - `final readonly class ResultadoVaga(?string $id, bool $indisponivel)`, com `::ok()`/`::indisponivel()`

- [x] **Step 1: Escrever os testes que falham**

| Teste | Expectativa |
|---|---|
| `it('abre acrescimo e substituicao')` | duas vagas `aberta`; a substituição grava `substituido_usuario_id`; evento `escala.vaga_aberta` |
| `it('substituicao de membro coordenadora responde 422')` | `membro_invalido` |
| `it('segunda vaga para o mesmo membro responde 409')` | `vaga_existente` |
| `it('abrir com sessao encerrada responde 409')` | `sessao_em_estado_invalido` |
| `it('abrir com dona sem vinculo responde 409')` | `dona_sem_vinculo` |
| `it('oferecer: fora do EBI responde 404')` | `not_found` |
| `it('oferecer: vaga cancelada responde 409')` | `vaga_indisponivel` |
| `it('oferecer: membro ativo e a dona recebem 422')` | `membro_ja_escalado` |
| `it('oferecer com conflito de horario responde 422')` | `conflito_de_horario` com `conflitos` |
| `it('segunda oferta pendente responde 409')` | `oferta_existente` |
| `it('vinculo de igreja vale para oferecer')` | **RF-2** → oferta criada |
| `it('vinculo com inicio futuro nao autoriza oferecer')` | **RF-3** → 404 |
| `it('elegibilidade usa a data local da sessao')` | **RF-3**: sessão 2026-12-06T02:30Z (dia 05/12 local); vínculo com `fim = 2026-12-05` → elegível; outro com `fim = 2026-12-04` → 422 `membro_inelegivel` |
| `it('retira a propria oferta')` | `retirada`; de outra pessoa → 404; já `aceita` → 409 `oferta_ja_decidida` |
| `it('escolher cria membro confirmado e preenche a vaga')` | membro `auxiliar`, `confirmado`, com `confirmado_em`; vaga `preenchida`; oferta `aceita` com `membro_criado_id`; demais `descartada`; aprovação intacta; evento `escala.vaga_preenchida` |
| `it('substituicao preenchida marca o original e cancela a troca')` | original `substituido`; novo com `substitui_membro_id`; troca `cancelada` com `motivo_decisao = 'vaga preenchida'` |
| `it('chefe e adm_igreja nao escolhem')` | 403 `nao_e_dona_da_escala` |
| `it('original indisponivel cancela a vaga e responde 409')` | original posto como `substituido` por update direto → 409 `vaga_indisponivel` **e** vaga persistida `cancelada` (`original_indisponivel`), ofertas `descartada` |
| `it('oferta de quem ja entrou na escala nao pode ser escolhida')` | **RF-5**: inclusão manual → escolher 422 `membro_ja_escalado`; nenhum membro novo; oferta segue `pendente` |
| `it('cancelar vaga descarta ofertas')` | `cancelada_pela_gestao`; vaga já `preenchida` → 409 `vaga_indisponivel` |

- [x] **Step 2: Rodar e ver falhar**

Run: `... exec app php artisan test --filter=VagaDominioTest`
Expected: FAIL — `Target [App\Domain\Escalas\Contracts\GerirVagaEscala] is not instantiable`.

- [x] **Step 3: Implementar**

- Todos os métodos em `TravaEscala::executar($escalaId, fn)`; vaga e oferta relidas depois do lock; guardas na ordem da decisão 5.
- `escolher`: original indisponível → `EncerradorVaga::cancelar(…, MOTIVO_INDISPONIVEL)` e callback devolve `ResultadoVaga::indisponivel()`; **depois** do `executar`, `throw new ProblemException('vaga_indisponivel', …, 409, …)`.
- Conflito via `DetectorConflito::conflitos([$u], $intervalo, $escalaId)`; ofertas pendentes não contam.
- Eventos: `escala.vaga_aberta`, `escala.vaga_cancelada`, `escala.vaga_oferta`, `escala.vaga_oferta_retirada`, `escala.vaga_preenchida` (ids apenas). Binding `GerirVagaEscala` → `GerenciadorVaga`.

- [x] **Step 4: Rodar e ver passar**

Run: `... exec app php artisan test --filter=VagaDominioTest`
Expected: PASS.

- [x] **Step 5: Commit**

```powershell
git add backend/app/Domain/Escalas/Contracts/GerirVagaEscala.php backend/app/Domain/Escalas/Services/GerenciadorVaga.php backend/app/Domain/Escalas/Services/ResultadoVaga.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Escalas/VagaDominioTest.php
git commit -m "feat(escalas): ciclo da vaga e da oferta com escolha pela dona"
```

---

### Tarefa 9: API da vaga, leitura e privacidade

**Files:**
- Create: `backend/app/Domain/Escalas/Contracts/{ConsultarVagaEscala,ProjecaoVaga}.php`, `backend/app/Domain/Escalas/Services/ConsultaVagaEscala.php`
- Create: `backend/app/Http/Controllers/Api/V1/{VagaEscalaController,OfertaVagaController,MinhasVagasController}.php`, `backend/app/Http/Requests/Escalas/AbrirVagaRequest.php`
- Modify: `backend/app/Domain/Identidade/{Contracts/ConsultarEquipeEbi.php,Services/ConsultaEquipeEbi.php}`
- Modify: `backend/app/Domain/Escalas/Services/ConsultaEscala.php` (`vagas` no detalhe)
- Modify: `backend/routes/api.php`, `backend/app/Providers/DomainServiceProvider.php`
- Modify: `docs/04-api/openapi.yaml`, `docs/04-api/endpoints.md`, `frontend/src/api/schema.d.ts`
- Test: `backend/tests/Feature/Escalas/VagaApiTest.php`

**Interfaces:**
- Produces:
  - `enum ProjecaoVaga { case Publica; case Leitor; case Gestao; }`
  - `ConsultarVagaEscala::doEbi(string $ebiId, ?string $inicio, ?string $fim, ?string $status, ProjecaoVaga $p): list<array>`
  - `ConsultarVagaEscala::daEscala(string $escalaId, ProjecaoVaga $p, ?string $leitorId): list<array>`
  - `ConsultarVagaEscala::minhas(string $usuarioId, string $inicio, string $fim): list<array>`
  - `ConsultarEquipeEbi::escoposDeEquipeVigentes(string $usuarioId): list<array{ebi_id:?string, igreja_id:string}>`
  - Formato da vaga: `{id, escala_id, sessao:{id,inicio_previsto,fim_previsto}, ebi_id, ebi_nome, dona_nome, observacao, tipo:'acrescimo'|'substituicao', status, expirada:bool}`; Leitor + `substituido_nome`, `ofertas_pendentes:int`, `escolhido_nome`, `motivo_encerramento`; Gestão + `ofertas:list<{id,usuario_id,nome,status,conflitos}>`; Pública + `minha_oferta:{id,status}|null`, `conflitos`.

| Rota | Nome | Status |
|---|---|---|
| `POST /schedules/{id}/openings` | `schedules.openings.store` | 201 |
| `POST /schedule-openings/{id}/cancel` | `schedule-openings.cancel` | 200 |
| `GET /ebis/{id}/schedule-openings` | `ebis.schedule-openings.index` | 200 |
| `GET /me/schedule-openings` | `me.schedule-openings` | 200 (teto 92 dias; acima → 422) |
| `POST /schedule-openings/{id}/offers` | `schedule-openings.offers.store` | 201 |
| `POST /schedule-offers/{id}/withdraw` | `schedule-offers.withdraw` | 200 |
| `POST /schedule-offers/{id}/accept` | `schedule-offers.accept` | 200 |

- [x] **Step 1: OpenAPI e `endpoints.md`** com as 7 rotas e os schemas `VagaPublica`, `VagaLeitor` e `VagaGestao`. Depois `npm run gen:api`.

- [x] **Step 2: Escrever os testes que falham**

| Teste | Expectativa |
|---|---|
| `it('dona abre, auxiliar oferece, dona escolhe pela API')` | 201, 201, 200; `GET /schedules/{id}` mostra a vaga `preenchida` |
| `it('auxiliar e coordenadora do EBI veem a vaga em /me')` | o item aparece para as duas |
| `it('quem ja e membro ativo nao ve a vaga em /me')` | **RF-5** |
| `it('/me nao mostra vaga expirada nem de EBI sem vinculo')` | sessão `encerrada` → fora; outro EBI → fora |
| `it('/me sem substituido e sem nomes de ofertas')` | chaves `substituido_nome` e `ofertas` ausentes |
| `it('leitor sem gestao ve contagem e escolhido, sem nomes')` | dataset `ministerio_local`, `adm_setor`, `adm_regional`, `super_admin`, coordenadora não dona: `ofertas_pendentes` presente, `ofertas` ausente |
| `it('dona, chefe e adm_igreja veem nomes das ofertas')` | `ofertas.0.nome` |
| `it('membro pela excecao de propriedade recebe projecao publica')` | auxiliar membro, `GET /schedules/{id}` → vagas sem `ofertas` nem `substituido_nome` |
| `it('painel do EBI filtra por status e periodo')` | `?status=aberta` |
| `it('auxiliar recebe 403 no painel')` | GET `/ebis/{id}/schedule-openings` → 403 |
| `it('/me acima de 92 dias responde 422')` | 422 |
| `it('repetir oferta e aceite com a mesma Idempotency-Key nao duplica')` | **RF-4**: dois POSTs iguais → mesma resposta; uma oferta e um membro |
| `it('observacao nao entra na auditoria')` | metadados sem `observacao` |

- [x] **Step 3: Rodar e ver falhar**

Run: `... exec app php artisan test --filter=VagaApiTest`
Expected: FAIL — 404 nas rotas novas.

- [x] **Step 4: Implementar**

- Projeção: `Gestao` se `DonaDaEscala::podeGerir`; `Leitor` se a visão tem `temEscalaLer`; `Publica` nos demais.
- `expirada` = vaga `aberta` com a sessão fora de `prevista`/`aberta`. Nomes numa `nomes()` por resposta.
- `MinhasVagasController` usa `escoposDeEquipeVigentes()` e exclui escalas em que o usuário é membro ativo.

- [x] **Step 5: Rodar e ver passar**

Run: `... exec app composer test`
Expected: PASS.

- [x] **Step 6: Commit**

```powershell
git add backend/app/Domain/Escalas backend/app/Domain/Identidade/Contracts/ConsultarEquipeEbi.php backend/app/Domain/Identidade/Services/ConsultaEquipeEbi.php backend/app/Http backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Escalas/VagaApiTest.php docs/04-api/openapi.yaml docs/04-api/endpoints.md frontend/src/api/schema.d.ts
git commit -m "feat(escalas): API de vagas e ofertas com projecao de privacidade"
```

---

### Tarefa 10: Correção da tela de Presença

**Files:**
- Modify: `backend/app/Domain/Escalas/Contracts/ConsultarEquipeEscalada.php`, `backend/app/Domain/Escalas/Services/ConsultaEquipeEscalada.php`
- Modify: `backend/app/Http/Controllers/Api/V1/SessaoController.php` (`show` ~:52), `backend/app/Http/Resources/SessaoResource.php`
- Modify: `docs/04-api/openapi.yaml`, `docs/04-api/endpoints.md`, `frontend/src/api/schema.d.ts`
- Modify: `frontend/src/pages/Presenca.tsx` (~:60, ~:108-112, ~:206-208), `frontend/src/pages/Presenca.test.tsx`
- Test: `backend/tests/Feature/Escalas/PresencaEscalaIdTest.php`

**Interfaces:**
- Produces:
  - `ConsultarEquipeEscalada::escalaDaSessao(string $sessaoId): ?string`
  - `SessaoResource` com `?string $escalaId = null` → `escala_id`
  - `Presenca.tsx` busca a escala por `sessao.escala_id`; bloco da equipe por `escala.pode_gerir`.

- [x] **Step 1: OpenAPI** (`Sessao.escala_id: string|null`) e depois `npm run gen:api`.

- [x] **Step 2: Escrever os testes que falham**

Backend:

| Teste | Expectativa |
|---|---|
| `it('GET /sessions/{id} traz escala_id')` | igual ao id da escala, com ids distintos |
| `it('sessao sem escala traz escala_id null')` | `null` |

Vitest (`Presenca.test.tsx`):

| Teste | Expectativa |
|---|---|
| `it('busca a escala por sessao.escala_id')` | `obterEscala` chamado com `'esc1'`, não com o id da sessão |
| `it('sem escala_id nao mostra o bloco da equipe')` | |
| `it('leitora nao dona ve a equipe so em leitura')` | `pode_gerir: false` → sem botão de salvar |
| `it('recarrega a equipe apos 422 membro_invalido mantendo marcacoes')` | |

- [x] **Step 3: Rodar e ver falhar**

Run: `... exec app php artisan test --filter=PresencaEscalaIdTest`
Expected: FAIL — `Unable to find JSON path [escala_id]`.
Run: `cd frontend; npx vitest run src/pages/Presenca.test.tsx; cd ..`
Expected: FAIL — `obterEscala` chamado com o id da sessão.

- [x] **Step 4: Implementar**

- `escalaDaSessao`: `Escala::query()->where('sessao_id', $id)->value('id')`; `SessaoController::show` passa ao resource.
- `Presenca.tsx`: troca o `podeGerenciar` por papéis pela `escala.pode_gerir`; recarrega a equipe ao abrir, depois de encerrar a sessão e no `422 membro_invalido`, mantendo os ids marcados que continuam na equipe; lista só os ativos.

- [x] **Step 5: Rodar e ver passar**

Os mesmos dois comandos do Step 3, agora PASS.

- [x] **Step 6: Commit**

```powershell
git add backend/app/Domain/Escalas/Contracts/ConsultarEquipeEscalada.php backend/app/Domain/Escalas/Services/ConsultaEquipeEscalada.php backend/app/Http/Controllers/Api/V1/SessaoController.php backend/app/Http/Resources/SessaoResource.php backend/tests/Feature/Escalas/PresencaEscalaIdTest.php docs/04-api/openapi.yaml docs/04-api/endpoints.md frontend/src/api/schema.d.ts frontend/src/pages/Presenca.tsx frontend/src/pages/Presenca.test.tsx
git commit -m "fix(escalas): presenca acha a escala por escala_id da sessao"
```

---

### Tarefa 11: Concorrência em PostgreSQL

**Files:**
- Create: `backend/tests/Concorrencia/VagaConcorrenciaTest.php`, `backend/tests/Concorrencia/Suporte.php`, `backend/tests/Concorrencia/bin/executar.php`
- Modify: `backend/phpunit.xml` (testsuite `Concorrencia` com `tests/Concorrencia`; a testsuite padrão **não** inclui o diretório)
- Modify: `backend/tests/Pest.php` (`pest()->in('Concorrencia')` sem `RefreshDatabase`; conexão `pgsql` no banco `ebi_teste` quando `TESTE_PGSQL=1`; senão `markTestSkipped`)
- Modify: `.github/workflows/ci.yml` (passo depois de `composer test`; o job de `composer test` já tem o serviço `postgres:17-alpine`, junto com Redis e Mailpit, que **continuam ligados**: o `tests/Pest.php` força Redis fora, mas não desliga serviço de container)

**Atenção — `force="true"` no `phpunit.xml`:** `DB_CONNECTION=sqlite` está com `force`, e `tests/Pest.php` também fixa `DB_CONNECTION` por `putenv`. Essas variáveis **vencem** as do ambiente e seriam as mesmas mesmo com `-e`: por isso o `tests/Pest.php` tem de **consultar `TESTE_PGSQL` primeiro** e só então fixar `sqlite`. O passo do CI não pode depender de `-e` na linha de comando: exportar as variáveis no `env:` do passo e decidir dentro do `Pest.php`.

**Interfaces:**
- Produces:
  - `bin/executar.php <acao> <id> <usuarioId> <papel> <escopo>` — carrega a aplicação, chama o serviço real (`escolher`, `cancelar`, `retirarMembro`, `incluirMembro`, `decidir`) e imprime `ok` ou o `code` do `ProblemException`.
  - `Suporte::correr(array $comandoA, array $comandoB): array{0:string,1:string}` — dispara os dois `proc_open` **antes** de ler qualquer saída (duas conexões independentes).
  - `Suporte::cenario(): array` — `migrate:fresh` no `ebi_teste` e semeia escala, vaga e duas ofertas.

- [x] **Step 1: Escrever os 5 pares** da decisão 12; cada um confere **as duas saídas** e o **estado persistido**.

| Teste | Expectativa |
|---|---|
| `it('aceite x cancelamento da mesma vaga')` | uma saída `ok` e a outra `vaga_indisponivel`; vaga `preenchida` com 1 membro novo, ou `cancelada` com 0 |
| `it('aceite x aprovacao de troca do original')` | vaga `preenchida` e troca `cancelada`, **ou** vaga `cancelada` (`membro_trocado`) e troca `aprovada`; nunca as duas efetivadas |
| `it('aceite x retirada do original')` | retirada `ok`; aceite antes → vaga `preenchida` com `substitui_membro_id` nulo; depois → `vaga_indisponivel` e vaga `cancelada` |
| `it('aceite x inclusao manual da mesma pessoa')` | exatamente 1 `escala_membro` ativo da pessoa |
| `it('dois aceites de ofertas da mesma vaga')` | `ok` e `oferta_ja_decidida` (ou `vaga_indisponivel`); 1 oferta `aceita` e 1 `descartada` |

- [x] **Step 2: Rodar e ver falhar** (trava desligada de propósito)

Em `TravaEscala::executar`, trocar temporariamente `lockForUpdate()` por sem lock (`get()`/`find()`).

`exec` está em uso na mesma linha do `executar`, e `docker compose exec` **não aceita `-e`** (o `-e` é do `docker compose run`). Passar a variável pelo `compose` pai funciona:

```powershell
$env:TESTE_PGSQL='1'; docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec app php artisan test --testsuite=Concorrencia; Remove-Item Env:TESTE_PGSQL
```

Expected: FAIL em pelo menos `dois aceites` (2 membros criados). **Reverter a alteração** antes de seguir.

- [x] **Step 3: Implementar** o suporte. Código de produção não muda. Se um par falhar com a trava ligada, a guarda que faltava volta para a tarefa dona (6 ou 8), sem afrouxar o teste.

- [x] **Step 4: Rodar e ver passar**

Run: mesmo comando do Step 2, com `$env:TESTE_PGSQL='1'` antes e `Remove-Item Env:TESTE_PGSQL` depois.
Expected: PASS (5 testes), repetido **3 vezes seguidas**.
Run: `... exec app composer test`
Expected: PASS, **sem** executar a pasta `Concorrencia`.

- [x] **Step 5: Commit**

```powershell
git add backend/tests/Concorrencia backend/phpunit.xml backend/tests/Pest.php .github/workflows/ci.yml
git commit -m "test(escalas): corridas de vaga em PostgreSQL com processos separados"
```

---

### Tarefa 12: Tela Equipes de escala e navegação

**Files:**
- Modify: `frontend/src/api/escalas.ts`
- Create: `frontend/src/pages/EquipesEscala.tsx`, `frontend/src/pages/EquipesEscala.test.tsx`
- Modify: `frontend/src/components/layout/ListaNav.tsx`, `frontend/src/components/layout/ListaNav.test.tsx`, `frontend/src/App.tsx`

**Interfaces:**
- Consumes: tipos de `schema.d.ts` gerados na Task 3.
- Produces:
  - `listarEquipes(ebiId)`, `obterEquipe(id)`, `criarEquipe(ebiId, coordenadoraId)`, `incluirMembroEquipe(id, usuarioId)`, `retirarMembroEquipe(id, usuarioId)`
  - `export function EquipesEscala({ contexto, papeis }: { contexto: ContextoAtuacao | null; papeis: string[] })`
  - `IdTela` ganha `'equipes-escala'`

- [x] **Step 1: Escrever os testes que falham**

| Teste | Expectativa |
|---|---|
| `it('lista as equipes do EBI com coordenadora sem vinculo marcada')` | texto "coordenadora sem vínculo" |
| `it('coordenadora sem equipe ve "Criar minha equipe"')` | botão chama `criarEquipe(ebi, eu)` |
| `it('inclui membro a partir do GET /ebis/{id}/team')` | `incluirMembroEquipe` chamado |
| `it('mostra o erro do servidor')` | 403 `nao_e_dona_da_equipe` → mensagem na tela |
| `it('membro sem vinculo aparece marcado')` | |
| `ListaNav: it('mostra equipes-escala para coordenadora, chefe e adm_igreja')` | e **não** mostra para `auxiliar` |

- [x] **Step 2: Rodar e ver falhar**

Run: `cd frontend; npx vitest run src/pages/EquipesEscala.test.tsx src/components/layout/ListaNav.test.tsx; cd ..`
Expected: FAIL — `Failed to resolve import "./EquipesEscala"`.

- [x] **Step 3: Implementar**

- Padrão de `Escalas.tsx`: seletor de EBI, lista e detalhe. Escrita liberada para `coordenadora`, `coordenadora_chefe` e `adm_igreja`; o servidor decide a regra de dona.
- `App.tsx` ganha a rota `tela === 'equipes-escala'`.

- [x] **Step 4: Rodar e ver passar**

O mesmo comando, PASS; depois `npm run lint` e `npx tsc -b`.

- [x] **Step 5: Commit**

```powershell
git add frontend/src/api/escalas.ts frontend/src/pages/EquipesEscala.tsx frontend/src/pages/EquipesEscala.test.tsx frontend/src/components/layout/ListaNav.tsx frontend/src/components/layout/ListaNav.test.tsx frontend/src/App.tsx
git commit -m "feat(escalas): tela de equipes da coordenadora"
```

---

### Tarefa 13: Telas Escalas e Minhas escalas

**Files:**
- Modify: `frontend/src/api/escalas.ts`
- Modify: `frontend/src/pages/Escalas.tsx`, `frontend/src/pages/Escalas.test.tsx`
- Modify: `frontend/src/pages/MinhasEscalas.tsx`, `frontend/src/pages/MinhasEscalas.test.tsx`
- Modify: `frontend/src/lib/formatos.ts`

**Interfaces:**
- Produces:
  - `abrirVaga(escalaId, corpo: { substitui_membro_id?: string; observacao?: string })`, `cancelarVaga(id)`, `listarVagasEbi(ebiId, filtro)`, `minhasVagas(inicio, fim)`, `oferecer(vagaId)`, `retirarOferta(id)`, `escolherOferta(id)`
  - `rotuloStatusVaga(s)`, `rotuloMotivoVaga(m)`, `rotuloStatusOferta(s)`

- [x] **Step 1: Escrever os testes que falham**

`Escalas.test.tsx`:
- `it('montar pre-seleciona a dona e os membros vigentes da equipe')` — **o teste fixado na Task 5 passa a verde**: `criarEscala` recebe `equipe_id`, a dona como `coordenadora` e os membros sem `sem_vinculo` como `auxiliar`;
- `it('sem equipe mostra o atalho Criar minha equipe')`;
- `it('escrita obedece pode_gerir')` — `pode_gerir: false` → nenhum botão de escrita;
- `it('dona abre vaga com aviso de observacao publica')` — texto "visível a todos os auxiliares do EBI";
- `it('escolher oferta so com pode_escolher_oferta')`;
- `it('painel Vagas do EBI lista por data')`.

`MinhasEscalas.test.tsx`:
- `it('lista vagas abertas e permite oferecer')`;
- `it('mostra minha_oferta e permite retirar')`;
- `it('mostra conflito_de_horario devolvido pelo servidor')`.

- [x] **Step 2: Rodar e ver falhar**

Run: `cd frontend; npx vitest run src/pages/Escalas.test.tsx src/pages/MinhasEscalas.test.tsx; cd ..`
Expected: FAIL — `abrirVaga is not a function` e textos inexistentes.

- [x] **Step 3: Implementar**

- Remover o `equipe_id: ''` provisório da Task 5 (o comentário `TODO(Tarefa 13)` sai junto).
- Substituir `podeGerir`/`podeAprovar` por papéis pelos campos do servidor onde existirem (`pode_gerir`, `pode_escolher_oferta`).

- [x] **Step 4: Rodar e ver passar**

O mesmo comando, PASS; depois `npm run lint`, `npx tsc -b` e `npx vitest run` (suíte toda do frontend).

- [x] **Step 5: Commit**

```powershell
git add frontend/src/api/escalas.ts frontend/src/pages/Escalas.tsx frontend/src/pages/Escalas.test.tsx frontend/src/pages/MinhasEscalas.tsx frontend/src/pages/MinhasEscalas.test.tsx frontend/src/lib/formatos.ts
git commit -m "feat(escalas): montar escala pela equipe, vagas e ofertas nas telas"
```

---

### Tarefa 14: Documentação, ADRs e backlog

**Files:**
- Create: `docs/06-arquitetura/decisoes/ADR-0034-equipes-da-coordenadora-e-vaga-de-escala.md`
- Modify: `docs/06-arquitetura/decisoes/ADR-0033-coordenadora-chefe-e-regras-da-escala.md` (§4, §5, §7)
- Modify: `docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md` (linhas "Equipe da coordenadora" e "Vaga: oferecer"; corrigir a linha 57)
- Modify: `docs/03-dados/estados.md`, `docs/03-dados/modelo-conceitual.md`, `docs/01-requisitos/funcionais/RF-07-escalas.md`, `docs/CHANGELOG.md`

- [x] **Step 1: Escrever a lista de verificação.** Cada item confirmado por `grep`:
  - ADR-0034: contexto, decisão (equipe, dona, acréscimo, vaga, privacidade, concorrência, duas regras de conflito), alternativas descartadas, consequências e a seção **Backlog** com (a)–(e) da spec;
  - ADR-0033: §4 e §5 anotados com "emendado pelo ADR-0034"; §5 diz "cancela trocas **pendentes**"; §7 alinhado ao `RelatorioPresenca`;
  - matriz: `equipe.gerenciar`; linha 57 corrigida (auxiliar sem `escala.ler`; `GET /ebis/{id}/team` sob `escala.gerenciar`);
  - `estados.md`: máquinas de estado da vaga e da oferta (decisão 5);
  - `modelo-conceitual.md`: `equipe`, `equipe_membro`, `vaga_escala`, `oferta_vaga_escala`;
  - RF-07: critérios de aceite novos;
  - CHANGELOG: eventos novos e a quebra (não há dados a migrar).

- [x] **Step 2: Conferir que ainda falta**

```powershell
Select-String -Path docs\06-arquitetura\decisoes\ADR-0034*.md,docs\05-seguranca-e-privacidade\matriz-de-autorizacao.md -Pattern "equipe.gerenciar"
```
Expected: erro de arquivo inexistente (ADR-0034) e nenhuma ocorrência na matriz.

- [x] **Step 3: Escrever os documentos.**

- [x] **Step 4: Conferir e fechar com a suíte verde**

```powershell
Select-String -Path docs\06-arquitetura\decisoes\ADR-0034*.md -Pattern "Backlog"
Select-String -Path docs\05-seguranca-e-privacidade\matriz-de-autorizacao.md -Pattern "equipe.gerenciar"
Select-String -Path docs\06-arquitetura\decisoes\ADR-0033*.md -Pattern "ADR-0034"
docker compose -p ebi-escalas -f docker-compose.yml -f docker-compose.worktree.yml exec app composer test
cd frontend; npx vitest run; cd ..
```
Expected: uma ocorrência em cada arquivo; PASS nas duas suítes (**verificação final da entrega**).

- [x] **Step 5: Commit**

```powershell
git add docs/06-arquitetura/decisoes/ADR-0034-equipes-da-coordenadora-e-vaga-de-escala.md docs/06-arquitetura/decisoes/ADR-0033-coordenadora-chefe-e-regras-da-escala.md docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md docs/03-dados/estados.md docs/03-dados/modelo-conceitual.md docs/01-requisitos/funcionais/RF-07-escalas.md docs/CHANGELOG.md
git commit -m "docs(escalas): ADR-0034, emendas do ADR-0033, matriz e backlog"
```

---

## Depois da última tarefa

1. Revisão da **branch inteira** (não só do diff): coerência entre contratos de equipe → escala → vaga; privacidade de ponto a ponto (decisão 9); nenhum caminho que grave "e responda erro" fora do padrão `ResultadoVaga`.
2. `testador` contra os critérios de aceite da spec, seção por seção — equipe, montagem e dona, acréscimo, vaga e oferta, privacidade, aviso à chefe, presença, concorrência, autorização e frontend — com a evidência de cada comando.
3. Relatório final: o que passou, o que ficou de fora (backlog), o que divergiu da spec e por quê.
