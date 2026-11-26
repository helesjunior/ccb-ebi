# Design — Escalas: equipes da coordenadora e pedido de vaga (E07, evolução)

**Data:** 24/11/2026 (rev. 3, 25/11/2026: base nova, sem migração) · **Épico:** E07 (evolução) · **Base:** [`2026-11-20-escalas-e07-design.md`](2026-11-20-escalas-e07-design.md), ADR-0010, ADR-0033 · **Requisitos:** RF-07 · **Critérios mantidos:** CA-12, CA-20, M-06

## Intenção e por quê

O E07 entregou a escala por sessão, mas assumiu "a equipe do EBI" como um bloco só: a coordenadora escolhe qualquer pessoa com vínculo no EBI (`GET /ebis/{id}/team`) e, na falta de alguém, o membro pede troca e a **chefe** escolhe o substituto. O produto corrigiu o modelo:

1. Cada coordenadora tem **a sua equipe** de auxiliares, que ela mesma monta e mantém. Uma auxiliar pode servir a mais de uma coordenadora (voluntariado).
2. Uma sessão é atendida por **uma escala**, montada a partir da equipe de **uma** coordenadora.
3. No dia, a escala pode **crescer**: mais auxiliares, inclusive outra coordenadora atuando como auxiliar.
4. Na falta, a coordenadora **abre uma vaga**; auxiliares do EBI **se oferecem**; a **coordenadora dona da escala escolhe**; a chefe **só é notificada**, sem nova aprovação.
5. A vaga aberta é visível a todos os auxiliares do EBI, não só às de outras equipes.
6. Igreja pequena: uma coordenadora com todas as auxiliares atende todas as sessões.

Sem isso, a coordenadora não tem "a minha equipe" para montar a escala, depende da chefe para cobrir toda falta, e qualquer acréscimo no dia derruba a aprovação (seção 5 do ADR-0033: "alterar a escala volta a prevista"). Com a sessão já aberta, isso tira a escala do M-06 e o aviso de equipe mínima volta ao campo digitado.

**Caminho (decidido com o produto):** o sistema **não está em produção** (só dados de demonstração). Não há escala real a migrar nem histórico a preservar: equipe e vaga entram como parte do modelo, e toda escala nasce de uma equipe. Ficam a `escala` por sessão, a aprovação, o conflito, a presença da equipe e o M-06.

**Escopo desta entrega (decisão do produto, rodada 2):** equipes + montagem a partir da equipe + fluxo completo de vaga (abrir, oferecer, retirar oferta, escolher, cancelar, painel "Vagas do EBI"), mais a correção de regressão da tela de Presença (decisão 11). O resto do que a revisão levantou está em **Fora do escopo desta entrega (backlog)**.

## Objetivos

1. Cadastro de **equipe** por coordenadora e por EBI, com membros mantidos pela própria coordenadora. Uma pessoa pode estar em 1..N equipes.
2. Toda escala aponta a **equipe que a atende** (`escala.equipe_id`, obrigatório). A coordenadora dona dessa equipe é a **dona da escala**.
3. Montar a escala **a partir da equipe**: a tela pré-seleciona a dona e os membros da equipe e deixa incluir outras pessoas do EBI.
4. **Acréscimo não derruba a aprovação:** incluir membro numa escala aprovada mantém a aprovação inteira (status, aprovador, data e divisor).
5. **Vaga:** a dona (ou a supervisão) abre; auxiliares do EBI se oferecem; **só a dona escolhe**; a escolhida entra `confirmado`; a chefe vê no painel, sem aprovar.
6. Quem se oferece **não pode estar em conflito de horário** (bloqueio só neste fluxo).
7. Corrigir a tela de Presença, que hoje busca a escala pelo id da sessão.

## Não-objetivos (cortados e por quê)

- **Migração de dados / escala sem equipe:** a base é nova; dados de demonstração são recriados (`migrate:fresh --seed`; nenhum seeder cria escala hoje). Um estado "legado" seria regra a mais sem nenhum dado real que precise dela.
- **Notificação externa** (e-mail, push, WhatsApp): o módulo `Notificacoes` está vazio e depende de RF-15/E12. "Notificar a chefe" = painel **Vagas do EBI** + evento de auditoria. Ver backlog (e).
- **Escala em lote / recorrente:** continua uma escala por sessão; a equipe já reduz a montagem a um clique.
- **Aceite da auxiliar ao entrar numa equipe:** a equipe não dá acesso (o acesso vem do `vinculo_acesso`), então não há o que consentir.
- **Vaga na função coordenadora:** a vaga é sempre `auxiliar`. A falta da própria coordenadora segue o fluxo de troca (a chefe decide). Com a sessão já aberta, ver backlog (a).
- **Equipe valendo para vários EBIs:** a equipe é de uma coordenadora **num EBI**.
- **Excluir equipe / histórico de composição:** sem exclusão; a composição é o estado atual e entradas/saídas ficam na auditoria.
- **"Minhas equipes" para a auxiliar** (`GET /me/teams`): não pedido.
- **Mudar M-06, equipe mínima, presença da equipe ou o fluxo de troca:** ficam como estão, salvo as integrações da decisão 7.
- **Mudar a regra de vigência na data da sessão** (hoje `fim >= data`, ver decisão 6).

## Anti-objetivos

- A equipe **restringir** quem pode ser escalado. A equipe é ponto de partida da montagem, não uma cerca.
- A chefe virar aprovadora da vaga por outro caminho (botão, bloqueio, estado "aguardando chefe") — ou **escolher a oferta no lugar da dona**.
- Uma pessoa preencher vaga em sessão sobreposta a outra em que já está escalada.
- Ampliar `escala.ler` para cumprir a visibilidade da vaga.
- Confiar no cliente: dona da escala, elegibilidade e conflito são checados no servidor, dentro da trava da escala.

## Restrições

- **Fronteira de módulos (ADR-0002):** equipe e vaga ficam no módulo `Escalas`. Vínculo e papel vêm de `Identidade` pelo contrato `ConsultarEquipeEbi`; sessão pelo `ConsultarSessao`. Nenhuma FK para tabela de outro módulo além das que o E07 já tem (`escala.sessao_id`).
- **OpenAPI antes do código**, `endpoints.md` com "no OpenAPI", `schema.d.ts` regenerado.
- **Autorização:** papel × operação na `AutorizadorOperacao::MATRIZ` + escopo (`AutorizacaoEscala::garantir`); regra de **dona** no serviço (como `QuemAprova`).
- **Atomicidade:**
  - Escritas que mexem em escala, vaga ou oferta rodam na `TravaEscala` (lock da linha `escala`), com guardas reavaliadas depois do lock.
  - A `TravaEscala` envolve o callback em `DB::transaction` (`TravaEscala.php:25`): **qualquer exceção lançada dentro dele desfaz tudo**. Quando uma operação precisa gravar algo (ex.: cancelar uma vaga) **e** responder erro, o callback devolve um resultado e o erro é lançado **depois do commit** (decisão 5).
  - Equipe **não** usa a `TravaEscala` (não há escala a travar): transação própria, e as violações de `UNIQUE` são traduzidas para 409 (padrão de `GerenciadorEscala::criar`). Nada de escala fictícia para lock.
- **Auditoria (RN-10):** cada transição gera evento, metadados só com ids.
- **Testes:** Pest (SQLite nos testes comuns; `lockForUpdate` não tem efeito no SQLite, então concorrência é testada em PostgreSQL — decisão 12), Vitest no frontend. O schema tem de subir nos dois.
- **Dados reais do código hoje:**
  - `PAPEIS_POR_FUNCAO` está **duplicado** em `GerenciadorEscala.php:34-37` e `AvaliadorEquipe.php:20-23`.
  - `GerenciadorEscala::salvarAlteracao` volta a escala a `prevista` em **qualquer** alteração, **limpa** `aprovado_por/em`, **recopia** o divisor do EBI e cancela trocas `pendente`.
  - `retirarMembro` faz **delete físico** do `escala_membro`. As FKs do E07: `escala_membro.escala_id` `cascadeOnDelete`, `substitui_membro_id` `nullOnDelete`, `solicitacao_troca_escala.escala_membro_id` `cascadeOnDelete`, `substituto_membro_id` `nullOnDelete`. `usuario_id` nunca tem FK (outro módulo).
  - Incluir membro já bloqueia sessão encerrada/cancelada (`GerenciadorEscala.php:117`, `373-383`); confirmar aceita `prevista` e `aberta`.
  - "Vínculo de equipe" hoje = `vinculo_acesso` com papel de equipe no EBI ou na igreja (`ConsultarEquipeEbi`, `PAPEIS_EQUIPE`). `equipe()` lista só vínculos com `fim` nulo; `vigencias()` traz o histórico; a comparação na data usa `fim >= data` (`AvaliadorEquipe.php:45`).
  - `GET /ebis/{id}/team` exige `escala.gerenciar` (`EquipeEbiController.php:21-27`).
  - `Presenca.tsx:108-112` e `:206-208` chamam `obterEscala(sessaoId)` → `GET /schedules/{id}`, que procura **escala** por esse id (`ConsultaEscala.php:41-45`). Como os ids são diferentes, a busca volta 404 e a tela fica sem o bloco da equipe.
  - `ListaNav.tsx` já tem um item `equipe` ("Equipe": usuários e convites).
  - `EscalaFactory` cria escala sem equipe e é usada pelos testes do E07 (ex.: `TrocaEscalaTest`, `CoberturaEscalaTest`).
  - `matriz-de-autorizacao.md:57` diverge do código em dois pontos: dá `escala.ler` ao auxiliar (o código não dá) e classifica `GET /ebis/{id}/team` sob `escala.ler` (o controller exige `escala.gerenciar`).
  - O ADR-0033 tem trechos normativos que divergem do código (decisão 14).

## Abordagens consideradas

**Onde fica a equipe:**

- **A — Tabela própria `equipe` + `equipe_membro` no módulo `Escalas` (escolhida).** Modela direto "a coordenadora tem a sua equipe; a auxiliar está em 1..N equipes". Não mexe em `vinculo_acesso`.
- **B — Novo escopo/papel em `vinculo_acesso`.** Misturaria acesso com organização do voluntariado e espalharia mais um tipo de escopo pelas listas fixas de papéis.
- **C — Equipe derivada de quem já serviu com a coordenadora.** Zero cadastro, mas a coordenadora não controla a equipe e um reforço de um dia vira membro para sempre.

**`escala.equipe_id` obrigatório ou opcional:**

- **Obrigatório (NOT NULL), escolhido.** Toda escala tem dona; some o ramo "escala sem dona" de cada regra (quem edita, quem abre vaga, quem escolhe, o que a tela mostra).
- **Opcional (nullable).** Descartado: só se justificava para escalas antigas, e não há escala real. Manter exigiria regras e erros para um estado que ninguém cria.

**Como cobrir a falta:**

- **Vaga com ofertas, escolhida pela dona (escolhida, decisão do produto).**
- **Estender a troca.** Descartada: a troca nasce de um pedido do membro e é decidida pela chefe (ADR-0033 §2); a falta nem sempre começa com pedido de troca.
- **Só incluir direto.** Já existe; não atende "auxiliares se oferecem" nem a visibilidade da vaga.

**A equipe restringe a escala?** Não; só pré-seleciona (escolhida). Restringir criaria duas regras de elegibilidade sem proteção real.

**Quem escolhe a oferta?** Só a dona (decisão do produto, rodada 2). Descartado: chefe/`adm_igreja` escolherem também — a escolha é a decisão que o produto deu à dona, e a chefe "só é notificada".

**Como responder 409 depois de cancelar uma vaga:** devolver resultado do callback e lançar depois do commit (escolhida), **e** cancelar a vaga já no caminho que desativa o original. Descartados: lançar dentro da trava (reverte o cancelamento); abrir uma segunda transação dentro da primeira (o lock da escala já está tomado e o efeito fica confuso).

## Decisões

### 1. Dados (módulo `App\Domain\Escalas`)

```
equipe                 id, ebi_id (index), igreja_id (index, cópia),
                       coordenadora_id (ulid, sem FK — usuário de Identidade),
                       criado_por, timestamps
                       UNIQUE (ebi_id, coordenadora_id)

equipe_membro          id,
                       equipe_id  FK equipe  cascadeOnDelete,
                       usuario_id (ulid, index, sem FK — usuário de Identidade),
                       incluido_por, timestamps
                       UNIQUE (equipe_id, usuario_id)

escala                 + equipe_id  NOT NULL FK equipe  restrictOnDelete, index

vaga_escala            id,
                       escala_id            FK escala         cascadeOnDelete,
                       funcao ('auxiliar'),
                       substitui_membro_id  nullable FK escala_membro  nullOnDelete,
                       substituido_usuario_id (nullable ulid, gravado na abertura),
                       observacao (nullable, 280, pública),
                       status (aberta | preenchida | cancelada),
                       aberta_por, encerrada_por (nullable), encerrada_em (nullable),
                       motivo_encerramento (nullable: codigo fixo, ver decisão 7),
                       timestamps
                       index (escala_id, status)

oferta_vaga_escala     id,
                       vaga_id           FK vaga_escala     cascadeOnDelete,
                       usuario_id (ulid, sem FK),
                       status (pendente | aceita | retirada | descartada),
                       membro_criado_id  nullable FK escala_membro  nullOnDelete,
                       timestamps
                       index (vaga_id, status), index (usuario_id, status)
```

**Por que cada `onDelete`** (padrão da migration `2026_11_20_100002`):

| FK | Regra | Motivo |
|---|---|---|
| `equipe_membro.equipe_id` | cascade | membro não existe sem a equipe; equipe não é excluída nesta entrega, mas a regra fica coerente |
| `equipe_membro.usuario_id`, `equipe.coordenadora_id`, `oferta.usuario_id` | sem FK | usuário é de `Identidade` (ADR-0002), igual a `escala_membro.usuario_id`; desligamento é por vínculo, não por exclusão |
| `escala.equipe_id` | restrict | escala aponta a dona; apagar equipe com escala é erro, nunca efeito colateral |
| `vaga_escala.escala_id` | cascade | igual a `escala_membro.escala_id` |
| `vaga_escala.substitui_membro_id` | **nullOnDelete** + `substituido_usuario_id` | `retirarMembro` apaga o membro; a vaga preenchida/cancelada continua dizendo **quem** foi substituído pelo id do usuário (o nome é resolvido na leitura por `ConsultarEquipeEbi::nomes`, sem gravar nome em texto) |
| `oferta.vaga_id` | cascade | oferta não existe sem a vaga |
| `oferta.membro_criado_id` | **nullOnDelete** | retirar o membro criado pela oferta não apaga a oferta; `usuario_id` e `status = aceita` preservam quem preencheu |

- **`escala.equipe_id` NOT NULL.** Como a base é recriada, a coluna nasce obrigatória; o ambiente de demonstração é refeito com `migrate:fresh --seed`. A `EscalaFactory` passa a criar a equipe (com a coordenadora como dona) por padrão, para os testes do E07 continuarem montando escala sem mudança de chamada.
- **Não confiar em cascata para regra de negócio:** retirar um membro com vaga aberta para ele **cancela a vaga antes** do delete, na mesma trava (decisão 7). A `nullOnDelete` só protege o registro da vaga.
- **Uma equipe por coordenadora por EBI.** A chefe também pode ter equipe.
- Membro de equipe: vínculo de equipe **vigente hoje** com papel aceito na função `auxiliar` (`auxiliar`, `coordenadora`, `coordenadora_chefe`) no EBI ou na igreja. Revogado depois: continua na lista, marcado `sem_vinculo` (calculado), e não é pré-selecionado.
- Dona de equipe: vínculo vigente hoje de `coordenadora` ou `coordenadora_chefe` no EBI ou na igreja. Revogada: a equipe continua (as escalas dela continuam apontando para ela), aparece "coordenadora sem vínculo" e não serve para escala nova.
- **`PAPEIS_POR_FUNCAO` num lugar só** (constante pública, ex.: `EscalaMembro::PAPEIS_POR_FUNCAO`), usada por escala, equipe, troca e vaga. É o primeiro passo da entrega.

### 2. Montagem a partir da equipe

- `POST /sessions/{id}/schedules` passa a exigir `equipe_id` **para todos** os papéis que criam escala:
  - ausente → `422 equipe_obrigatoria` (para a coordenadora sem equipe, a tela oferece "Criar minha equipe");
  - **coordenadora:** tem de ser a equipe **dela** naquele EBI → senão `422 equipe_invalida`;
  - **chefe e `adm_igreja`:** qualquer equipe do EBI da sessão (podem criar a equipe de uma coordenadora antes, com `equipe.gerenciar`);
  - equipe de outro EBI ou com dona sem vínculo vigente → `422 equipe_invalida`.
- A composição continua vindo em `membros`. **A tela** pré-seleciona a dona (`coordenadora`) e os membros com vínculo vigente (`auxiliar`). O servidor não expande a equipe sozinho.
- **A equipe de uma escala não muda depois de criada.** `PATCH /schedules/{id}` continua só com a estimativa; trocar a dona é backlog (c).
- Uma escala por sessão: `UNIQUE (sessao_id)`; a segunda tentativa recebe `409 escala_existente`.

### 3. Dona da escala e quem escreve

- **Dona** = `equipe.coordenadora_id` da `escala.equipe_id`. Toda escala tem dona.
- Serviço novo `DonaDaEscala` (ao lado de `QuemAprova`), que recebe o `ContextoDecisao`:

| Operação | Coordenadora | Chefe / `adm_igreja` |
|---|---|---|
| estimativa, incluir, retirar, mudar função, presença da equipe | dona | qualquer escala do EBI |
| abrir vaga, cancelar vaga | dona | qualquer escala do EBI |
| **escolher oferta** | **dona** | **não** (`403 nao_e_dona_da_escala`) |

- Recusa → `403 nao_e_dona_da_escala`, com a mensagem dizendo quem é a dona.
- Leitura não muda: `escala.ler` lê todas as escalas do EBI.
- A escolha da oferta exige a dona **atuando no contexto de coordenadora ou chefe no EBI/igreja da escala** e com vínculo vigente hoje. Se a dona foi revogada, ninguém escolhe; a supervisão cancela a vaga e inclui alguém direto (Riscos).

### 4. Acréscimo preserva a aprovação inteira

Emenda à **seção 5 do ADR-0033** ("alterar a escala depois de aprovada"):

| Alteração | Escala aprovada |
|---|---|
| Incluir membro (manual) | **mantém** `status`, `aprovado_por`, `aprovado_em` e `participantes_por_auxiliar` |
| Vaga preenchida (acréscimo ou substituição) | **mantém** tudo acima |
| Retirar membro, mudar função, mudar estimativa | volta a `prevista` e recopia o divisor (como hoje) |

- **Por quê:** acrescentar gente só aumenta a cobertura; o mínimo que a chefe aprovou continua conferido com o **mesmo divisor**. Recopiar o divisor num acréscimo poderia deixar a escala aprovada abaixo do mínimo sem ninguém ter mexido na regra.
- Implementação: `salvarAlteracao` ganha um modo "acréscimo" que **não** toca em `status`, `aprovado_*` nem `participantes_por_auxiliar`, e **não** cancela trocas pendentes. `escala.alterada` sai com `voltou_prevista = false`.
- Membro incluído manualmente entra `escalado` (precisa confirmar; não conta no M-06 até lá). Sessão encerrada/cancelada continua recusando inclusão (`GerenciadorEscala.php:117`).

### 5. Vaga e oferta — ciclo

```
vaga:    (abrir) --> aberta --escolher oferta (dona)--> preenchida
                     aberta --cancelar (dona/supervisão)--> cancelada
                     aberta --original retirado / trocado / mudou de função--> cancelada
                     aberta + sessão não está prevista/aberta  => "expirada" (derivado)

oferta:  (oferecer) --> pendente --escolhida--> aceita
                        pendente --retirar (própria pessoa)--> retirada
                        pendente --vaga preenchida por outra / vaga cancelada--> descartada
```

**Abrir** — `POST /schedules/{id}/openings` `{ substitui_membro_id?, observacao? }`:
- Quem: decisão 3.
- Dona sem vínculo vigente → `409 dona_sem_vinculo` (ninguém poderia escolher a oferta).
- Sessão `prevista` ou `aberta` → senão `409 sessao_em_estado_invalido`.
- Escala `prevista` ou `aprovada`.
- `substitui_membro_id`, se informado: membro **ativo** desta escala, **na função `auxiliar`**, sem outra vaga `aberta` → senão `422 membro_invalido` / `409 vaga_existente`. Grava `substituido_usuario_id`. Sem ele, a vaga é **acréscimo**.
- `observacao` é **pública** (decisão 9): a tela avisa "visível a todos os auxiliares do EBI". Não há campo de justificativa interna nesta entrega.

**Oferecer** — `POST /schedule-openings/{id}/offers`, sem corpo, pela própria pessoa. Na trava, nesta ordem:
1. **Autorização atual** (decisão 6): vínculo de equipe vigente **hoje** no EBI/igreja da sessão com papel aceito na função `auxiliar` → senão `404` (não revela vaga de EBI alheio).
2. Vaga `aberta` e sessão `prevista`/`aberta` → senão `409 vaga_indisponivel`.
3. **Elegibilidade na data da sessão** (mesma conta do `AvaliadorEquipe`) → senão `422 membro_inelegivel`.
4. Não é membro ativo desta escala → `422 membro_ja_escalado`. Não é a dona → `422 membro_ja_escalado` (ela já está na escala).
5. **Sem conflito** com outra escala em que esteja ativa → `422 conflito_de_horario` com os conflitos.
6. Uma oferta `pendente` dela por vaga → `409 oferta_existente`.

**Retirar oferta** — `POST /schedule-offers/{id}/withdraw`: só a própria pessoa (outro → 404), só `pendente` (→ `409 oferta_ja_decidida`).

**Escolher** — `POST /schedule-offers/{id}/accept`, **só a dona** (decisão 3). Na trava, nesta ordem:
1. Ator é a dona → senão `403 nao_e_dona_da_escala` (inclusive chefe e `adm_igreja`).
2. Oferta `pendente` → senão `409 oferta_ja_decidida`; vaga `aberta` → senão `409 vaga_indisponivel`; sessão `prevista`/`aberta` → senão `409 vaga_indisponivel`.
3. Se a vaga substitui alguém: o original **segue ativo e na função `auxiliar`**. Se não → a vaga é **cancelada** (`motivo_encerramento = original_indisponivel`), as ofertas pendentes viram `descartada`, e o callback **devolve** `Indisponivel`; o serviço lança `409 vaga_indisponivel` **depois** que a `TravaEscala` comitou. Na prática quase não acontece, porque retirar, trocar e mudar função já cancelam a vaga (decisão 7); a checagem cobre o que escapar.
4. Revalida a pessoa da oferta: autorização atual, elegibilidade na data, não escalada, **sem conflito** → senão `422` correspondente, **nada gravado** (a oferta segue pendente; a dona pode escolher outra).

Efeito (mesma trava):
1. Cria `escala_membro` da pessoa, função `auxiliar`, **`confirmado`, `confirmado_em = agora`**, `substitui_membro_id` = original (se houver).
2. Original (se houver) → `substituido`; troca pendente dele → `cancelada` (decisão 7).
3. Vaga → `preenchida`; oferta → `aceita` com `membro_criado_id`; demais ofertas → `descartada`.
4. Aprovação da escala intacta (decisão 4). Nenhuma ação da chefe.

- **Por que entra `confirmado`** (confirmado pelo produto): a oferta é a pessoa dizendo "eu vou" e a dona aceitou; deixá-la `escalado` faria uma substituição numa escala aprovada **baixar** o M-06 até ela confirmar de novo.

**Cancelar vaga** — `POST /schedule-openings/{id}/cancel`, quem: decisão 3; só `aberta` → senão `409 vaga_indisponivel`. Ofertas pendentes → `descartada`. `motivo_encerramento = cancelada_pela_gestao`.

**Padrão "gravar e responder erro":** todo método que pode cancelar e recusar na mesma chamada usa o retorno do callback (ex.: `ResultadoVaga::indisponivel()`), e o 409 é lançado fora da `TravaEscala`. Teste obrigatório: conferir o **estado persistido** e a **resposta**.

### 6. Autorização atual × elegibilidade na data

Duas perguntas diferentes, que não se misturam:

- **Autorização atual** (listar vagas, oferecer, aceitar oferta, ser membro de equipe, ser dona): vínculo de equipe **vigente hoje** = `vinculo_acesso` com `fim` nulo (o mesmo recorte de `ConsultarEquipeEbi::equipe`). Revogada hoje (`fim = hoje`) → já não lista, não oferece, não escolhe.
- **Elegibilidade na data da sessão** (contar para equipe mínima, M-06, aviso, aprovar): a mesma regra do E07 (`AvaliadorEquipe::vigenteEm`, `fim >= data`). Revogar alguém hoje não apaga a participação dela numa sessão já ocorrida.
- Oferecer e escolher exigem **as duas**.
- "Vínculo de equipe" é o **vínculo de acesso**, não `equipe_membro`: auxiliar do EBI **sem nenhuma equipe cadastrada** vê vagas e pode se oferecer.
- Divergência registrada para o construtor, sem mudar nesta entrega: o spec do E07 diz `fim > data`, o código faz `fim >= data`. Os testes desta entrega fixam o comportamento atual.

### 7. Integração com troca, retirada, mudança de função e conflito

Todas na mesma trava da escala:

| Evento | Vaga `aberta` que substitui o membro | `motivo_encerramento` |
|---|---|---|
| Retirar o membro (`DELETE /schedule-members/{id}`) | cancelada **antes** do delete; ofertas `descartada` | `membro_retirado` |
| Mudar a função do membro | cancelada; ofertas `descartada` | `funcao_alterada` |
| Troca do membro aprovada pela chefe (vira `substituido`) | cancelada; ofertas `descartada` | `membro_trocado` |
| Vaga preenchida com troca pendente do original | — (a troca é que é cancelada: `motivo_decisao = "vaga preenchida"`, evento `escala.troca_cancelada`) | — |

- Alterações que voltam a escala a `prevista` **não** cancelam vagas por si só.
- **Nunca sobra escala sem coordenadora por causa de vaga:** a vaga só substitui membro na função `auxiliar` (na abertura) e o aceite revalida a função; mudar o membro para `coordenadora` cancela a vaga.
- **Retirar o membro criado pela oferta:** delete normal; a oferta fica `aceita` com `membro_criado_id = null` e a vaga continua `preenchida` (quem preencheu vem de `oferta.usuario_id`).
- **Conflito:** o `DetectorConflito` continua só avisando na montagem, aprovação e confirmação (CA-12). O bloqueio vale **só** na oferta e na escolha. Ofertas pendentes não contam como conflito.
- **Corrida entre escalas diferentes** (a mesma pessoa escolhida em duas vagas sobrepostas no mesmo instante): **risco aceito**. A trava é por escala; o conflito aparece depois como aviso nas duas, que é o comportamento normal do sistema.

### 8. Leitura e o "aviso" à chefe

- **Painel "Vagas do EBI"** — `GET /ebis/{id}/schedule-openings?inicio=&fim=&status=` (`escala.ler`). É o "notificar a chefe": lista por data, sem botão de aprovar.
- **Detalhe da escala** (`GET /schedules/{id}`) ganha `equipe: { id, coordenadora_id, coordenadora_nome }`, `pode_gerir: boolean`, `pode_escolher_oferta: boolean` e `vagas: [...]` (projeção da decisão 9).
- **`GET /me/schedule-openings?inicio=&fim=`** (próprio usuário, teto de 92 dias como `/me/schedules`): vagas `aberta` e não expiradas de todos os EBIs em que a pessoa tem vínculo de equipe **vigente hoje** (decisão 6), exceto as escalas em que ela já é membro ativo. Vale para auxiliares **e coordenadoras** do EBI (confirmado pelo produto). Com `minha_oferta` e os `conflitos` dela.
- **Equipes:** `GET /ebis/{id}/equipes` e `GET /equipes/{id}` com `escala.ler`.
- **Não se amplia `escala.ler`.** A auxiliar continua sem ele; vê vagas só pela rota `/me`.

### 9. Privacidade — projeções da vaga

Regra confirmada pelo produto: **nomes de quem se ofereceu só para a dona e a supervisão do EBI** (`coordenadora_chefe`, `adm_igreja`). Os demais leitores com `escala.ler` — `ministerio_local`, `adm_setor`, `adm_regional`, `super_admin` e coordenadoras que não são donas — veem **só a quantidade de ofertas e quem foi escolhido**.

| Campo | Cartão público (`/me/schedule-openings`) | Demais leitores com `escala.ler` (painel, detalhe) | Dona e supervisão (`pode_gerir`) |
|---|---|---|---|
| data, horário, EBI, nome da dona, `observacao`, tipo (acréscimo/substituição), status | sim | sim | sim |
| quem é substituído | **não** | sim (já é membro da escala nominal que esse leitor vê) | sim |
| quantidade de ofertas pendentes | não | sim | sim |
| nomes e conflitos de quem se ofereceu (pendente, retirada ou descartada) | só a própria (`minha_oferta`) | **não** | sim |
| quem foi escolhido (oferta aceita) | não | sim (já é membro da escala) | sim |

- **Membro da escala que lê pela exceção de propriedade** (`ehMembroVigente`, sem `escala.ler`) recebe as vagas na projeção do **cartão público**.
- `motivo_encerramento` é código fixo (não texto livre), mostrado só a quem tem `escala.ler`.
- A supervisão vê nomes das ofertas, mas não escolhe (decisão 3).

### 10. Autorização

| Operação | Mudança | Papéis | Escopo / regra no serviço |
|---|---|---|---|
| `equipe.gerenciar` | **nova** | `coordenadora`, `coordenadora_chefe` (via superconjunto), `adm_igreja` | escopo EBI; coordenadora só a **própria** equipe |
| `escala.ler` | inalterada; passa a cobrir `GET /ebis/{id}/equipes`, `GET /equipes/{id}`, `GET /ebis/{id}/schedule-openings` | como hoje | subárvore |
| `escala.gerenciar` | inalterada na matriz; regra de dona no serviço (decisão 3); cobre abrir/cancelar vaga e escolher oferta | como hoje | subárvore + `DonaDaEscala` |
| `escala.aprovar`, `troca.decidir` | inalteradas | — | — |
| oferecer, retirar oferta, `GET /me/schedule-openings` | sem operação de papel (propriedade + vínculo vigente hoje) | — | 404 fora do alcance |

- **Sem `equipe.ler`:** a leitura da equipe tem a mesma sensibilidade da escala nominal.
- **Impacto no código:** `Operacoes` + `EQUIPE_GERENCIAR`; `MATRIZ`: em `OPERACOES_COORDENADORA` (a chefe herda) e em `adm_igreja`. Nenhum papel novo: `AutoridadeDePapel`, `VisibilidadeIgreja::PAPEIS`, `PapelSeeder`, `PAPEIS_EQUIPE` não mudam. Frontend: `ListaNav.tsx` ganha `equipes-escala`; escrita nas telas obedece `pode_gerir`/`pode_escolher_oferta` vindos do servidor.
- **Quem lê equipes e vagas:** todos com `escala.ler` no território — inclusive `ministerio_local`, `adm_regional`, `adm_setor`, `super_admin` — e recebem 403 só nas escritas. Ministérios Central/Regional/Setorial e auxiliar (sem `escala.ler`) recebem 403 nos GETs de equipe e do painel.
- `matriz-de-autorizacao.md`: linhas "Equipe da coordenadora" e "Vaga: oferecer"; corrigir a linha 57 (auxiliar sem `escala.ler`; `GET /ebis/{id}/team` sob `escala.gerenciar`).

**Rotas novas:** `GET|POST /ebis/{id}/equipes`, `GET /equipes/{id}`, `POST /equipes/{id}/members`, `DELETE /equipes/{id}/members/{usuarioId}`, `POST /schedules/{id}/openings`, `POST /schedule-openings/{id}/cancel`, `GET /ebis/{id}/schedule-openings`, `GET /me/schedule-openings`, `POST /schedule-openings/{id}/offers`, `POST /schedule-offers/{id}/withdraw`, `POST /schedule-offers/{id}/accept`. **Alteradas:** `POST /sessions/{id}/schedules` (+ `equipe_id` obrigatório); escritas na escala passam pela regra de dona. `GET /ebis/{id}/team` fica, sem mudança (candidatos de fora da equipe e substituto na troca).

### 11. Tela de Presença (correção de regressão)

- `GET /sessions/{id}` passa a trazer `escala_id` (nullable: sessão pode não ter escala). A composição fica no `SessaoController`, por um método novo no contrato que ele já consome (`ConsultarEquipeEscalada::escalaDaSessao(sessaoId): ?string`), sem `Sessoes` depender de `Escalas`.
- `Presenca.tsx` busca a escala por **`sessao.escala_id`**, nunca pelo id da sessão; sem `escala_id`, não mostra o bloco da equipe.
- O bloco "Presença da equipe" aparece por `escala.pode_gerir` (decisão 8), não pela lista de papéis: leitora não dona vê a equipe em leitura.
- A equipe é recarregada ao abrir a tela, depois de encerrar a sessão e quando o envio volta `422 membro_invalido` (membro mudou por vaga/retirada), mantendo as marcações dos ids que continuam.
- Lista usa só membros ativos: o membro criado pela vaga aparece; o substituído não.

### 12. Concorrência — onde se prova

- Testes de concorrência rodam em **PostgreSQL**, com **conexões independentes** (o `lockForUpdate` não tem efeito no SQLite dos testes comuns). Grupo Pest separado (ex.: `pgsql`), executado no ambiente com PostgreSQL; pulado no SQLite.
- Pares cobertos, cada um terminando num resultado válido e com o estado persistido conferido:
  - aceite × cancelamento da mesma vaga;
  - aceite × aprovação de troca do original;
  - aceite × retirada do original;
  - aceite × inclusão manual da mesma pessoa na mesma escala;
  - dois aceites de ofertas da mesma vaga.
- Corrida entre escalas diferentes: risco aceito (decisão 7), sem teste de bloqueio.

### 13. Auditoria (RN-10)

Novos eventos, metadados só com ids: `equipe.criada`, `equipe.membro_incluido`, `equipe.membro_retirado`, `escala.vaga_aberta`, `escala.vaga_cancelada` (com `motivo_encerramento`), `escala.vaga_oferta`, `escala.vaga_oferta_retirada`, `escala.vaga_preenchida` (vaga, oferta, membro criado, usuário substituído). `escala.criada` passa a levar `equipe_id`. `escala.vaga_preenchida` é o registro formal do aviso à chefe.

### 14. Documentação na mesma entrega

- **ADR-0034 (novo) — Equipes da coordenadora e vaga de escala:** equipe por coordenadora e EBI; toda escala nasce de uma equipe (`equipe_id` obrigatório); equipe não restringe a escala; dona escreve, supervisão gerencia, **só a dona escolhe oferta**; acréscimo preserva a aprovação inteira (**emenda à seção 5 do ADR-0033**); chefe só vê; oferta bloqueada por conflito; escolhida entra confirmada; nomes de ofertas só para dona e supervisão; autorização atual × elegibilidade na data. Seção "Fora do escopo desta entrega" com os itens do backlog abaixo.
- **ADR-0033, corrigido para o código na mesma entrega:**
  - §4: o divisor é copiado na criação **e recopiado a cada alteração que volta a escala a prevista** (não é imutável desde a criação); com a emenda, o acréscimo não recopia.
  - §5: cancela trocas **pendentes** (não "abertas e aceitas"); nota apontando a emenda do ADR-0034.
  - §7: M-06 = **sessões cobertas ÷ sessões previstas** até a data de geração (não "cumprido ÷ planejado"), como `RelatorioPresenca.php:405`.
- `estados.md`, `modelo-conceitual.md`, `matriz-de-autorizacao.md`, `endpoints.md`, `openapi.yaml`, `RF-07`, `CHANGELOG.md`.

## Fora do escopo desta entrega (backlog)

Decisão do produto (rodada 2): não bloqueiam esta entrega; entram como nota no ADR-0034.

- **(a) Falta da coordenadora com a sessão aberta.** Hoje a troca só é decidida com sessão `prevista`, e a vaga é só de auxiliar. Cobrir a coordenação no meio da sessão exige outra regra (quem assume, e se a escala continua contando).
- **(b) Reforço após o encerramento aparecer no cumprido.** Incluir e abrir vaga exigem sessão `prevista`/`aberta`; quem chegou e não estava na escala não entra no planejado × cumprido (já era não-objetivo no E07).
- **(c) Transferir a dona da escala.** A equipe da escala é fixa desde a criação; não há "passar a escala para outra equipe".
- **(d) Indicador "nova atividade" para a chefe** (contador de vagas preenchidas não vistas): exige estado por leitor.
- **(e) Notificação externa** (e-mail/push/WhatsApp) da vaga aberta e do preenchimento: E12.
- Aprimoramentos da presença da equipe além da correção da decisão 11.

## Riscos

- **Regra de dona** tira de coordenadoras a edição da escala de colegas. Mensagem diz quem é a dona.
- **Dona revogada:** ninguém escolhe oferta nem abre vaga naquela escala; a supervisão edita a escala, cancela vagas e inclui alguém direto; o painel mostra "dona sem vínculo". Sem transferência de dona (backlog c).
- **`equipe_id` obrigatório quebra fixtures do E07:** a `EscalaFactory` e os testes que criam escala direto precisam da equipe. Mitigação: a factory cria a equipe por padrão.
- **Schema em SQLite × PostgreSQL:** acrescentar coluna NOT NULL com FK numa tabela existente se comporta diferente nos dois. Ver "Deixado ao construtor".
- **Entrar `confirmado` pela vaga** pula a confirmação explícita (decisão do produto).
- **Duas regras de conflito** (aviso em geral, bloqueio na oferta): intencional; precisa estar no ADR-0034.
- **Corrida entre escalas** na escolha de ofertas: aceita.
- **Vaga visível ao EBI** expõe data, horário, nome da dona e a observação a todos os auxiliares e coordenadoras. Mitigação: cartão público sem nome do substituído e sem ofertas alheias; observação marcada como pública na tela.
- **"Notificação" sem push:** a chefe só sabe se abrir o painel.
- **Ofertas pendentes de vagas expiradas** ficam no banco; listas filtram pela sessão.
- **Testes de concorrência dependem de PostgreSQL** no CI; se o pipeline só tiver SQLite, esses testes não rodam. Garantir o job.
- **Pré-requisito pulado:** se `PAPEIS_POR_FUNCAO` não for unificado antes, a vaga cria uma terceira cópia.

## Critérios de aceite (fonte dos testes)

**Equipe**
- Coordenadora cria a própria equipe; segunda vez → 409 (corrida: `UNIQUE` traduzido para 409); não cria para outra coordenadora; chefe e `adm_igreja` criam para qualquer coordenadora vigente do EBI.
- Coordenadora inclui e retira na própria equipe; na alheia → 403; pessoa sem vínculo de equipe vigente hoje → 422; repetir → 409.
- A mesma auxiliar em duas equipes ao mesmo tempo.
- Revogada aparece `sem_vinculo` e não é pré-selecionada.
- GETs de equipe: `ministerio_local`, `adm_igreja`, `adm_setor`, chefe e coordenadora **leem** no território; fora do território → 404; auxiliar e ministérios Central/Regional/Setorial → 403. Escritas por `ministerio_local` → 403.

**Montagem e dona**
- Criar escala sem `equipe_id` → 422 `equipe_obrigatoria`, para coordenadora, chefe e `adm_igreja`.
- Coordenadora com equipe alheia → 422 `equipe_invalida`; com a própria → escala com `equipe_id`. Chefe e `adm_igreja` criam com a equipe de qualquer coordenadora do EBI. Equipe de outro EBI ou com dona revogada → 422.
- Banco recusa `escala` sem `equipe_id` (NOT NULL).
- `PATCH /schedules/{id}` ignora/recusa `equipe_id` (a equipe não muda).
- Coordenadora não dona: lê; incluir, retirar, estimativa, mudar função, presença da equipe, abrir e cancelar vaga → 403 `nao_e_dona_da_escala`. Chefe e `adm_igreja` fazem tudo isso em qualquer escala do EBI.
- **Escolher oferta: chefe → 403 `nao_e_dona_da_escala`; `adm_igreja` → 403; coordenadora não dona → 403; dona → 200.**
- Abrir vaga em escala cuja dona foi revogada → 409 `dona_sem_vinculo`.
- Igreja pequena: uma equipe com todas as auxiliares monta todas as sessões do período sem ninguém de fora.

**Acréscimo**
- Mudar o divisor do EBI depois da aprovação; incluir membro manualmente e por vaga → `status`, `aprovado_por`, `aprovado_em` e `participantes_por_auxiliar` iguais; equipe mínima e M-06 calculados com o divisor da aprovação; sem nova aprovação; troca pendente não cancelada.
- Retirar, mudar função ou estimativa de escala aprovada → volta a `prevista`, recopia o divisor, cancela trocas pendentes (regressão do E07).
- Incluir com sessão encerrada/cancelada → 409.

**Vaga e oferta**
- Abrir acréscimo e substituição; substituição de membro `coordenadora` → 422; segunda vaga para o mesmo membro → 409; sessão encerrada/cancelada → 409.
- `/me/schedule-openings` mostra a vaga para: auxiliar de outra equipe, da mesma equipe, **auxiliar sem nenhuma equipe cadastrada**, outra coordenadora do EBI. Não mostra para: a dona, membro ativo da escala, pessoa de outro EBI.
- **Revogada hoje** (pela API de revogação real, `fim = hoje`): não vê a vaga e oferecer → 404; com oferta já feita, escolher → 422, nada gravado; numa sessão anterior à revogação em que ela estava escalada e confirmada, a equipe mínima e o M-06 continuam contando-a.
- Oferecer: outro EBI → 404; já na escala → 422; com sessão sobreposta em qualquer igreja → 422 `conflito_de_horario` com os conflitos; oferta pendente repetida → 409.
- Retirar oferta própria → `retirada`; de outra pessoa → 404; aceita → 409.
- Escolher: membro `auxiliar` `confirmado` com `confirmado_em`; vaga `preenchida`; demais ofertas `descartada`; aprovação intacta.
- Substituição preenchida: original `substituido`; novo com `substitui_membro_id`; troca pendente do original → `cancelada` com evento; M-06 conta o novo e não o original.
- Pessoa passou a ter conflito depois de oferecer → escolher → 422, nada gravado, oferta segue pendente.
- **Mudar função × aceite:** mudar o original para `coordenadora` cancela a vaga (`funcao_alterada`) e descarta ofertas; aceitar depois → 409; a escala continua com coordenadora.
- **Original indisponível no aceite** (simulado sem passar pelo cancelamento automático): resposta 409 `vaga_indisponivel` **e** a vaga persistida como `cancelada` (`original_indisponivel`), ofertas `descartada` — o cancelamento não é desfeito pelo 409.
- **Retirada do original:** vaga aberta para ele → `cancelada` (`membro_retirado`) antes do delete; vaga já `preenchida` cujo original é apagado → `substitui_membro_id = null`, `substituido_usuario_id` preservado, leitura mostra o nome.
- **Retirada do membro criado pela oferta:** oferta continua `aceita` com `membro_criado_id = null`; vaga continua `preenchida`; leitura mostra quem preencheu.
- Leitura posterior de vagas `preenchida` e `cancelada` funciona depois das duas retiradas acima.
- Troca aprovada para membro com vaga aberta → vaga `cancelada` (`membro_trocado`).
- Sessão encerrada com vaga aberta → "expirada"; não aceita oferta nem escolha.

**Privacidade**
- Auxiliar da escala (sem `escala.ler`) lendo o detalhe: vagas sem nome do substituído e sem ofertas alheias.
- Auxiliar externa em `/me/schedule-openings`: idem; vê só a própria oferta.
- `ministerio_local`, `adm_setor`, `adm_regional`, `super_admin` e coordenadora não dona: veem a quantidade de ofertas e quem foi escolhido; **nenhum** nome de quem se ofereceu (pendente, retirada ou descartada), nem os conflitos delas.
- Dona, chefe e `adm_igreja`: veem nomes e conflitos das ofertas.

**Aviso à chefe**
- Painel mostra a vaga aberta e depois preenchida com quem preencheu; não existe rota nem botão de aprovação de vaga; `escala.vaga_preenchida` registrado.

**Presença (regressão)**
- Sessão e escala com **ids distintos**: a tela carrega a escala por `escala_id` e mostra a equipe; sem escala → sem bloco.
- Membro criado por vaga aparece; substituído não.
- Leitora não dona (`pode_gerir = false`) vê a equipe sem o botão de registrar.
- Envio após mudança da equipe → 422 → tela recarrega e mantém as marcações válidas.

**Concorrência (PostgreSQL, conexões independentes)**
- aceite × cancelamento; aceite × troca aprovada; aceite × retirada do original; aceite × inclusão manual da mesma pessoa; dois aceites da mesma vaga — cada um termina em um resultado válido, com estado persistido coerente (nunca membro criado com vaga cancelada, nem pessoa duplicada ativa na escala).

**Autorização e regressão**
- `equipe.gerenciar` na `MATRIZ` para coordenadora, chefe e `adm_igreja`; superconjunto chefe ⊇ coordenadora verde.
- Suíte do E07 verde com os ajustes de acréscimo, de dona e da `EscalaFactory` com equipe.
- `migrate:fresh --seed` sobe em PostgreSQL; a suíte sobe o schema em SQLite.

**Frontend (Vitest)**
- `ListaNav`: "Equipes de escala" para quem tem `escala.ler`; "Equipe" inalterado.
- `Escalas`: montar exige escolher a equipe, pré-marca dona e membros e envia `equipe_id`; coordenadora sem equipe vê "Criar minha equipe"; escrita obedece `pode_gerir`; botão "Escolher" só com `pode_escolher_oferta` (chefe vê ofertas sem o botão).
- `MinhasEscalas`: vagas abertas; botão desabilitado com conflito; oferecer e retirar; vaga preenchida aparece confirmada.
- `Presenca`: casos da seção "Presença (regressão)".

**Auditoria:** cada evento da decisão 13 sai uma vez por transição, sem nomes.

## Deixado ao construtor

- Nomes de classes (`GerenciadorEquipe`, `GerenciadorVaga`, `DonaDaEscala`, `ResultadoVaga`), contratos e controllers, respeitando ADR-0002 e a `TravaEscala`.
- Onde fica a constante única `PAPEIS_POR_FUNCAO`.
- Como o schema nasce, desde que `escala.equipe_id` seja NOT NULL e suba em SQLite e PostgreSQL: migration nova que cria `equipe` e acrescenta a coluna (o ambiente é recriado com `migrate:fresh`), ou ajuste da migration do E07 — que ainda não está em produção — com a criação de `equipe` antes de `escala`. Sem migration de dados.
- Mecanismo do grupo de testes PostgreSQL e do job no CI.
- Formato exato das respostas no OpenAPI, desde que os campos citados existam.
- Textos de tela e agrupamento visual.
- Ordem sugerida: unificar `PAPEIS_POR_FUNCAO` → correção da Presença → equipe → `equipe_id` obrigatório + factory + regra de dona → acréscimo preserva aprovação → vaga/oferta → telas → docs/ADRs.

## Decisões do produto

Todas fechadas (rodadas 2 e 3): regra de dona; escolha exclusiva da dona; escopo da entrega; escolhida entra `confirmado`; vaga visível também às coordenadoras do EBI; sem migração (base nova, `equipe_id` obrigatório); nomes de quem se ofereceu só para dona e supervisão do EBI. Nenhuma decisão aberta.
