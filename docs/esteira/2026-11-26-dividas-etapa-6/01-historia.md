# Dívidas da Etapa 6: escopo de mesclagem e correção (CA-27) e finalidade nas leituras de presença

**Como** coordenadora (e coordenadora chefe) **quero** mesclar e corrigir os cadastros ligados ao meu EBI, inclusive os que não têm igreja de referência mas frequentam o meu EBI, **para** resolver duplicidades e erros sem depender de outro nível e sem alcançar cadastros do país inteiro.

**Como** responsável pela privacidade **quero** que toda leitura nominal de presença feita por operador declare a finalidade e fique na auditoria, **para** cumprir a marca `*` da matriz (RNF-07, ADR-0030).

## Contexto

A Etapa 6 (CHANGELOG 2.18) deixou quatro dívidas, e nenhuma foi resolvida até a 2.20. Esta entrega fecha duas:

1. **CA-27 / ADR-0027.** A coordenadora só mescla ou corrige participante com **presença ou igreja de referência** em EBI onde atua. Os casos fora desse escopo vão para a chefe. Hoje:
   - `ParticipantMergeController` olha só a igreja de referência (`AutorizacaoParticipante::cobreTerritorio`), e a presença não conta.
   - A chefe segue a mesma regra da coordenadora.
   - Não existe rota de correção. `GerirParticipante::corrigir` existe, mas ninguém a chama.
2. **Finalidade nas listas e no histórico (§8 item 4 do design da Etapa 6).** `GET /sessions/{id}/attendance` e `GET /participants/{id}/attendance` devolvem nomes e presenças sem finalidade e sem auditoria.

Decisões do produto (26/11/2026):

- **Mesclagem:** a coordenadora precisa dos **dois** cadastros ligados ao EBI dela; para a chefe, basta **um**. Desfazer segue a mesma regra.
- **Correção:** criar `PATCH /participants/{id}`, com a mesma regra de escopo.
- **Finalidade:** obrigatória para todo operador, nas duas rotas. O responsável fica isento. O portal envia uma finalidade fixa.
- **Presença só liga cadastro sem igreja de referência** (proposta do arquiteto depois da revisão 1, aprovada pelo produto em 26/11/2026).
- **Revisão 2 (26/11/2026):** a correção do principal também atualiza o snapshot da mesclagem; aceitos os riscos de a chefe alcançar qualquer cadastro como duplicado e de não conseguir desfazer quando o principal herda a igreja de outro EBI. A busca da sessão encontra qualquer cadastro do país, então lançar uma presença não pode dar escopo sobre o cadastro de outra igreja.

## Definição usada nos critérios

Um participante está **ligado** ao escopo do ator quando vale uma das condições:

- (a) ele tem igreja de referência, e o escopo do ator cobre essa igreja ou o EBI dela (regra atual);
- (b) ele **não tem** igreja de referência, e tem ao menos uma presença com `status = valida` numa sessão de EBI coberto pelo escopo do ator.

Presença `estornada` não liga. Participante com igreja de referência fora do escopo **não** fica ligado por presença. O `super_admin` está sempre ligado.

## Critérios de aceite

- **CA1.** Dado um participante sem igreja de referência, com presença válida numa sessão do EBI da coordenadora, quando ela o mescla com outro cadastro ligado ao EBI dela, então a resposta é `200` e o duplicado fica `mesclado`.
- **CA2.** Dado um par em que só um dos cadastros está ligado ao EBI da coordenadora, quando ela tenta mesclar, então a resposta é `403 acesso_negado` e nenhum dos cadastros muda.
- **CA3.** Dado o mesmo par do CA2, quando a chefe desse EBI tenta mesclar, então a resposta é `200`. Se nenhum dos dois estiver ligado ao EBI da chefe, a resposta é `403`.
- **CA4.** Dado um participante sem igreja de referência cuja única presença no EBI está `estornada`, quando a coordenadora tenta mesclá-lo, então a resposta é `403`.
- **CA5.** Dada uma mesclagem feita, quando alguém pede para desfazê-la, então vale a regra do CA1 ao CA3 sobre o principal e o duplicado guardado no snapshot: a coordenadora precisa dos dois, a chefe de um. Fora disso, a resposta é `403` e o estado não muda.
- **CA6.** Dado um participante ligado ao EBI da coordenadora (ou da chefe), quando ela envia `PATCH /participants/{id}` com campos permitidos e `motivo`, então a resposta é `200` com o participante corrigido. É gravado um evento `participante.corrigido` com o motivo, os campos que realmente mudaram, o estado anterior (`antes`) e `escopo` igual ao escopo de atuação (`ebi:<id>`).
- **CA7.** Dado o `PATCH /participants/{id}`, as respostas de erro são estas, e nenhuma grava alteração nem evento:
  - participante não ligado ao escopo do ator: `403`;
  - corpo sem `motivo`, `motivo` com mais de 255 caracteres, data de nascimento futura ou igreja inexistente: `422`;
  - nenhum campo diferente do valor atual: `422 nada_a_corrigir`;
  - nova igreja de referência fora do escopo do ator: `422 igreja_fora_do_escopo`;
  - participante `mesclado`: `409 participante_mesclado`;
  - papel sem `participante.corrigir` (auxiliar, `adm_igreja`, responsável): `403`.
- **CA8.** Dado um operador com acesso à sessão, quando ele chama `GET /sessions/{id}/attendance` sem `finalidade`, então a resposta é `422 finalidade_obrigatoria`. Com finalidade acima de 255 caracteres, a resposta é `422 finalidade_invalida`. Com uma finalidade válida, a resposta é `200` e é gravado um evento `presenca.lida` com a finalidade em `motivo`, `escopo = ebi:<id>` e `metadados_minimizados = { sessao_id, participante_ids }`.
- **CA9.** Dado um operador com acesso ao participante, quando ele chama `GET /participants/{id}/attendance` sem `finalidade`, então a resposta é `422 finalidade_obrigatoria`. Com finalidade, a resposta é `200` e é gravado um evento `presenca.lida` com `metadados_minimizados = { participante_id }`. Recurso inexistente continua respondendo `404`, e recurso fora do escopo continua respondendo `403`, antes da checagem da finalidade.
- **CA10.** Dado o responsável com vínculo aprovado, quando ele chama `GET /me/participants/{id}/attendance` sem `finalidade`, então a resposta é `200` e nenhum `presenca.lida` é gravado.
- **CA11.** Dado o portal, quando a tela Presença carrega a lista da sessão, então a chamada leva `finalidade=registro_presenca`. Quando o operador abre o diálogo Histórico em Participantes, a chamada leva `finalidade=consulta_historico`. Em Dependentes, o histórico continua sem finalidade.
- **CA12.** Dado um participante com igreja de referência de outra igreja e presença válida no EBI da coordenadora, quando ela tenta mesclá-lo ou corrigi-lo, então a resposta é `403` e nada muda.
- **CA13.** Dado o `super_admin`, quando ele corrige ou mescla um participante sem igreja de referência e sem presença, então a resposta é `200`.
- **CA14.** Dado um principal com mesclagem pendente, quando ele é corrigido pelo `PATCH` e a mesclagem é desfeita depois, então a correção continua no principal.

## Fora do escopo

- **Contingência em lote e aviso dos 12 anos (RF-15).** Continuam como dívidas, por decisão do produto. O aviso depende do módulo de Notificações (E12).
- **Tela de correção no portal.** Esta entrega cria só a API.
- **Mesclar presenças.** A mesclagem continua movendo só os vínculos de responsável.
- **Correção pelo responsável** ("corrige dados permitidos" na matriz). Seria outra rota, por propriedade.
- **Escopo nos eventos de mesclagem e de cadastro.** `participante.mesclado`, `participante.mesclagem_desfeita` e `participante.cadastrado` continuam com `escopo = null` (visíveis só ao `super_admin`). Fica registrado como dívida.

## Dependências e premissas

- O papel `coordenadora_chefe` (E07, ADR-0033) já tem `participante.mesclar` e `participante.corrigir` na matriz (`AutorizadorOperacao`).
- O sistema não está em produção. A finalidade obrigatória quebra clientes antigos das duas rotas, e só o portal as usa.
- Não há migração de banco. `evento_auditoria.motivo` continua `varchar(255)`, e os limites de `motivo` e `finalidade` respeitam esse tamanho.
- A decisão "presença só liga cadastro sem igreja" estreita a leitura do ADR-0027 e será registrada nele.
