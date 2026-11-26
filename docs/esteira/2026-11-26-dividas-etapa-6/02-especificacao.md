# Especificação técnica

## Solução

A entrega tem três partes independentes. Elas compartilham dois ajustes pequenos: um contrato novo em `Sessoes` e um helper de finalidade.

### 1. "Ligado ao escopo" (CA-27)

**Contrato novo em `Sessoes`.** Em `backend/app/Domain/Sessoes/Contracts/ConsultarPresenca.php`:

```php
/** EBIs (ids distintos) onde o participante tem presença `valida`. @return array<int, string> */
public function ebisComPresencaValida(string $participanteId): array;
```

A implementação fica em `backend/app/Domain/Sessoes/Services/ConsultaPresenca.php`:

```php
return Presenca::query()->join('sessao', 'sessao.id', '=', 'presenca.sessao_id')
    ->where('presenca.participante_id', $participanteId)
    ->where('presenca.status', Presenca::STATUS_VALIDA)
    ->distinct()->pluck('sessao.ebi_id')->map(fn ($id) => (string) $id)->all();
```

O índice `presenca (participante_id, registrado_em)` já existe e atende o filtro.

**Regra em `backend/app/Support/Participantes/AutorizacaoParticipante.php`.** A classe passa a receber a dependência `ConsultarPresenca`, que é um contrato de `Sessoes`. A direção é Support → Contracts: não há ciclo nem violação do ADR-0002.

Métodos novos:

```php
/** CA-27: ligado ao escopo do ator para mesclar/corrigir (definição abaixo). */
public function ligado(?ContextoAtuacao $contexto, Participante $p, string $operacao): bool

/** Coordenadora (e demais papéis): TODOS ligados. coordenadora_chefe: AO MENOS UM. 403 se falhar. */
public function exigirLigados(?ContextoAtuacao $contexto, array $participantes, string $operacao): void

/** O escopo cobre esta igreja? Resolve a EBI da igreja, como cobreTerritorio já faz. */
public function cobreIgreja(?ContextoAtuacao $contexto, string $igrejaId, string $operacao): bool
```

Algoritmo de `ligado`, nesta ordem:

1. Contexto nulo: `false`.
2. `$contexto->papelCodigo === 'super_admin'`: `true`. É um curto-circuito explícito: sem ele, um cadastro sem igreja e sem presença daria 403 até para o `super_admin`, porque `permite` nunca seria chamado.
3. Participante **com** `igreja_referencia_id`: o resultado é `cobreIgreja($contexto, $igreja, $operacao)`. A presença **não é consultada**. Esta é a decisão do produto da revisão 1, explicada em "Segurança".
4. Participante **sem** igreja de referência: `true` se algum `$ebiId` de `ebisComPresencaValida($p->id)` passar em `$this->autorizar->permite($contexto, $operacao, 'ebi', $ebiId)`.

Outras regras:

- `cobreTerritorio` passa a delegar a `cobreIgreja`, sem mudar o comportamento: igreja vazia continua dando `false`.
- `exigirLigados` lança `ProblemException('acesso_negado', 'Acesso negado', 403, ...)`, o mesmo erro de `exigir`. Contexto nulo também dá 403.
- `exigirLigados` **não** considera a propriedade (responsável): as rotas exigem `participante.mesclar` ou `participante.corrigir`, que o responsável não tem.
- `pode`, `exigir` e as leituras **não mudam**.

**Mesclar e desfazer** em `backend/app/Http/Controllers/Api/V1/ParticipantMergeController.php`:

- `store`: carregar o principal e o duplicado com `GerirParticipante::encontrar` (404 se algum não existir) e chamar `exigirLigados($contexto, [$principal, $duplicado], 'participante.mesclar')`. Isso substitui as duas chamadas a `exigirEscopo`.
- `undo`: carregar o principal (404 se não existir) e ler `$principal->snapshot_mesclagem['duplicado']['id'] ?? null`.
  - Com snapshot e duplicado existente: `exigirLigados` com `[principal, duplicado]`.
  - Senão: `exigirLigados` com `[principal]`. Depois disso, o serviço responde `409 sem_mesclagem`.
- Remover `exigirEscopo`, que fica sem uso.
- Atualizar o comentário do topo da classe e o de `backend/app/Domain/Participantes/Services/GerenciadorMesclagem.php` (linhas 20–22), que hoje dizem "nesta etapa".

### 2. Correção de cadastro: `PATCH /participants/{id}`

**Rota** em `backend/routes/api.php`, no grupo `IdempotenciaMiddleware` dos participantes (perto da linha 186):

```php
Route::patch('participants/{id}', [ParticipantController::class, 'update'])->middleware('can:participante.corrigir')->name('participants.update');
```

**Request novo** `backend/app/Http/Requests/Participantes/CorrigirParticipanteRequest.php`:

```php
'nome' => ['sometimes', 'string', 'max:255'],
'nome_social' => ['sometimes', 'nullable', 'string', 'max:255'],
'data_nascimento' => ['sometimes', 'date', 'before_or_equal:today'],
'igreja_referencia_id' => ['sometimes', 'nullable', 'string', 'exists:igrejas,id'],
'motivo' => ['required', 'string', 'min:3', 'max:255'], // evento_auditoria.motivo é varchar(255)
```

**`ParticipantController::update(CorrigirParticipanteRequest $request, string $id)`.** O `FormRequest` valida o corpo antes do controller. A ordem completa das respostas é:

1. Corpo inválido: `422`, vindo do `FormRequest`. Isso não revela se o participante existe, porque a regra não depende dele.
2. Participante inexistente: `404`. Carregar com `encontrar`.
3. Participante não ligado: `403`, via `exigirLigados($contexto, [$participante], 'participante.corrigir')`. Com um item só, "todos" e "algum" coincidem.
4. Nenhum campo diferente do atual: `422 nada_a_corrigir`.
   - `$motivo = $request->validated('motivo')`.
   - `$dados` é `validated()` sem `motivo`, só com os campos cujo valor difere do atual.
   - A data é comparada como string: `$participante->data_nascimento?->toDateString()` contra `Carbon::parse($valor)->toDateString()`. Os demais campos usam comparação estrita.
   - Se `$dados === []`, a resposta é `422 nada_a_corrigir`. Isso inclui um corpo que só tem `motivo`.
5. Troca de igreja para fora do escopo: `422 igreja_fora_do_escopo`. Vale quando `igreja_referencia_id` está em `$dados` com valor **não nulo** e `cobreIgreja($contexto, $novo, 'participante.corrigir')` é falso.
   - Trocar a igreja para `null` não precisa de checagem extra. Pela regra de `ligado`, se o cadastro tinha igreja e passou do passo 3, essa igreja já estava no escopo do ator.
6. Cadastro mesclado: `409 participante_mesclado`, lançado pelo serviço.
7. Sucesso (a guarda de contexto nulo fica no topo de `update`: `$contexto ?? throw` 403, para o PHPStan estreitar o tipo): `$this->participantes->corrigir($usuarioId, $id, $dados, $motivo, "{$contexto->tipoEscopo}:{$contexto->escopoId}")` e `ParticipanteResource` com `200`.

**Serviço.** Mudam `backend/app/Domain/Participantes/Services/GerenciadorParticipante.php` (linhas 213–245) e o contrato `backend/app/Domain/Participantes/Contracts/GerirParticipante.php`.

- Nova assinatura: `corrigir(string $usuarioId, string $id, array $dados, string $motivo, ?string $escopo): Participante`. Hoje não existe chamador: o único `->corrigir(` em `backend/app` é de `SessaoController` e chama outra classe.
- Participante com `status === 'mesclado'` lança `ProblemException('participante_mesclado', 'Conflito', 409, 'Cadastro mesclado não pode ser corrigido; desfaça a mesclagem.')`.
- O `save` e a auditoria rodam juntos, dentro de `DB::transaction`.
- **Snapshot pendente (decisão do produto, revisão 2, opção a):** se o principal tem `snapshot_mesclagem['principal']`, os campos aplicados também são gravados nele (`$snap['principal'][$campo] = $valor`, com `data_nascimento` como `toDateString()`). Assim, desfazer a mesclagem mais tarde não apaga a correção. O duplicado `mesclado` continua recusado com 409.
- A auditoria grava:
  - `'escopo' => $escopo`. Hoje grava `null`, e por isso o evento não aparece para `adm_setor` nem para `adm_regional` em `ConsultaAuditoria`.
  - `'motivo' => $motivo`.
  - `'campos' => array_keys($aplicados)`, onde `$aplicados` é extraído para variável a partir do `array_intersect_key` hoje inline (linha 224).
  - `antes`, como hoje.

### 3. Finalidade nas leituras de presença

**Helper novo** `backend/app/Support/FinalidadeLeitura.php`, com o método estático `exigir(Request $request): string`.

- Lê `query('finalidade')` ou o header `X-Finalidade` e aplica `trim`.
- Valor vazio: `ProblemException('finalidade_obrigatoria', ...)` com `422`, como no corpo atual de `ParticipantController::finalidade`.
- Mais de 255 caracteres (`mb_strlen`): `ProblemException('finalidade_invalida', 'Dados inválidos', 422, 'A finalidade deve ter até 255 caracteres.')`. A coluna `evento_auditoria.motivo` é `varchar(255)`; no PostgreSQL, um valor maior estouraria dentro da gravação e viraria `500`.

**Trocar as duas cópias privadas.** `ParticipantController::finalidade` e `PresencaController::finalidade` passam a chamar `FinalidadeLeitura::exigir($request)`, e os métodos privados são apagados. Única mudança de comportamento nas rotas que já exigiam finalidade: o limite de 255 caracteres, que conserta o mesmo `500`.

**`PresencaController::index`.** A ordem passa a ser:

1. sessão inexistente: `404`;
2. `Gate::authorize(PRESENCA_LER)`: `403`;
3. `FinalidadeLeitura::exigir`: `422`;
4. `daSessao`;
5. auditoria:

```php
$this->auditoria->registrar([
    'acao' => 'presenca.lida', 'recurso' => 'presenca', 'ator_id' => (string) $request->user()?->getAuthIdentifier(),
    'origem' => 'api', 'escopo' => "ebi:{$sessao->ebiId}", 'motivo' => $finalidade,
    'metadados_minimizados' => ['sessao_id' => $id, 'participante_ids' => array_values(array_unique(array_map(fn ($p) => $p->participanteId, $lista)))],
    'ocorrido_em' => now(),
]);
```

`$lista` é o retorno de `$this->consulta->daSessao($id)`: um array de `PresencaRegistrada`, com a propriedade `participanteId`.

**`HistoricoPresencaController::operador`.** Depois de `exigirOperador`, chamar `$finalidade = FinalidadeLeitura::exigir($request)`, montar a resposta com `historico()` e auditar:

```php
'acao' => 'presenca.lida', 'recurso' => 'presenca', 'escopo' => $participante->igrejaReferenciaId ? "igreja:{$participante->igrejaReferenciaId}" : null,
'motivo' => $finalidade, 'metadados_minimizados' => ['participante_id' => $id]
```

O controller passa a injetar `RegistrarEvento`. `responsavel()` e o método privado `historico()` não auditam.

**Portal.**

- `frontend/src/pages/Presenca.tsx`, linha 79: a chamada `/sessions/{id}/attendance` passa a ter parâmetros próprios: `{ params: { path: { id: sessaoId }, query: { finalidade: 'registro_presenca' } }, headers: cabecalhos(), signal: controller.signal }`. A chamada `/sessions/{id}` continua com `options`.
- `frontend/src/components/HistoricoPresencas.tsx`, linha 48: o ramo `operador` ganha `query: { finalidade: 'consulta_historico' }` em `params`. O ramo `responsavel` não muda.

### Documentação

**`docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md`:**

- Linha 17: trocar "G (escopo a confirmar)" por "G (participantes ligados ao EBI: igreja de referência no EBI, ou sem igreja e com presença válida no EBI; chefe: basta um dos dois cadastros)".
- Tabela de operações: novas linhas `participante.mesclar` e `participante.corrigir`, com rotas e papéis.
- Linha `presenca.ler`: acrescentar "finalidade obrigatória, auditada (`presenca.lida`)".
- Nota da linha 68: trocar "o `*` ainda não é aplicado" pela lista de onde ele já é aplicado: `GET /participants`, `GET /participants/{id}`, `GET /sessions/{id}/participants`, `GET /sessions/{id}/attendance` e `GET /participants/{id}/attendance`.

**`docs/06-arquitetura/decisoes/ADR-0027-escopo-de-mesclagem.md`:** acrescentar a seção "Implementação (2.21)" com:

- a definição de "ligado" da história, que inclui a restrição "presença só liga cadastro sem igreja de referência", com o motivo;
- a regra "coordenadora: os dois; chefe: basta um";
- o desfazer conferindo os dois cadastros.

**`docs/CHANGELOG.md`:** nova versão **2.21 — 26/11/2026**, com as três partes, a menção a `PATCH /participants/{id}` e a `presenca.lida`, e a linha "**Dívidas:** contingência em lote; aviso dos 12 anos (RF-15, E12); tela de correção no portal; mesclar presenças; escopo nos eventos de mesclagem e cadastro".

**`docs/04-api/endpoints.md`:** nova linha logo abaixo da linha 64: `| PATCH | \`/participants/{id}\` | Corrigir cadastro (CA-27, motivo obrigatório) *novo* (\`no OpenAPI\`) | RF-02 |`.

## Contratos

### `PATCH /api/v1/participants/{id}`

Cabeçalhos: `Authorization`, `X-Acting-Scope` e `Idempotency-Key`.

```json
{ "nome": "Ana Maria Souza", "data_nascimento": "2016-03-10", "motivo": "nome digitado errado no cadastro assistido" }
```

| Resposta | Quando |
|---|---|
| `200` com `Participante` | cadastro corrigido |
| `403 acesso_negado` | sem `participante.corrigir`, ou participante não ligado ao escopo |
| `404 not_found` | participante inexistente |
| `409 participante_mesclado` | cadastro já mesclado |
| `422` | corpo inválido no `FormRequest` (inclui data futura e motivo acima de 255), `nada_a_corrigir` ou `igreja_fora_do_escopo` |

**OpenAPI** (`docs/04-api/openapi.yaml`): acrescentar `patch:` em `/participants/{id}` (linha 2466).

- `tags: [participants]`;
- parâmetros `XActingScope`, `ParticipanteId` e `IdempotencyKey`;
- corpo `CorrecaoParticipanteInput`;
- as respostas da tabela acima.

O schema novo vai em `components/schemas`:

```yaml
CorrecaoParticipanteInput:
  type: object
  required: [motivo]
  properties:
    nome: { type: string, maxLength: 255 }
    nome_social: { type: [string, 'null'], maxLength: 255 }
    data_nascimento: { type: string, format: date }
    igreja_referencia_id: { type: [string, 'null'] }
    motivo: { type: string, minLength: 3, maxLength: 255 }
```

### Leituras de presença

`GET /sessions/{id}/attendance` (linha 559) e `GET /participants/{id}/attendance` (linha 2490) ganham:

- `- $ref: '#/components/parameters/FinalidadeLeitura'`;
- a resposta `'422': { $ref: '#/components/responses/Problem' }`;
- uma `description` que cite a auditoria `presenca.lida` e o limite de 255 caracteres.

Se o parâmetro `FinalidadeLeitura` (linha 2968) não tiver `maxLength: 255` no schema, acrescente-o. O ajuste vale para todas as rotas que usam o parâmetro.

Exemplo: `GET /api/v1/sessions/01J.../attendance?finalidade=registro_presenca` responde `200` e grava o evento:

```json
{ "acao": "presenca.lida", "recurso": "presenca", "escopo": "ebi:01J...", "motivo": "registro_presenca",
  "metadados_minimizados": { "sessao_id": "01J...", "participante_ids": ["01J..."] } }
```

### Contrato de módulo

`ConsultarPresenca::ebisComPresencaValida(string $participanteId): array<int, string>`, descrito na seção 1.

Não há migração.

## Decisões

| Decisão | Alternativa descartada | Motivo |
|---|---|---|
| A presença só liga um cadastro **sem** igreja de referência (proposta do arquiteto na revisão 1, aprovada pelo produto em 26/11/2026) | (a) A presença liga qualquer cadastro. (b) A presença liga, mas não permite trocar a igreja. (c) A presença vale só para mesclar. | A busca da sessão acha qualquer cadastro do país. Se a presença ligasse o cadastro, bastaria lançá-la para corrigir cadastros de outras igrejas, que é o cenário que o ADR-0027 proíbe. A opção (b) ainda deixaria alterar nome e data. |
| "Ligado" amplia só mesclar e corrigir; as leituras continuam pela igreja de referência | Usar `ligado` também em `pode`/`exigir` | O ADR-0027 trata só de mesclar e corrigir. |
| A presença conta só se for `valida` | Contar também a `estornada` | Um estorno desfaz o lançamento. |
| `super_admin` com curto-circuito explícito em `ligado` | Depender do bypass de `permite` | Sem igreja e sem presença, `permite` nunca é chamado. O cadastro ficaria impossível de corrigir por qualquer papel. |
| A regra da chefe é decidida pelo `papelCodigo` do contexto | Consultar `chefe_de_ebi_id` | O middleware `X-Acting-Scope` já validou o contexto, e a matriz trata a chefe por papel. |
| `ligado` fica em `app/Support` | Pôr a regra em `GerenciadorMesclagem` | O design da Etapa 6 (§8 item 7) indicou `AutorizacaoParticipante`. Assim o serviço continua sem `ContextoAtuacao`. |
| Desfazer confere o principal e o duplicado do snapshot | Conferir só o principal | Desfazer altera os dois cadastros. |
| A correção exige `motivo` (3 a 255 caracteres) | Motivo opcional, ou até 500 caracteres com migração para `text` | O ADR-0007 pede correção auditável. O limite de 255 cabe na coluna atual e dispensa migração. |
| A nova igreja de referência precisa estar no escopo do ator | Aceitar qualquer igreja | Sem isso, a coordenadora jogaria cadastros nas listas de outra igreja. |
| `PATCH` sem nenhuma mudança real responde `422 nada_a_corrigir` | Gravar `participante.corrigido` vazio | A auditoria ficaria com ruído. |
| O duplicado `mesclado` não é corrigido (409) | Permitir | O cadastro está fora de uso; a correção vai no principal. |
| Corrigir o principal com mesclagem pendente também atualiza `snapshot_mesclagem['principal']` (produto, revisão 2) | (b) Desfazer restaurar só os campos que a mesclagem mudou. (c) Aceitar a perda. | `desfazer` faz `fill($snapshot['principal'])`; sem isso, a correção sumiria sem aviso e sem como recuperar pela auditoria. |
| A chefe pode não conseguir desfazer a própria mesclagem (produto, revisão 2) | Avaliar o principal pelo estado do snapshot | Aceito. Quando o principal sem igreja herda a igreja B do duplicado, quem desfaz é a chefe de B (ou o `super_admin`). |
| `participante.corrigido` grava o escopo de atuação | Manter `escopo = null` | Com `null`, só o `super_admin` vê o evento. O `adm_setor` e o `adm_regional` têm `auditoria.ler`. |
| `FinalidadeLeitura::exigir` em `app/Support`, com limite de 255 | Uma terceira cópia privada | Uma cópia só, e o `500` por estouro da coluna é corrigido em todas as rotas. |
| O responsável fica isento da finalidade | Exigir de todos | O acesso dele é por propriedade (decisão do produto). |
| Nas leituras, a finalidade é exigida depois da autorização (404 → 403 → 422) | Exigir antes | Quem está fora do escopo não descobre se o recurso existe. |
| `Presenca.tsx` continua recarregando a lista depois de cada registro e estorno, o que grava um `presenca.lida` por recarga | Atualizar a lista localmente com a resposta do `POST` | Muda menos o portal. O custo está em "Riscos". |

## Segurança e autorização

- **Acesso horizontal.**
  - A coordenadora de outro EBI não mescla nem corrige, porque nenhum dos cadastros está ligado ao EBI dela (CA2, CA7).
  - A chefe só alcança pares em que um dos cadastros está no EBI dela (CA3). **Risco residual aceito pelo produto (revisão 2):** com isso ela alcança qualquer cadastro do país como duplicado, desde que o pareie com um dela. O cadastro alheio fica `mesclado`, some da busca da igreja de origem e perde os vínculos de responsável para o principal. Mitigação: é reversível, auditado, e a chefe da igreja de origem também consegue desfazer (o duplicado está ligado a ela).
- **Troca para `null`.** A coordenadora que põe `null` na igreja de um cadastro da própria igreja sem presença válida no EBI perde o acesso a ele; depois só o `super_admin` corrige. Aceito: é auditado e reversível pelo `super_admin`.
- **Presença como chave de escopo.** Lançar presença exige `presenca.registrar` **na sessão**. Isso não dá acesso legítimo **ao participante**: a busca da sessão (`ConsultaParticipante::buscar`) não filtra por território, e `RegistradorPresenca::registrar` só confere a elegibilidade etária. Por isso:
  - a presença só liga cadastro sem igreja de referência (CA12);
  - cadastro de outra igreja continua com a chefe da igreja dele, ou com o `super_admin`.

  Risco residual: a coordenadora lança presença para um cadastro sem igreja vindo de outro lugar e passa a poder corrigi-lo. É aceito, porque esse cadastro não pertence a nenhuma igreja, e a correção é auditada com `antes`.
- **Troca de igreja.**
  - Para uma igreja nova, ela precisa estar no escopo do ator.
  - Para `null`, só passa quando a igreja atual já estava no escopo. Isso decorre de `ligado`.
- **Acesso vertical.** `adm_igreja`, `adm_regional`, `adm_setor`, o auxiliar e o responsável não têm `participante.mesclar` nem `participante.corrigir` e recebem `403` do gate da rota.
- **`super_admin`.** O curto-circuito em `ligado` segue o bypass que `permite` já tem.
- **Auditoria.**
  - `participante.corrigido` passa a gravar motivo, `antes`, campos alterados e escopo.
  - `participante.mesclado` e `participante.mesclagem_desfeita` não mudam.
  - `presenca.lida` é um evento novo.
- **Códigos de erro.**
  - Leituras: 404 → 403 → 422.
  - `PATCH`: corpo inválido (422) → 404 → 403 → `nada_a_corrigir` → `igreja_fora_do_escopo` → 409. O 422 do corpo vem antes do 404, mas não revela se o participante existe.
- **Tamanho dos textos.** `motivo` e `finalidade` são limitados a 255 caracteres, com resposta `422`, em vez de `500` no PostgreSQL.
- **Idempotência.** O `PATCH` fica sob `IdempotenciaMiddleware`.

## Riscos e mitigação

| Risco | Mitigação |
|---|---|
| A finalidade obrigatória quebra as chamadas existentes às duas rotas | O único cliente é o portal, ajustado na T6. Os testes Pest são ajustados na T5. |
| `GET /sessions/{id}/attendance` passa a gravar. `Presenca.tsx` recarrega a lista depois de cada registro e de cada estorno (linhas 164 e 186), então cada confirmação grava dois eventos (`presenca.registrada` e `presenca.lida`) e disputa duas vezes o `lockForUpdate` da cadeia de auditoria com as auxiliares | Aceito nesta entrega: o volume de uma sessão é de dezenas de registros. Se a disputa aparecer, a T6 pode passar a acrescentar a presença retornada pelo `POST` à lista local, sem recarregar. Deixe um comentário `ponytail:` em `carregar()` dizendo isso. |
| `ebisComPresencaValida` roda uma consulta por cadastro sem igreja | São no máximo 2 por requisição, com índice em `participante_id`. |
| A mudança de assinatura de `GerirParticipante::corrigir` quebra um chamador | Não há chamador (grep `->corrigir(` em `backend/app`: só `SessaoController`, que usa outra classe). |
| Refatorar `cobreTerritorio` muda as leituras | `AutorizacaoParticipanteTest`, `MesclagemTest` e `EstornoHistoricoTest` continuam verdes. Só `EstornoHistoricoTest` ganha a `finalidade`. |
| Limite de 255 numa finalidade que hoje passaria nas rotas antigas | O portal envia valores curtos. Antes, um valor acima de 255 já quebrava com `500` no PostgreSQL. |

## Respostas à revisão

Rodada 1 (`05-revisao.md`):

| Item | Resposta |
|---|---|
| B1: a presença dá escopo sobre qualquer cadastro | **Aceito.** Decisão do produto: a presença só liga cadastro sem igreja de referência. O algoritmo de `ligado` mudou, e o texto da mitigação foi corrigido (acesso à sessão ≠ acesso ao participante). Entraram o CA12 e os casos M5b e C9. A opção do revisor (bloquear só a troca de igreja) foi descartada pelo produto, porque ainda deixaria alterar nome e data. |
| B2: `motivo` e finalidade acima de 255 caracteres | **Aceito.** `motivo` vai a `max:255` no request e no OpenAPI. A finalidade acima de 255 responde `422 finalidade_invalida` no helper. A migração para `text` foi descartada: a premissa "sem migração" continua valendo. |
| B3: `forgetGuards` e M6 | **Aceito.** A regra está em "Dados e ambiente" do plano de testes. M6, M7 e M9 montam a mesclagem pelo serviço (`GerirMesclagem::mesclar`). |
| R: `super_admin` "passa sempre" | **Aceito.** O curto-circuito é explícito em `ligado`, e entraram o CA13 e o caso C10. |
| R: casos faltando em C7 e CA5 | **Aceito.** O C7 passou a cobrir o responsável, e entraram M9 (chefe desfaz sem nenhum ligado) e M10 (desfazer com ligação só por presença). |
| R: intervalo 171–185 | **Aceito** na T5. |
| R: `Idempotency-Key` | **Aceito.** "Uma chave nova por requisição" está em "Dados e ambiente". |
| R: `participante.corrigido` com `escopo = null` | **Aceito.** O controller passa o escopo de atuação ao serviço. Os outros eventos de `Participantes` com `escopo = null` ficam como dívida, em "Fora do escopo". |
| R: custo da leitura que vira escrita | **Aceito** como risco registrado. A decisão foi manter a recarga, e o caminho de evolução está descrito. |
| R: ordem de erros no `PATCH` | **Aceito.** O texto foi corrigido na seção 2, em "Segurança" e na T4. |
| R: "Pronto quando" da T7 | **Aceito.** O critério agora é por arquivo. |
| O: o build quebra entre a T1 e a T6 | **Aceito.** O aviso está na T1. |
| O: intervalos diferentes de `AprovacaoEscalaTest.php` | **Aceito.** Unificado em 236–244. |
| O: cenário T1 ambíguo | **Aceito.** O texto foi reescrito. |
| O: `PATCH` sem mudança real | **Aceito.** A comparação é feita antes de gravar e responde `422 nada_a_corrigir`. |

Nenhum item foi recusado.

Rodada 2 (decisões do produto em 26/11/2026):

| Item | Resposta |
|---|---|
| B: a correção é apagada ao desfazer a mesclagem | **Aceito, opção (a).** A correção do principal também atualiza `snapshot_mesclagem['principal']`. Caso C11. |
| R: o M10 não testa a ligação por presença | **Aceito.** O par foi invertido: principal da igreja A, duplicado sem igreja com presença em A. |
| R: a chefe sem desfazer a própria mesclagem | **Aceito como comportamento.** Registrado em "Decisões". Caso M11. |
| R: o `super_admin` mesclando | **Aceito.** Caso M12. |
| R: texto do CA-27 em `criterios-de-aceite.md` | **Aceito.** Entrou na T7. |
| R: risco da chefe alcançar cadastros do país | **Aceito como risco residual**, registrado em "Segurança". |
| O: header do `super_admin` | **Aceito.** Fixado em "Dados e ambiente". |
| O: guarda de contexto nulo | **Aceito.** Fica no topo de `update`. |
| O: extrair `$aplicados` | **Aceito.** |
| O: C1, C5 e C6 sem conferência de evento | **Aceito.** Os casos foram reforçados. |
| O: troca para `null` faz perder o acesso | **Aceito como risco**, registrado em "Segurança". |
