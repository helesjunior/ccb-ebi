# Desenho — Organização (Etapa 2)

**Data:** 09/11/2026 · **Status:** proposto, aguardando aprovação
**Abrange:** Etapa 2 (Organização · E02) do [plano de desenvolvimento](../../07-produto/plano-de-desenvolvimento.md)
**Requisitos:** [RF-01](../../01-requisitos/funcionais/RF-01-estrutura-organizacional.md), [ADR-0017](../../06-arquitetura/decisoes/ADR-0017-hierarquia-e-ministerios.md)

> **Atenção — mudança de requisito nesta leva.** A decisão do dono do produto **inverte** a regra
> territorial descrita em três lugares da documentação vigente (2.7): RN-09, CA-18 e o modelo
> conceitual. Este desenho implementa a **decisão do dono do produto** e **atualiza a documentação
> de requisitos na mesma leva** (ver seção 8), para que doc e código nasçam coerentes.

## 1. Intenção e o porquê

A fundação técnica (Etapas 0 e 1) entregou a esteira, o esqueleto modular e a base da API, sem regra de negócio. A **Etapa 2 cria a espinha dorsal organizacional** sobre a qual todo o resto se apoia: a hierarquia `Central (Brás) → Regional → Setor → Igreja → EBI` (ADR-0017), o **histórico de vinculação** da igreja com o setor e a carga da Regional de Brasília por CSV.

Por que agora: escopo e papéis (E01) dependem da hierarquia (E02); presença e relatórios precisam saber a que setor uma igreja pertence.

**Pronto quando:** a transferência de uma igreja de um setor para outro faz **todos** os dados daquela igreja passarem a ser atribuídos ao setor novo, sem data de corte, e a carga de Brasília roda sem erro.

## 2. Objetivos, não objetivos e anti-objetivos

**Objetivos**
- Módulo `Organizacao` com as entidades `central_nacional`, `regional`, `setor`, `igreja`, `igreja_vinculacao`, `ebi`.
- Hierarquia navegável e CRUD de regional, setor, igreja e EBI.
- **Transferência de igreja entre setores com histórico de vinculação**; o setor vigente é sempre o atual da igreja.
- Carga inicial da Regional de Brasília por **importação CSV** idempotente (chave: `codigo` da igreja).
- Endpoints REST `/api/v1` documentados no OpenAPI **antes** do código, com testes de contrato, de autorização e de auditoria.
- Toda escrita organizacional gera **evento de auditoria** (RF-16).
- **Atualizar a documentação de requisitos** (RN-09, CA-18, modelo conceitual/dicionário) para a nova regra territorial, na mesma leva (seção 8).

**Não objetivos (nesta leva)**
- **Geocadastro** (`GET /churches/nearby?lat=&lon=`) — adiado por decisão sua; a igreja guarda `latitude`/`longitude`, mas a sugestão por proximidade e seus limites de uso ficam para a etapa do RF-08.
- Autorização real RBAC+ABAC e `X-Acting-Scope` (Etapa 3, E01). Aqui é **provisória**.
- Importação de organograma da CCB inteira; só a Regional de Brasília.
- Interface no portal Web para gestão organizacional (o foco do portal nesta leva continua o login).

**Anti-objetivos (o que faria esta leva falhar mesmo "funcionando")**
- Um dado antigo continuar preso ao setor antigo depois da transferência (a regra é: tudo vai para o setor atual).
- Guardar o setor/regional "na data" em lugar que congele o valor (ex.: `sessao.setor_id`), reintroduzindo o comportamento antigo.
- Vínculos com sobreposição de vigência (duas linhas ativas para a mesma igreja).
- Carga CSV não idempotente (rodar duas vezes duplica igrejas/setores).
- Escrita organizacional sem evento de auditoria.
- Endpoint sem teste de autorização (quem pode e quem não pode).

## 3. Restrições

- **PHP 8.4 sempre em contêiner** (host é 8.3); comandos via `docker compose run --rm app ...`.
- **Monólito modular** (ADR-0002): o módulo `Organizacao` expõe uma **interface pública**; outros módulos não leem suas tabelas. O teste de arquitetura já existente cobre isso.
- **PostgreSQL 17** em produção e no compose; os testes usam SQLite (`phpunit.xml`), então o esquema deve funcionar nos dois.
- **Nenhum dado real** de participantes antes da Etapa 8; o CSV de Brasília traz dados organizacionais (públicos) para homologação, e o *seeder* de desenvolvimento gera hierarquia fictícia.
- **`contract-first`**: cada rota entra no `openapi.yaml` antes do código e é marcada `no OpenAPI` em `endpoints.md`; `scripts/check-endpoints.py` no CI confere o alinhamento.
- Idiomas: domínio e documentação em português; rotas e campos da API em **inglês** (`church`, `sector`, `region`, `ebi`).
- **IDs opacos (ULID)** expostos externamente.
- Erros no formato `application/problem+json` (RFC 9457); paginação por cursor; `limit` máx. 100.

## 4. Abordagens consideradas

**A) `igreja_vinculacao` explícito como fonte da verdade territorial (escolhida).**
A igreja **não** guarda `setor_id` direto. O setor vigente da igreja é a linha de `igreja_vinculacao` com `fim IS NULL`. Transferir = fecha o vínculo ativo (`fim = data do ato`) e abre um novo (`inicio = data`), numa transação, sob a restrição "sem sobreposição de vigência para a mesma igreja". **Como o setor é sempre o atual, todo dado vinculado à igreja (sessões, EBIs, relatórios) passa a pertencer ao setor novo no instante da transferência** — não há congelamento do setor da época. Atende a decisão do dono do produto e não exige que outros módulos gravem `setor_id`/`regional_id` próprios. Custo: consultar o setor atual sempre passa por join com `fim IS NULL` (irrelevante para a volumetria do MVP).

**B) `igreja.setor_id` direto + tabela de histórico só-auditoria.** A igreja tem `setor_id` atual; transferir atualiza a coluna e insere uma linha de histórico. Com a regra "setor é sempre o atual", isso **já seria suficiente** para os dados, mas perde o histórico de transferências consultável que o RF-01 item 4 pede. **Descartada** por não atender o requisito de histórico.

**C) Híbrido: `setor_id` denormalizado na igreja + vigência.** Leitura barata do estado atual e histórico consultável. Contra: dois lugares para manter consistentes. Na prática, a abordagem A já dá a leitura barata via índice; o ganho não paga a complexidade. **Descartada.**

**Decisão:** Abordagem A. Você escolheu A, e confirmou a regra "a igreja é a âncora; o setor é sempre o atual, sem data de corte".

## 5. Estrutura e decisões de como construir

### 5.1 Modelo de dados

| Tabela | Campos | Notas |
|---|---|---|
| `central_nacional` | `id` (ULID), `nome`, timestamps | Raiz da hierarquia; nomeada "Central (Brás)" nas telas |
| `regional` | `id`, `central_nacional_id`, `nome`, `codigo`, `status`, timestamps | `codigo` UNIQUE |
| `setor` | `id`, `regional_id`, `nome`, `codigo`, `status`, timestamps | `codigo` UNIQUE por regional |
| `igreja` | `id`, `codigo` (string, UNIQUE), `nome`, `endereco`, `latitude`, `longitude`, `timezone`, `status`, timestamps | **`codigo` obrigatório e UNIQUE** (ex.: `240003`); **sem** `setor_id` |
| `igreja_vinculacao` | `id`, `igreja_id`, `setor_id`, `inicio` (date), `fim` (date, nullable), timestamps | "sem sobreposição de vigência para a mesma igreja"; `fim IS NULL` = vínculo atual |
| `ebi` | `id`, `igreja_id`, `nome`, `status`, `coordenadora_chefe_id` (nullable), `participantes_por_auxiliar` (int, default 4), timestamps | mais de um EBI por igreja permitido |

- **`igreja.codigo`** é o código oficial da CCB (o `240003` do Núcleo Bandeirante, Central da Regional Brasília), **string** de dígitos (nunca número, para não perder zeros à esquerda), UNIQUE e **não gerado pelo sistema**. É a chave natural da importação, da deduplicação e do `POST /churches`.
- **ULID** como chave primária (IDs opacos expostos). **Atenção:** `atores` usa `bigint` auto-incremento — ver seção 8 (dívida de coerência de IDs).
- `status` como enum de domínio (`ativo`/`inativo`), replicando o padrão de enums do projeto.
- Restrições de integridade no banco (FKs) e um índice para "vínculo atual" (`igreja_id`, `fim`).

### 5.2 Módulo e fronteira

- Preencher `app/Domain/Organizacao/` no padrão dos demais módulos:
  - `Contracts/` — interface pública (ex.: `ConsultarOrganizacao`, `TransferirIgreja`).
  - `Models/` — as seis entidades.
  - `Services/` — `ServicoTransferencia` (fechar/abrir vínculo numa transação, registrar auditoria).
  - Bindings no `DomainServiceProvider`.
- Nenhum outro módulo acessa as tabelas de `Organizacao` direto; consome os contratos. O teste de arquitetura (`ModularBoundariesTest`) é estendido com um caso para o novo contrato.
- **Contrato territorial para as etapas seguintes:** o módulo expõe `setorDo(igrejaId): Setor` (o setor vigente), de modo que Sessões (E04) e Relatórios (E06) resolvam o território **pela igreja**, sem gravar `setor_id`/`regional_id` próprios.

### 5.3 Transferência e vinculação

- Serviço `TransferirIgreja::executar(igrejaId, setorDestinoId, data, motivo)`:
  1. Valida que existe vínculo ativo e que o destino é um setor existente e diferente.
  2. Fecha o vínculo ativo: `fim = data` (**exclusivo** — intervalo `[inicio, fim)`).
  3. Abre novo vínculo: `inicio = data`, `fim = NULL`.
  4. Registra evento de auditoria (`acao: transferir`, `recurso: igreja`, escopo, motivo).
  5. Tudo numa transação; a sobreposição é verificada no serviço e reforçada no banco. A API aceita **data explícita** (inclusive passada), validando que não sobrepõe vínculo existente.
- **Regra de leitura (a regra do dono do produto):** o setor de uma igreja é sempre o **vigente agora** (`fim IS NULL`). Depois de transferir a 240003 de A para B, **qualquer** relatório — passado ou futuro — passa a atribuir os dados da 240003 a **B**. Não há data de corte nem congelamento do setor da época.
- **Teste central (substitui o CA-18 como estava):** registra dados vinculados à igreja no setor A, transfere para B, e confirma que a **mesma** leitura (inclusive de um período anterior) agora resolve **B**.

### 5.4 Autorização provisória (meu call)

- Nesta leva, a autorização é **esqueleto**: um `Gate`/policy `gerir-organizacao` liberado para o **ator administrativo fictício** (o `Ator` semeado), com middleware aplicado às rotas de escrita.
- Leitura autenticada (qualquer ator autenticado lê). A matriz de autorização completa (que restringe leitura por nível e escopo territorial) entra na Etapa 3.
- Cada endpoint tem teste **"pode" e "não pode"** (autorizado e não autorizado). A Etapa 3 substitui o gate provisório por RBAC+ABAC e `X-Acting-Scope`, e estes testes viram o alicerce dos testes da matriz.

### 5.5 Carga inicial da Regional de Brasília (CSV)

- Comando `php artisan organizacao:importar-brasilia <arquivo.csv>` (**não** é endpoint de usuário; é carga operacional).
- Formato: uma linha por igreja, com `codigo` da igreja e colunas de regional/setor/igreja (e lat/long/timezone quando houver). A primeira linha é o cabeçalho.
- **Idempotente por `igreja.codigo`** (`updateOrCreate`); rodar duas vezes não duplica. Relatório final: criados/atualizados/ignorados.
- Um arquivo de exemplo versionado (`database/data/brasilia.csv`), com um conjunto pequeno e **fictício/ilustrativo** (incluindo a 240003) para não travar a leva em dados reais.

### 5.6 Infra de API que falta na fundação (meu call)

Verifiquei o código: **não existe** middleware/helper de `Idempotency-Key` nem de paginação por cursor, embora o plano da Etapa 1 os listasse na "base comum". Como esta leva precisa listar igrejas/EBIs (paginação) e transferir/criar com segurança de repetição (idempotência), **eu construo aqui**:
- paginação por cursor (`?cursor=&limit=`, máx. 100) no padrão do Laravel;
- idempotência para as escritas que a declaram (`Idempotency-Key`), com replay da resposta (201 vs. 200 conforme convenções).

### 5.7 API (contract-first)

Rotas em `/api/v1` (inglês), na convenção do projeto:

| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/regions/{id}/sectors` | Setores de uma regional | RF-01 |
| GET/POST | `/churches` | Listar (paginado, filtros) e criar igreja | RF-01 |
| GET/PATCH | `/churches/{id}` | Ver e atualizar igreja | RF-01 |
| POST | `/churches/{id}/transfers` | Transferir igreja de setor | RF-01 |
| GET/POST | `/ebis` | Listar e criar EBI | RF-01 |
| GET/PATCH | `/ebis/{id}` | Ver e atualizar EBI | RF-01 |

- CRUD de **regional, setor e central** nesta leva: **decidido** — exposto por endpoints de escrita (`POST/PATCH /regions`, `/sectors`; `GET /central`) para que a hierarquia seja criável sem depender só do CSV/seeder. Rotas a detalhar no OpenAPI antes do código.
- Paginação por cursor; filtros por query string; ordenação `?sort=`.
- Escrita com `Idempotency-Key` (criação/transferência).
- Recursos JSON em inglês (`ChurchResource`, `SectorResource`, `EbiResource`, `RegionResource`); o `setor` da igreja exposto a partir do **vínculo vigente**.
- `openapi.yaml` e `endpoints.md` atualizados **antes** da implementação.

### 5.8 Testes

- **Domínio:** transferência fecha/abre vínculo; sem sobreposição; `fim` exclusivo; data explícita validada.
- **Regra territorial (o teste central da etapa):** dados da igreja atribuídos a A antes da transferência passam a resolver **B** depois, inclusive para período anterior.
- **Importação CSV:** idempotência (2×), contagem criados/atualizados, erro de linha sem `codigo`.
- **Autorização:** pode/não pode por endpoint de escrita.
- **Auditoria:** escrita organizacional gera evento.
- **Contrato:** cada rota responde conforme o OpenAPI.
- **Arquitetura:** novo contrato do módulo respeita a fronteira.
- Mantém `pint`, `phpstan` (nível 8) e o trio do CI verdes.

## 6. O que fica para o construtor

- Nomes exatos de `timezone`, `endereco` (texto livre vs. estruturado) e `latitude`/`longitude` — seguir o dicionário de dados.
- Formato final das colunas do CSV de Brasília — a estrutura fica pronta; o conteúdo real é carregado em homologação.
- Se `coordenadora_chefe_id` de `ebi` vira FK para `usuario` (Etapa 3) — nesta leva fica coluna nullable sem FK dura, documentada.
- Formato de `escopo` no evento de auditoria (ex.: id do setor/igreja afetado).
- Quais endpoints de região/setor/central exatamente (nomes e payloads) — detalhados no OpenAPI antes do código.

## 7. Dívidas registradas nesta leva

- **Geocadastro** (`/churches/nearby`) adiado (decisão sua).
- **Autorização real** (RBAC+ABAC, `X-Acting-Scope`) fica para a Etapa 3; aqui é gate provisório.
- **FK de `coordenadora_chefe_id`** e a relação com `usuario` ficam para a Etapa 3.
- **Coerência de IDs:** a fundação expõe `atores` como `bigint`, enquanto esta leva usa ULID. Reconciliar o padrão de IDs opacos (idealmente migrar `atores`/futuros `usuario` para ULID, ou padronizar strings na API) — decidir na Etapa 3.

## 8. Mudança de requisito e documentação a atualizar (decidida)

A decisão do dono do produto nesta leva **contradiz** três itens da documentação atual (versão 2.7):

| Item | O que diz hoje | O que passa a valer (decisão desta leva) |
|---|---|---|
| RN-09 | "Transferências organizacionais **não mudam** a igreja, o setor nem a regional históricos da sessão." | A transferência **muda** todas as atribuições territoriais da igreja para o setor novo, sem data de corte. |
| CA-18 | "relatórios de períodos anteriores **continuam atribuindo as sessões antigas ao setor original**." | Relatórios de períodos anteriores passam a atribuir os dados ao **setor atual** da igreja. |
| Modelo conceitual (`sessao`) | `sessao` carrega `igreja_id, setor_id, regional_id` "unidade territorial **gravada na data**". | `sessao` deve gravar só `igreja_id`; setor/regional são derivados do **vínculo vigente** no momento da consulta. |

**Ação (decidida):** atualizar RN-09, CA-18 e o modelo conceitual/dicionário **nesta leva**, junto do código, para que documentação e implementação nasçam coerentes.

**Novo texto do CA-18:** *"Transferência de igreja: depois que a igreja muda de setor, **todos** os relatórios — inclusive de períodos anteriores — passam a atribuir os dados daquela igreja ao **novo setor**; a igreja é a âncora e o setor é sempre o vigente."*

**Novo texto da RN-09:** *"Transferências organizacionais não criam data de corte: o território (setor e regional) de uma igreja é sempre o vigente atual. Ao transferir, todos os dados daquela igreja — inclusive de períodos anteriores — passam a ser atribuídos ao novo setor. O vínculo com vigência preserva o histórico de transferências (RF-01), não a atribuição territorial dos dados."*

**Modelo conceitual/dicionário:** `sessao` deixa de carregar `setor_id`/`regional_id` próprios; passa a referenciar a `igreja` e derivar setor/regional do vínculo vigente no momento da consulta.