# Design — Escalas (E07)

**Data:** 20/11/2026 · **Épico:** E07 (fase 2, P1) · **Requisitos:** RF-07, ADR-0010, M-06 · **Critérios:** CA-12, CA-20 · **Fluxo:** FL-04

## Intenção e por quê

Hoje a sessão do EBI existe (Etapa 6), mas a equipe que vai atendê-la não. A coordenadora digita à mão um número em `sessao.auxiliares_escalados` só para alimentar o aviso de equipe mínima, e ninguém sabe **quem** vai estar lá, se essa pessoa confirmou, se ela também está escalada em outra igreja no mesmo horário, nem se a equipe cumpriu o planejado. O relatório (Etapa 7) deixou o M-06 de fora porque não havia escala nominal.

Esta etapa dá nome à equipe de cada sessão e fecha o ciclo do FL-04:

1. a coordenadora monta a escala da sessão;
2. o sistema confere a equipe mínima e avisa conflitos de horário;
3. a **coordenadora chefe** aprova;
4. cada membro confirma ou pede troca, e a chefe escolhe o substituto;
5. depois da sessão, a coordenadora registra quem esteve (planejado × cumprido);
6. o aviso de equipe mínima e o M-06 passam a vir da escala aprovada.

## Objetivos

1. Criar a atribuição **coordenadora chefe**, uma por EBI (ADR-0010), como papel de acesso.
2. Montar a escala de uma sessão: membros com função (`coordenadora` ou `auxiliar`) e a estimativa de participantes.
3. Calcular a equipe mínima: 1 coordenadora + ⌈estimativa ÷ `participantes_por_auxiliar`⌉ auxiliares (ADR-0010).
4. Sinalizar conflito de horário do mesmo membro em sessões sobrepostas, inclusive em outra igreja, **antes** da aprovação e da confirmação (CA-12), sem bloquear.
5. Aprovar a escala, só com a equipe mínima atingida (CA-20).
6. Confirmação e pedido de troca pelo próprio membro; decisão da troca pela chefe, que escolhe o substituto.
7. Registrar a presença da equipe depois da sessão e manter o histórico planejado × cumprido (RF-07.6).
8. Derivar o aviso de equipe mínima da sessão da escala aprovada.
9. Publicar o **M-06** no relatório existente (`GET /reports/attendance`), sem supressão.

## Não-objetivos (cortados e por quê)

- **Notificações** (RF-07.5): nova escala, alteração, lembrete e troca dependem de preferências e registro de envio (RF-15, E12, fase 3). Nesta etapa a equipe vê tudo na tela "Minhas escalas". Corte decidido com o produto.
- **Estimativa calculada** pela média das últimas 4 sessões (proposta do ADR-0010): a coordenadora digita a estimativa. Decisão do produto; registrada no ADR novo (decisão 16).
- **Horário por membro** (RF-07.1 cita "horário"): todo membro cobre a sessão inteira. Turnos parciais exigiriam outra regra de equipe mínima por faixa de horário, e nenhum EBI do piloto pediu isso.
- **Presença de quem não estava escalado** no planejado × cumprido: só os membros da escala são marcados. Incluir voluntário de última hora exige outra entidade e muda o M-06.
- **Substituto que aceita antes da chefe aprovar:** a chefe escolhe e o substituto confirma depois, como qualquer membro.
- **Escala em lote / modelo de escala recorrente** ("mesma equipe todo domingo"): cada sessão tem a sua. Uma ação "copiar da sessão anterior" é barata e pode vir depois.
- **Indicadores de equipe além do M-06** (faltas por pessoa, ranking de presença da equipe): não pedidos, e um ranking nominal de voluntárias seria má ideia.
- **Leitura da escala pelos Ministérios Central, Regional e Setorial:** a matriz dá só agregado (A); eles veem o M-06 no relatório.

## Anti-objetivos

- A escala bloquear a operação do dia: sessão sem escala, ou com escala não aprovada, continua podendo ser aberta e receber presenças.
- Escala sem aprovação, ou abaixo da equipe mínima, aparecer como confirmada ou contar no M-06 (CA-20).
- Esconder o conflito porque a outra sessão está fora do território de quem monta a escala.
- Confiar no cliente: a função do membro, o vínculo dele com o EBI e quem aprova são checados no servidor.

## Restrições

- **Fronteira de módulos (ADR-0002):** a escala é de um módulo novo e não lê tabela de `Sessoes`, `Identidade` nem `Organizacao`; consome contratos.
- **OpenAPI antes do código:** cada rota entra no `openapi.yaml`, recebe `no OpenAPI` em `endpoints.md`, e depois o `schema.d.ts` é regenerado.
- **Autorização no servidor:** papel × operação (`AutorizadorOperacao::MATRIZ`) e escopo (`ResolverEscopo::cobre`), como nas etapas anteriores.
- **Auditoria (RN-10, `estados.md`):** toda transição de escala, membro e troca gera evento.
- **Dados reais do código hoje:**
  - não existe `ebis.coordenadora_chefe_id` (a Etapa 2 adiou e nunca criou), nem papel de chefe;
  - `sessao.auxiliares_escalados` é digitado e lido pelo `SessaoController` com a `CalculadoraEquipeMinima`;
  - `ebis.participantes_por_auxiliar` existe (padrão 4), editável pelo `EbiRequest`;
  - os papéis estão espalhados em listas fixas no backend (`AutorizadorOperacao`, `AutoridadeDePapel`, `VisibilidadeIgreja::PAPEIS`, `PapelSeeder`) e no frontend (`ListaNav.tsx`, `Sessoes.tsx`, `Presenca.tsx`, `Vinculos.tsx`, `lib/formatos`).

## Abordagens consideradas

- **A — Módulo novo `Escalas`, com cabeçalho por sessão e linha por membro (escolhida).** `escala` guarda o estado da sessão (prevista/aprovada, estimativa, quem aprovou); `escala_membro` guarda cada pessoa (função, confirmação, presença); `solicitacao_troca_escala` guarda o pedido. Segue o ADR-0002 e a arquitetura de referência ("Agenda e Escalas"). Custa dois contratos novos com `Sessoes` e um com `Identidade`.
- **B — Tudo dentro de `Sessoes`.** Menos contratos (conflito e aviso de equipe leriam direto a tabela), mas o maior módulo do sistema cresce de novo e passa a misturar equipe com presença de crianças.
- **C — Tabela plana do modelo conceitual** (uma linha `escala` por pessoa, com `aprovado_por`). Sem cabeçalho, "aprovada", estimativa e divisor se repetem em cada linha, e voltar a escala para "prevista" precisa atualizar todas as linhas de forma consistente.

**Papel ou campo para a chefe** (alternativa real, decidida pelo produto):

- **Papel novo `coordenadora_chefe` (escolhido).** Entra no vínculo de acesso, no seletor de contexto e na matriz como os demais.
- **Campo `ebis.coordenadora_chefe_id`** (descartado pelo produto). Mexeria menos em listas de papéis, mas o "aprova se chefe" viraria uma exceção fora da matriz.

## Decisões

### 1. Papel `coordenadora_chefe`

- Novo papel no `PapelSeeder` ("Coordenadora chefe").
- **Superconjunto da coordenadora:** todas as operações de `coordenadora` na `MATRIZ`, mais `escala.aprovar` e `troca.decidir`. Ela opera o EBI inteiro nesse contexto, sem trocar de contexto para aprovar.
- **Só escopo `ebi`.** `POST /access-grants` e convites com `coordenadora_chefe` em outro `tipo_escopo` → `422 escopo_invalido_para_papel`. É o único papel com essa trava nesta etapa (a trava geral de papel × escopo continua dívida, ver Riscos da Etapa 7).
- **Uma por EBI, em todos os caminhos que criam vínculo.** Hoje o vínculo nasce em três lugares: `GerenciadorVinculo::conceder` (`POST /access-grants`), `AceitadorConvite::aceitar` (aceite de convite, `:41–49`, que hoje só trava o convite) e `GerenciadorConta` (só cria `responsavel`, fora do caso). A regra vale nos dois primeiros, e a criação do convite também é checada, para falhar cedo.
  - **Recurso comum no banco:** nova coluna `vinculo_acesso.chefe_de_ebi_id` (nullable, **UNIQUE**). Recebe o `escopo_id` quando o vínculo é de `coordenadora_chefe` e está vigente; volta a `null` quando o vínculo é encerrado (`revogar` e o encerramento do "mesmo par" em `conceder`). Um índice único parcial pelo `papel_id` não serve, porque o id do papel é um ULID gerado pelo seeder e muda por ambiente. A coluna funciona igual no SQLite dos testes (`phpunit.xml:29`) e no PostgreSQL, onde `NULL` não colide.
  - **Respostas:**
    - conceder com chefe vigente no EBI → `422 chefe_ja_definida`;
    - criar convite de chefe com chefe vigente → `422 chefe_ja_definida`;
    - aceitar convite de chefe quando outra chefe já está vigente, inclusive por outro convite aceito antes → `422 chefe_ja_definida`. A transação inteira é desfeita: nenhum usuário nem vínculo é criado, e o convite continua pendente, para ser usado se a chefe atual for revogada ou cancelado pelo administrador.
  - A violação do índice, numa corrida, é traduzida para o mesmo `422 chefe_ja_definida`. A checagem prévia dá a mensagem; o índice dá a garantia.
  - Trocar de chefe = revogar a atual e conceder a nova.
- **Autoridade 35** em `AutoridadeDePapel` (acima de `coordenadora` 30, abaixo de `adm_igreja` 40): concedida por `adm_igreja` e acima; a coordenadora não nomeia a chefe.
- **Onde a coordenadora já é reconhecida, a chefe também é:** `VisibilidadeIgreja::PAPEIS` (números reais no relatório e na lista), MFA (já exige para todo papel não-responsável), menus e permissões de tela no frontend. Cada lista fixa citada nas Restrições ganha `coordenadora_chefe`.

### 2. Quem aprova

- A escala e as trocas são aprovadas pela **coordenadora chefe vigente do EBI**.
- **Sem chefe vigente no EBI**, o `adm_igreja` da igreja do EBI aprova no lugar (decisão do produto, desvia do ADR-0010 e vai para o ADR novo).
- **Troca pedida pela própria chefe:** decidida pelo `adm_igreja` (ela não decide a própria troca).
- **Escala em que a chefe está escalada:** a própria chefe aprova. *Leitura adotada, sujeita a ratificação:*
  - O ADR-0010 dá à chefe a aprovação **da escala do EBI**, sem exceção. Escalar a chefe é o caso comum: ela é uma das coordenadoras do EBI.
  - A segregação que o código já pratica é "quem abriu o pedido não o decide" (`GerenciadorVinculoResponsavel.php:79–80`). Aqui ela se aplica à **troca** pedida pela chefe, que vai ao `adm_igreja` (item acima).
  - Aprovar a escala não é um pedido dela, e a regra que ela aprova (equipe mínima) é checada pelo servidor.
- O servidor decide quem pode aprovar a cada pedido (não grava "aprovador designado"): se existir chefe vigente, só ela (exceto a troca dela); se não, `adm_igreja`. `super_admin` não aprova escala (não está na matriz como G de escala).

### 3. Dados (migrations novas, módulo `App\Domain\Escalas`)

```
escala                    id, sessao_id (UNIQUE), ebi_id, igreja_id,
                          estimativa_participantes (smallint),
                          participantes_por_auxiliar (smallint, cópia do EBI),
                          status (prevista | aprovada),
                          aprovado_por (nullable), aprovado_em (nullable),
                          criado_por, timestamps

escala_membro             id, escala_id FK, usuario_id, funcao (coordenadora | auxiliar),
                          status (escalado | confirmado | troca_solicitada | substituido),
                          confirmado_em (nullable),
                          presente (bool, nullable), presenca_registrada_por/em (nullable),
                          substitui_membro_id (nullable FK escala_membro), timestamps
                          UNIQUE parcial (escala_id, usuario_id) WHERE status <> 'substituido'

solicitacao_troca_escala  id, escala_membro_id FK, solicitante_id, motivo (nullable),
                          status (pendente | aprovada | recusada | cancelada),
                          substituto_membro_id (nullable FK escala_membro),
                          decidido_por (nullable), decidido_em (nullable), motivo_decisao (nullable),
                          timestamps
                          UNIQUE parcial (escala_membro_id) WHERE status = 'pendente'
```

- `ebi_id` e `igreja_id` são copiados da sessão (via contrato) para filtrar sem ler `sessao`.
- **`participantes_por_auxiliar` é copiado do EBI a cada gravação da escala** e fica congelado enquanto a escala está `aprovada`. Isso **muda a semântica** do `metricas-e-indicadores.md:12`, que pede "o valor do parâmetro vigente na data da sessão" (*leitura adotada, sujeita a ratificação*):
  - **Por quê:** o sistema não tem histórico do parâmetro. `ebis.participantes_por_auxiliar` é um campo sobrescrito por `GerenciadorOrganizacao::atualizarEbi`, sem vigência. "Vigente na data da sessão" não é calculável para sessões passadas sem criar um histórico novo no módulo `Organizacao`.
  - O valor que a chefe usou para aprovar é o que define se a equipe era suficiente; recalcular depois tornaria "não coberta" uma escala aprovada corretamente.
  - **Consequência aceita:** se o divisor muda entre a aprovação e a sessão, a escala aprovada segue com o divisor antigo até ser alterada. Qualquer alteração (decisão 6) volta a escala para prevista e copia o divisor atual. A tela mostra o divisor usado.
  - A linha 12 de `metricas-e-indicadores.md` é reescrita para "parâmetro registrado na escala no momento da aprovação" (decisão 16).
  - Alternativa descartada: um histórico de vigência do parâmetro no EBI. É uma tabela nova em `Organizacao` para um valor que no piloto é 4 em todos os EBIs (`OrganizacaoSeeder.php:65`).
- Não há estado `cumprida` / `ausencia` no membro: o resultado fica em `presente` (null = não registrado). O estado "cancelada" da escala é derivado da sessão cancelada, não gravado.
- `escala.status` é do conjunto; o diagrama de `estados.md` é reescrito em dois (escala e membro), ver decisão 16.

### 4. Quem pode ser escalado

- Usuário com vínculo **vigente** de `coordenadora`, `coordenadora_chefe` ou `auxiliar` cujo escopo é o EBI ou a igreja do EBI.
- Função `coordenadora` exige papel `coordenadora` ou `coordenadora_chefe`. Função `auxiliar` aceita qualquer dos três (*leitura adotada, sujeita a ratificação*: o RF-07.1 escala "coordenadoras e auxiliares" sem prender a função ao papel, e uma coordenadora pode servir como auxiliar).
- Novo contrato em `Identidade`: `ConsultarEquipeEbi::equipe(ebiId, igrejaId)` → `[{usuario_id, nome, papeis}]`. Exposto em `GET /ebis/{id}/team` para o seletor da tela.
- **Vínculo revogado depois de escalado** (RF-05.4: "usuário desligado não registra sessões nem consulta dados após a revogação"):
  - O membro continua na escala. A resposta o marca `sem_vinculo`, que é calculado e não fica gravado.
  - **Aprovar** com membro sem vínculo → `422 membro_sem_vinculo`. Ele não confirma, não pede troca e não vê a escala.
  - **Não conta como cobertura nas sessões depois da revogação.** O membro só conta para a equipe mínima (decisão 5), para o aviso durante a sessão (decisão 10) e para o M-06 (decisão 11) se tinha vínculo de equipe com o EBI **vigente na data local da sessão**: `inicio ≤ data` e (`fim` nulo ou `fim > data`).
    - `vinculo_acesso` já guarda `inicio`/`fim` como datas e a revogação grava `fim = hoje` (`GerenciadorVinculo.php:98`). A regra sai do histórico existente, sem campo novo.
    - Assim, uma sessão passada em que a pessoa estava vigente continua coberta, e o histórico não é reescrito. Uma sessão futura deixa de contar a pessoa no dia da revogação.
  - A escala aprovada **não** volta a prevista sozinha: `Identidade` não chama `Escalas`. Ela passa a aparecer como "abaixo do mínimo" na tela de Escalas, e a coordenadora resolve alterando a escala (que volta a prevista) ou a chefe resolve por troca.
  - O contrato `ConsultarEquipeEbi` ganha `vigencias(usuarioIds, ebiId, igrejaId)` → períodos `[inicio, fim)` dos vínculos de equipe, para essa checagem em lote.

### 5. Equipe mínima

- `auxiliares_necessarios = ⌈estimativa ÷ participantes_por_auxiliar⌉`; `coordenadoras_necessarias = 1`.
- **Estimativa 0 → 0 auxiliares; o mínimo é só a coordenadora.** *Leitura adotada, sujeita a ratificação.*
  - É a fórmula literal do `ADR-0010:10` (auxiliares = ⌈participantes esperados ÷ 4⌉, com exemplos a partir de 1 criança). É também o que a `CalculadoraEquipeMinima.php:10` já faz, e a decisão abaixo exige uma fórmula só.
  - A frase "1 auxiliar para cada 4 participantes" (`ADR-0010:9`) não pede auxiliar quando não se espera criança.
  - Descartado: `max(1, …)`. Criaria uma segunda regra, divergente do aviso durante a sessão.
- `estimativa_participantes` é obrigatória ao criar a escala (inteiro ≥ 0), digitada pela coordenadora.
- Conta para o mínimo: membros que não estão `substituido` **e** que têm vínculo vigente na data da sessão (decisão 4). Uma pessoa conta numa função só.
- A resposta da escala traz `equipe_minima: { estimativa, participantes_por_auxiliar, coordenadoras_necessarias, auxiliares_necessarios, coordenadoras_escaladas, auxiliares_escalados, atingida }`.
- A `CalculadoraEquipeMinima` existente é reaproveitada (ou movida para um lugar comum) para não haver duas fórmulas.

### 6. Ciclo da escala

```
(sem escala) --criar--> prevista --aprovar [mínimo atingido, todos com vínculo]--> aprovada
aprovada --qualquer alteração da coordenadora--> prevista   (confirmações mantidas)
aprovada --troca aprovada--> aprovada                        (a troca já é aprovação da chefe)
```

- **Criar:** `POST /sessions/{id}/schedules` com estimativa e membros iniciais (opcionais). Uma escala por sessão (`409 escala_existente`).
- **Alterar** (incluir/retirar membro, mudar função ou estimativa): só com a sessão `prevista` ou `aberta`. Se a escala estava `aprovada`, volta para `prevista`, limpa `aprovado_por/em` e mantém `confirmado_em` de quem continua (decisão do produto).
  - **Trocas pendentes são canceladas por qualquer alteração**, não só pela retirada do membro: `status = cancelada`, `motivo_decisao = "escala alterada"`, e o membro volta ao status anterior. A troca foi pedida sobre uma escala aprovada que deixou de existir; quem montar a nova versão resolve a ausência ao montar. Cada troca cancelada gera `escala.troca_cancelada`.
- **Aprovar:** `POST /schedules/{id}/approve`. Exige escala `prevista` (`409 escala_em_estado_invalido` se já aprovada), sessão `prevista` ou `aberta`, mínimo atingido (`422 equipe_minima_nao_atingida`) e todos os membros com vínculo. Conflitos **não** impedem; a resposta e a tela mostram os conflitos ainda abertos.
- **Sessão cancelada:** a escala fica só leitura, aparece como "cancelada" e sai do M-06. **Sessão encerrada:** só a presença da equipe pode mudar.
  - Quando a sessão é cancelada ou encerrada, trocas pendentes ficam sem efeito. Como `Sessoes` não chama `Escalas`, isso não é gravado no cancelamento ou no encerramento. Qualquer tentativa de decidir a troca é recusada (abaixo), e as listas mostram a troca como "expirada" (derivado do status da sessão).
- **Atomicidade.** Toda escrita na escala (alterar, aprovar, confirmar, pedir, cancelar ou decidir troca, presença da equipe) roda numa transação que começa com `lockForUpdate` na linha de `escala`, seguindo o padrão de `GerenciadorSessao.php:116` e `AceitadorConvite.php:23`. Todas as guardas são reavaliadas **depois** do lock. Assim:
  - aprovar e alterar ao mesmo tempo → uma vence; a outra vê o estado novo e responde pela guarda (409 ou volta a prevista);
  - decidir a mesma troca duas vezes → a segunda vê `status ≠ pendente` → `409 troca_ja_decidida`;
  - alterar enquanto a chefe aprova a troca → ou a troca é aplicada e a alteração volta a escala a prevista, ou a alteração cancela a troca e a decisão recebe 409. Nunca as duas.
- O status da sessão vem do contrato de `Sessoes`, lido dentro da transação. Não há lock na `sessao`: `Escalas` não trava tabela de outro módulo. *Corrida residual aceita:* a sessão pode ser cancelada no instante seguinte à leitura. O efeito é uma troca aprovada numa sessão já cancelada, que não conta no M-06 nem aparece como ativa.

### 7. Confirmação e troca (pelo membro)

- **Confirmar:** `POST /schedule-members/{id}/confirm`. Só o próprio membro (propriedade: `usuario_id` = ator), só com escala `aprovada` e sessão `prevista`. `escalado → confirmado`. Idempotente. A resposta traz os conflitos dele, e a tela os mostra antes do botão (CA-12).
- Com escala `prevista`, a tela mostra "aguardando aprovação" e não oferece confirmar (CA-20).
- **Pedir troca:** `POST /schedule-swaps` `{ escala_membro_id, motivo? }`. Próprio membro, escala `aprovada`, sessão `prevista`, membro `escalado` ou `confirmado`. Membro passa a `troca_solicitada` e **deixa de contar como confirmado** no M-06 enquanto a troca está pendente (*leitura adotada, sujeita a ratificação*: quem pediu para sair não é cobertura confirmada).
- **Cancelar o pedido:** `POST /schedule-swaps/{id}/cancel`, sem corpo. Só o **solicitante** (`solicitante_id` = ator), só com o pedido `pendente` (senão `409 troca_ja_decidida`). O membro volta ao status anterior: `confirmado` se tinha `confirmado_em`, senão `escalado`.
  - É uma rota separada da decisão de propósito: o solicitante nunca passa pelo `PATCH` e não tem como mandar `decisao` nem `substituto_usuario_id`.
  - Outro usuário cancelando → 404 (não revela o pedido alheio).
- **Decidir:** `PATCH /schedule-swaps/{id}` `{ decisao: aprovar | recusar, substituto_usuario_id?, motivo? }`, só por quem aprova (decisão 2). O solicitante, mesmo sendo chefe, não decide o próprio pedido (403). Guardas, avaliadas depois do lock da escala (decisão 6):
  - troca `pendente` → senão `409 troca_ja_decidida`;
  - escala `aprovada` → senão `409 escala_em_estado_invalido`. Na prática não acontece, porque alterar a escala cancela as trocas, mas a guarda fica explícita;
  - sessão `prevista` → senão `409 sessao_em_estado_invalido`. Com sessão aberta, encerrada ou cancelada a troca não é mais aplicável; com sessão aberta, ajustar a equipe é alterar a escala.
  - **Aprovar** exige `substituto_usuario_id` elegível (decisão 4), com vínculo vigente e que não esteja na escala. O membro original vira `substituido`; nasce um membro novo, mesma função, `escalado`, com `substitui_membro_id`. A escala continua `aprovada`; o substituto confirma depois.
    - **A troca é de pessoa por pessoa na mesma função, então a equipe mínima não muda.** O serviço recalcula mesmo assim; se, por vínculo revogado de outro membro, a escala já estava abaixo do mínimo, a troca é aplicada (melhora ou mantém a situação) e a resposta traz `equipe_minima.atingida = false`.
  - **Recusar** devolve o membro ao status anterior.
- Lista para quem decide: `GET /schedule-swaps?ebi_id=&status=pendente`.

### 8. Conflito de horário (CA-12)

- **Conflito** = o mesmo usuário é membro não `substituido` de outra escala cuja sessão não está cancelada e cujo intervalo `[inicio_previsto, fim_previsto)` se sobrepõe ao desta, em **qualquer** EBI ou igreja.
- Calculado no servidor e devolvido em cada membro: `conflitos: [{ sessao_id?, inicio, fim, igreja_nome? }]`.
  - Se a outra sessão está no território de quem consulta: mostra igreja, EBI e horário.
  - Fora do território: mostra só o horário e "outra igreja" (*leitura adotada, sujeita a ratificação*: avisar sem expor a agenda de outra igreja). O próprio membro, em "Minhas escalas", vê tudo, porque são as escalas dele.
- Aparece ao montar, ao aprovar e ao confirmar. **Não bloqueia** nada (decisão do produto).
- Precisa do intervalo das sessões: contrato novo em `Sessoes`, `ConsultarSessao::intervalos(sessaoIds)` (ou equivalente) → `{id, ebi_id, igreja_id, inicio_previsto, fim_previsto, status}`.

### 9. Presença da equipe (planejado × cumprido)

- `PUT /schedules/{id}/team-attendance` `{ membros: [{ id, presente }] }`. Só com a sessão `encerrada`. Quem gerencia a escala (`escala.gerenciar`).
  - **Idempotente:** grava o estado enviado. Reenviar o mesmo corpo não muda nada e não gera evento novo; o evento só sai quando algum valor muda.
  - **Parcial:** só os membros listados mudam. Os demais mantêm o valor que já tinham, inclusive `null`.
  - Ids que não são membros não substituídos desta escala → `422`, e nada é gravado.
- **O servidor nunca infere presença.** `presente` fica `null` ("não registrado") até a coordenadora enviar um valor explícito. Confirmar a escala não é comparecer.
  - Na tela, a caixa pode vir marcada para quem confirmou, para poupar toques (decisão do produto na conversa). Essa marcação é só sugestão do formulário e não grava nada.
- **Duas chamadas, sem atomicidade com o encerramento.** A tela encerra a sessão (rota da Etapa 6, sem mudança) e depois envia a presença da equipe.
  - Se a segunda chamada falhar, a sessão fica encerrada com os membros em `null`. A tela mostra o erro e mantém o formulário para reenviar.
  - O detalhe da sessão encerrada e a tela de Escalas mostram "Presença da equipe não registrada" enquanto houver membro com `null`. A coordenadora retoma a qualquer momento pela mesma rota.
  - Não há prazo nem bloqueio. *Por quê:* juntar as duas coisas numa chamada só faria `Sessoes` escrever em `Escalas` (ADR-0002), ou criaria uma rota de orquestração para um ganho pequeno.
- No histórico, `null` aparece como "não registrado", nunca como ausente.
- Corrigir depois é permitido, sempre auditado (`escala.presenca_equipe`, antes/depois).
- **Histórico planejado × cumprido** (RF-07.6) = a própria escala da sessão encerrada: quem foi escalado, quem substituiu quem, quem confirmou, quem esteve. Visível no detalhe da sessão e em "Minhas escalas" (passadas).

### 10. Aviso de equipe mínima durante a sessão (substitui o campo digitado)

- Com escala **aprovada**, o detalhe da sessão (`GET /sessions/{id}`) passa `auxiliares_informados` = membros com função `auxiliar` não substituídos e com vínculo vigente na data da sessão (decisão 4). Sem escala aprovada, continua usando `sessao.auxiliares_escalados` (compatibilidade com sessões antigas).
- `PATCH /sessions/{id}` com `auxiliares_escalados` quando há escala aprovada → `422 escala_aprovada_define_equipe`. A tela esconde o campo nesse caso.
- A composição fica no `SessaoController` (como já é feito com `ConsultarEbi` e a calculadora), chamando um contrato de `Escalas` — assim `Sessoes` não depende de `Escalas` e `Escalas` depende de `Sessoes` só por contrato, sem ciclo.
- O aviso continua **não bloqueante** (ADR-0010).

### 11. M-06 no relatório

- **Denominador:** sessões **não canceladas** (`prevista`, `aberta`, `encerrada`, realizada ou não), com data local de `inicio_previsto` (fuso da igreja) **dentro do período e até a data de geração do relatório, inclusive**. *Leitura adotada, sujeita a ratificação:*
  - "Sessões previstas" = as que estavam planejadas, aconteçam ou não.
  - **Sessões futuras não entram.** O `RelatorioPresencaRequest` aceita `fim` no futuro, e o gerador diário cria sessões 28 dias à frente (Etapa 6, §6.3). Sem esse corte, um período que inclui as próximas semanas mostraria uma cobertura baixa por escalas que ainda nem precisavam existir.
  - O corte segue o precedente de `metricas-e-indicadores.md:22`: as janelas de ativo e M-09 também têm limite superior na data de geração.
  - A cobertura das próximas semanas aparece na tela de Escalas, que é onde se age sobre ela.
  - O `criterios` do relatório e do CSV declara o corte.
- **Numerador:** dessas, as que têm escala `aprovada` e, entre os membros que contam, ao menos 1 na função `coordenadora` e pelo menos `auxiliares_necessarios` na função `auxiliar`, com o `participantes_por_auxiliar` registrado na escala (decisão 3). Um membro conta quando:
  - tem `confirmado_em` não nulo e status `confirmado` (`troca_solicitada` e `substituido` não contam);
  - **e** tem vínculo vigente na data da sessão (decisão 4).
- A presença da equipe **não** entra: o M-06 mede o planejamento confirmado (decisão do produto).
- **Contrato:** `Escalas` expõe `ConsultarCoberturaEscala::cobertura(igrejaIds, ebiIds, inicio, fim)` → por nó `{ sessoes_previstas, sessoes_cobertas }`. O denominador vem de `Sessoes` por contrato (novo método em `ConsultarIndicadoresPresenca` ou similar); o construtor escolhe se `Escalas` pede as sessões a `Sessoes` ou se `Relatorios` junta os dois.
- **Resposta do relatório:** três chaves novas em `totais`, no mesmo formato `{ valor, suprimido, motivo }`:
  - `sessoes_previstas`;
  - `sessoes_com_equipe_minima`;
  - `cobertura_escala` (percentual com uma casa, `null` quando o denominador é 0).
- **Nunca suprimido**, em nenhum nível, e fora da cascata (decisão do produto): conta sessões, não pessoas, como M-05 e M-09. Vale também no nó EBI.
- **CSV:** três colunas novas; `criterios` passa a declarar a regra do M-06.
- **Tela:** linha "Cobertura da escala: X de Y sessões (Z%)" no bloco do período.

### 12. Autorização

Novas operações em `Operacoes`, linhas na `MATRIZ` e gates:

| Operação | Papéis | Escopo |
|---|---|---|
| `escala.ler` | super_admin, adm_regional, adm_setor, adm_igreja, ministerio_local, coordenadora, coordenadora_chefe | subárvore (escala do EBI) |
| `escala.gerenciar` | adm_igreja, coordenadora, coordenadora_chefe | subárvore |
| `escala.aprovar` | coordenadora_chefe; adm_igreja (só se o EBI não tem chefe vigente) | EBI; a regra "sem chefe" é checada no serviço |
| `troca.decidir` | coordenadora_chefe; adm_igreja (sem chefe, ou troca da própria chefe) | EBI; idem |

- Confirmar, pedir e cancelar troca e `GET /me/schedules` não são operações de papel: são do **próprio membro** (propriedade), com vínculo vigente. O auxiliar lê só as próprias escalas (matriz: "L (própria)").
- Leitura nominal por super_admin, adm_regional e adm_setor segue a matriz (decisão do produto): a escala tem nome de adultos da equipe, que esses perfis já leem em `/users`.
- Ministérios Central, Regional e Setorial: sem `escala.ler`; veem o M-06 por `relatorio.ler`.
- Fora do território: 404, como no relatório. Papel sem operação: 403.

### 13. Rotas

| Método | Rota | Operação |
|---|---|---|
| GET | `/ebis/{id}/schedules?inicio=&fim=` | `escala.ler` — sessões do período com a situação da escala (inclui "sem escala") |
| GET | `/ebis/{id}/team` | `escala.gerenciar` — quem pode ser escalado |
| POST | `/sessions/{id}/schedules` | `escala.gerenciar` |
| GET | `/schedules/{id}` | `escala.ler` (ou membro da escala) — membros, equipe mínima, conflitos |
| PATCH | `/schedules/{id}` | `escala.gerenciar` — estimativa |
| POST | `/schedules/{id}/members` · DELETE `/schedule-members/{id}` · PATCH `/schedule-members/{id}` (função) | `escala.gerenciar` |
| POST | `/schedules/{id}/approve` | `escala.aprovar` |
| POST | `/schedule-members/{id}/confirm` | próprio membro |
| GET | `/schedule-swaps` | `troca.decidir` |
| POST | `/schedule-swaps` | próprio membro — pedir |
| POST | `/schedule-swaps/{id}/cancel` | próprio solicitante — cancelar |
| PATCH | `/schedule-swaps/{id}` | `troca.decidir` — aprovar ou recusar |
| PUT | `/schedules/{id}/team-attendance` | `escala.gerenciar` |
| GET | `/me/schedules?inicio=&fim=` | próprio usuário |

`endpoints.md` já cataloga `GET /ebis/{id}/schedules`, `POST /sessions/{id}/schedules`, `POST /schedules/{id}/approve` e `/schedule-swaps`; as demais entram como *novo*.

### 14. Telas

- **"Escalas"** (menu para `escala.ler`/`escala.gerenciar`): lista de sessões das próximas semanas do EBI do contexto, cada uma com a situação — "sem escala", "abaixo do mínimo", "aguardando aprovação", "aprovada · X de Y confirmados", "cancelada". Ao abrir: estimativa, membros com função e status, conflitos (ícone + texto), indicador de equipe mínima, botão "Aprovar" para quem pode. Painel "Trocas pendentes" com escolha do substituto.
- **"Minhas escalas"** (todo usuário com vínculo de equipe): próximas escalas com "Confirmar" e "Pedir troca", conflitos visíveis antes do botão; escalas passadas com "esteve / não esteve".
- **Presença:** ao encerrar a sessão, lista da equipe escalada para marcar quem esteve. O campo "auxiliares escalados" some quando há escala aprovada.
- **Equipe / Vínculos:** `coordenadora_chefe` aparece como papel concedível, só com escopo EBI.
- **Relatórios:** linha do M-06.

### 15. Auditoria (RN-10)

Eventos: `escala.criada`, `escala.alterada` (inclui volta a prevista), `escala.aprovada`, `escala.membro_confirmado`, `escala.troca_solicitada`, `escala.troca_cancelada`, `escala.troca_decidida`, `escala.presenca_equipe`. Metadados minimizados: ids de escala, sessão, membro e usuário; sem nomes. Concessão de chefe já é auditada como `acesso.concedido`.

### 16. Documentação na mesma entrega

- **ADR-0033 (novo) — Coordenadora chefe e regras da escala:**
  - papel `coordenadora_chefe`: superconjunto da coordenadora, só escopo EBI, uma por EBI garantida também no aceite de convite;
  - `adm_igreja` aprova quando não há chefe e decide a troca pedida pela chefe; a chefe aprova a escala em que está;
  - estimativa digitada, que substitui a média de 4 sessões proposta no ADR-0010; estimativa 0 → 0 auxiliares;
  - divisor registrado na escala (substitui "vigente na data da sessão");
  - escala alterada volta a prevista e cancela trocas pendentes;
  - conflito avisa sem bloquear;
  - M-06 até a data de geração, só com membros de vínculo vigente, e nunca suprimido.
- **ADR-0010:** apontar para o ADR-0033 nas consequências (estimativa e quem aprova na falta de chefe).
- **`estados.md`:** diagrama da escala (prevista ⇄ aprovada) e do membro (escalado → confirmado → troca_solicitada → substituido; presença à parte).
- **`modelo-conceitual.md`:** tabelas `escala`, `escala_membro`, `solicitacao_troca_escala`; retirar `coordenadora_chefe_id` de `ebi`.
- **`matriz-de-autorizacao.md`:** coluna/nota da coordenadora chefe e as quatro operações em "Operações implementadas".
- **`metricas-e-indicadores.md`:**
  - linha 12 reescrita: denominador até a data de geração, numerador com membros confirmados e de vínculo vigente, "parâmetro registrado na escala na aprovação" no lugar de "vigente na data da sessão";
  - M-06 incluído entre os indicadores nunca suprimidos.
- **`modelo-conceitual.md` / dicionário:** coluna `vinculo_acesso.chefe_de_ebi_id`.
- **`endpoints.md`, `openapi.yaml`, `perfis-e-atores.md`, `RF-07` (status), `rastreabilidade.md` (CA-12, CA-20), `CHANGELOG.md`.**

## Riscos

- **Papel novo espalhado em listas fixas.** O superconjunto exige incluir `coordenadora_chefe` em cada lista de papéis do backend e do frontend. Esquecer uma deixa a chefe sem acesso a algo que a coordenadora tem. Mitigação: teste que percorre a `MATRIZ` e exige que `coordenadora_chefe ⊇ coordenadora`, e teste de tela por papel.
- **Unicidade da chefe sob concorrência:** duas concessões, dois aceites de convite, ou uma concessão e um aceite simultâneos podem criar duas chefes se só houver checagem no serviço. Por isso a coluna única `chefe_de_ebi_id` da decisão 1, que vale para todos os caminhos.
  - Risco residual: um caminho novo que crie vínculo de chefe sem preencher a coluna. Mitigação: a coluna é preenchida num único ponto de `Identidade` que os dois caminhos chamam, e um teste confere que não existe vínculo de chefe vigente com a coluna nula.
- **Convite de chefe que perde a vaga** fica pendente e falha no aceite com 422. Quem foi convidada não consegue entrar até o administrador revogar a chefe atual ou cancelar o convite. A tela de aceite mostra a mensagem "este EBI já tem coordenadora chefe; fale com o administrador".
- **Chefe aprovando escala em que está:** se o produto quiser segregação de função, a regra muda para "adm_igreja aprova escala com a chefe" (decisão 2).
- **Revogação não volta a escala a prevista sozinha** (decisão 4). Uma escala aprovada pode ficar abaixo do mínimo sem mudar de estado. Ela não conta no M-06 e aparece como "abaixo do mínimo", mas depende de alguém agir.
- **Divisor congelado** (decisão 3) pode divergir do divisor atual do EBI até a escala ser alterada. A tela mostra o divisor usado.
- **Presença da equipe em duas chamadas** (decisão 9): uma falha deixa a sessão encerrada sem registro da equipe. A situação fica visível ("não registrada") e é retomável; o histórico nunca a mostra como ausência.
- **Corrida residual com o status da sessão** (decisão 6): uma troca pode ser aprovada no instante em que a sessão é cancelada. Não afeta o M-06.
- **Fallback do adm_igreja:** um EBI sem chefe nomeada fica dependente do adm_igreja; se a igreja não tiver adm_igreja vigente, ninguém aprova e o M-06 fica em zero. Visível na tela ("defina a coordenadora chefe").
- **Estimativa digitada** pode ser chutada para baixo para atingir o mínimo. Custo aceito pelo produto; o aviso durante a sessão continua comparando com os presentes reais.
- **Conflito por horário previsto:** sessões cujo horário real difere do previsto podem gerar falso conflito ou deixar de mostrar um real. Por isso o aviso não bloqueia.
- **Desempenho do conflito:** checar sobreposição em todo o país por membro. No piloto é pouco; índice em `escala_membro (usuario_id)` e consulta por intervalo resolvem.
- **M-06 e sessões futuras:** o corte na data de geração (decisão 11) faz o M-06 e o M-05 terem denominadores com recortes diferentes num mesmo período que inclui o futuro. O `criterios` declara isso. Se o produto preferir contar as futuras, basta tirar o corte; o resto da regra não muda.

## Testes que provam o pronto

- **CA-20:** escala `prevista`, ou abaixo do mínimo, não deixa confirmar, não aparece como confirmada e não conta no M-06; aprovar abaixo do mínimo → 422.
- **CA-12:** membro escalado em sessões sobrepostas de duas igrejas vê o conflito ao ser escalado, na aprovação e antes de confirmar; outra sessão fora do território aparece sem nome da igreja para a coordenadora; aprovação e confirmação seguem permitidas.
- **Equipe mínima:** estimativa 0 → 0 auxiliares (só a coordenadora; aprova com 1 coordenadora e nenhum auxiliar); 1 → 1; 4 → 1; 5 → 2; divisor 3 no EBI → 5 pede 2. A escala e o aviso durante a sessão dão o mesmo resultado para as mesmas entradas, porque a fórmula é uma só.
- **Divisor registrado:** mudar o divisor do EBI depois da aprovação não muda a escala aprovada nem o M-06 dela. Alterar a escala depois disso volta a prevista e copia o divisor novo.
- **Chefe — unicidade em todos os caminhos:**
  - conceder a segunda chefe no mesmo EBI → 422 `chefe_ja_definida`; criar convite de chefe com chefe vigente → 422; chefe em escopo igreja → 422 `escopo_invalido_para_papel`; coordenadora não concede chefe;
  - dois convites de chefe para o mesmo EBI: o primeiro aceite cria o vínculo; o segundo → 422, sem usuário nem vínculo criados, e o convite continua pendente;
  - convite criado antes da nomeação de outra chefe e aceito depois → 422;
  - revogar a chefe libera a vaga: o convite pendente passa a ser aceito;
  - **concorrência:** duas concessões ou dois aceites disputando o mesmo EBI terminam com exatamente um vínculo de chefe vigente, e o perdedor recebe 422 `chefe_ja_definida` (a violação do índice é traduzida). No SQLite dos testes, provado pela violação da coluna única com a checagem prévia contornada;
  - chefe faz tudo o que a coordenadora faz (teste de superconjunto da matriz).
- **Aprovação:** só a chefe vigente aprova; com chefe vigente, adm_igreja → 403; sem chefe, adm_igreja aprova; coordenadora → 403; a chefe aprova escala em que está escalada; troca da própria chefe é decidida pelo adm_igreja e a chefe → 403 no próprio pedido; aprovar escala já aprovada → 409.
- **Ciclo:** alterar escala aprovada volta a prevista mantendo confirmações; troca aprovada mantém aprovada, marca original `substituido`, cria membro novo `escalado`; recusar e cancelar devolvem o status anterior; troca pendente não conta como confirmada.
- **Sequências da troca:**
  - alterar a escala (qualquer alteração, não só retirar o membro) cancela as trocas pendentes, gera `escala.troca_cancelada` e devolve o membro ao status anterior; decidir essa troca depois → 409;
  - decidir com a sessão aberta, encerrada ou cancelada → 409; a lista mostra a troca como "expirada";
  - decidir duas vezes a mesma troca → a segunda 409;
  - aprovar troca com substituto já na escala, sem vínculo vigente ou de papel inelegível → 422;
  - **concorrência:** alteração e decisão de troca simultâneas terminam num dos dois resultados válidos (troca aplicada e escala prevista, ou troca cancelada e decisão 409), nunca em troca aplicada sobre escala que a cancelou; aprovação e alteração simultâneas idem.
- **Cancelar pedido de troca:** `POST /schedule-swaps/{id}/cancel` pelo solicitante → membro volta ao status anterior; outro usuário → 404; cancelar pedido já decidido → 409; o solicitante no `PATCH` → 403, inclusive tentando mandar `substituto_usuario_id`.
- **Membro:** sem vínculo vigente bloqueia a aprovação e não confirma; outro usuário confirmando por mim → 403/404.
- **Revogação depois da confirmação:**
  - membro confirmado e revogado hoje deixa de contar na equipe mínima, no aviso durante a sessão e no M-06 das sessões **a partir de hoje**; a escala aparece "abaixo do mínimo" sem mudar de estado;
  - numa sessão **passada** em que o vínculo estava vigente, ele continua contando: o M-06 daquele período não muda depois da revogação.
- **Sessão:** cancelar sessão tira a escala do M-06; presença da equipe só com sessão encerrada; escala ausente não impede abrir sessão nem registrar presença.
- **Presença da equipe:**
  - sem envio, todos ficam `null`, inclusive quem confirmou, e o histórico mostra "não registrado", nunca "ausente";
  - encerrar a sessão com sucesso e falhar a chamada da presença (simulada) deixa a sessão encerrada e os membros `null`; o detalhe mostra "Presença da equipe não registrada"; o reenvio depois grava normalmente;
  - reenviar o mesmo corpo é idempotente: mesmo estado, nenhum evento novo;
  - envio parcial muda só os listados; id de outra escala ou de membro substituído → 422 sem gravar nada.
- **Aviso de equipe mínima:** com escala aprovada usa os auxiliares da escala com vínculo vigente; `PATCH auxiliares_escalados` → 422; sem escala aprovada, usa o campo antigo.
- **M-06:**
  - o denominador exclui canceladas e sessões depois da data de geração (período com `fim` no futuro não conta as sessões futuras);
  - o numerador conta só escala aprovada com 1 coordenadora + auxiliares necessários confirmados e com vínculo vigente na data da sessão;
  - nunca suprimido para quem atua acima da igreja (valor 1 a 4 aparece) e não dispara cascata;
  - CSV com as colunas novas, e o `criterios` declara o corte na data de geração e o divisor registrado.
- **Autorização:** horizontal e vertical → 404; ministérios acima da igreja sem `escala.ler` → 403; auxiliar lê só as próprias escalas.
- **Auditoria:** cada transição gera o evento esperado, sem nomes nos metadados.

## Deixado ao construtor

- Forma exata dos contratos entre `Escalas`, `Sessoes`, `Identidade` e `Relatorios`, desde que nenhum módulo leia tabela de outro e não haja dependência circular.
- Como preencher e limpar `chefe_de_ebi_id` num único ponto de `Identidade` (a coluna única em si não é opcional: decisão 1).
- Nomes de classes auxiliares, textos de tela e agrupamento visual das telas.
- Se a lista de equipe na tela de presença é enviada logo após o encerramento ou em botão próprio. Em qualquer caso, são duas chamadas, e a falha da segunda segue a decisão 9.
- Ordem de entrega (sugestão: chefe → escala e equipe mínima → aprovação e conflito → confirmação e troca → presença da equipe e aviso → M-06).

## Decisões do produto nesta conversa (alternativas descartadas)

- **Ciclo completo sem notificação.** Descartados: incluir e-mail transacional de escala; entregar só o núcleo (montar e aprovar) e deixar confirmação/troca/M-06 para depois.
- **Papel `coordenadora_chefe`, superconjunto.** Descartados: campo no EBI; papel só de aprovação, que obrigaria trocar de contexto.
- **adm_igreja aprova sem chefe.** Descartado: bloquear até a chefe ser definida.
- **A chefe escolhe o substituto.** Descartados: quem pede indica; substituto aceita antes da chefe.
- **Conflito avisa, não bloqueia.** Descartado: 422 com conflito.
- **Alteração volta a prevista.** Descartado: escala aprovada só muda por troca.
- **Não aprova abaixo do mínimo.** Descartado: aprovar com aviso e não contar no M-06.
- **M-06 = aprovada e confirmada.** Descartado: contar pela equipe que compareceu.
- **Estimativa digitada.** Descartado: média das últimas 4 sessões, ajustável.
- **Presença da equipe pela coordenadora no encerramento.** Descartado: cada membro marca a si.
- **Escala aprovada define os auxiliares do aviso.** Descartado: remover o campo antigo.
- **M-06 nunca suprimido.** Descartado: tratá-lo como indicador de pessoas.
- **Módulo novo `Escalas`.** Descartados: dentro de `Sessoes`; tabela plana.
- **Leitura nominal segue a matriz.** Descartado: escala nominal só até a igreja.
