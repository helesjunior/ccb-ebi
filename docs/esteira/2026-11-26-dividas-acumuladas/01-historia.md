# Dívidas acumuladas (2.16 a 2.21)

**Como** coordenadora, responsável, auxiliar e auditora territorial **quero** fechar as cinco dívidas pendentes do `docs/CHANGELOG.md` **para** usar no portal o que hoje só existe na API, não perder prazo de exceção etária e enxergar na auditoria os eventos do meu território.

## Contexto

O `docs/CHANGELOG.md` acumula dívidas declaradas nas versões 2.16, 2.18, 2.19, 2.20 e 2.21:

1. **Tela de correção de cadastro.** A rota `PATCH /participants/{id}` existe desde a 2.21, mas não há tela.
2. **Aviso dos 12 anos (RF-15).** No dia em que faz 12 anos, sem exceção inclusiva aprovada, o participante fica bloqueado na presença. Ninguém é avisado antes (`docs/02-regras-de-negocio/elegibilidade-etaria.md:89`).
3. **Contingência em lote.** A lista em papel é lançada uma pessoa por vez, repetindo o motivo a cada uma.
4. **`escopo` nos eventos de auditoria.** Cadastro, mesclagem, leitura de participante, vínculo e exceção gravam `escopo = null`. Por isso só o `super_admin` os vê em `GET /audit-events`.
5. **"Desistir" em Minhas escalas.** A API cancela o próprio pedido de troca (`POST /schedule-swaps/{id}/cancel`), mas a tela só mostra "Troca pedida; aguardando aprovação".

Achados durante a leitura e a revisão, que também entram nesta demanda:

- **Limite de motivo (CA12).** `evento_auditoria.motivo` é `varchar(255)`. Mesmo assim, a contingência, o estorno, o cancelamento e a correção de sessão e a transferência de igreja aceitam motivo de até 500 caracteres, e a exceção de agenda não tem limite. Um motivo longo derruba a gravação no PostgreSQL.
- **Território na decisão de exceção (CA13).** `PATCH /age-exceptions/{id}` só confere o papel. Hoje a coordenadora do EBI A consegue decidir a exceção de uma criança do EBI B. Decisão do humano: corrigir nesta demanda.
- **Igreja do cadastro sem validação (CA8).** `igreja_referencia_id` aceita qualquer texto em `POST /participants` e em `POST /participants/assisted`. Com o escopo derivado da igreja, um id inexistente gravaria um território falso, e um id longo estouraria `evento_auditoria.escopo` (`varchar(255)`).

## Critérios de aceite

### Tela de correção de cadastro
- **CA1** — Dado um usuário atuando como `coordenadora`, `coordenadora_chefe` ou `super_admin`, quando ele busca em Participantes, clica em "Corrigir" numa linha, muda o nome, informa um motivo de 3 a 255 caracteres e salva, então o portal chama `PATCH /participants/{id}` só com os campos alterados e o motivo, a linha mostra o nome novo e aparece o aviso "Cadastro corrigido.".
- **CA2** — Dado o formulário de correção aberto, quando a API responde `409 participante_mesclado`, `422 nada_a_corrigir` ou `422 igreja_fora_do_escopo`, então o diálogo continua aberto e mostra o `detail` da resposta. Dado um usuário atuando com outro papel (por exemplo `auxiliar` ou `adm_igreja`), então o botão "Corrigir" não aparece.

### Aviso dos 12 anos (RF-15)
- **CA3** — Dado um responsável com dependente que faz 12 anos entre hoje e daqui a 30 dias (inclusive), no fuso da igreja de referência, e sem exceção `aprovada` vigente na data do aniversário, quando ele abre "Meus dependentes", então aparece o aviso "‹nome› faz 12 anos em ‹dd/mm/aaaa›. Sem exceção inclusiva aprovada, a presença fica bloqueada a partir dessa data. Se for o caso, peça a exceção à coordenação do EBI." Não gera aviso o dependente fora da janela nem o que tem exceção vigente. Se `GET /me/participants` falhar, aparece a mensagem de erro que a tela já mostra hoje.
- **CA4** — Dado um usuário atuando como `coordenadora` ou `coordenadora_chefe` com escopo `ebi:X`, quando ele abre o Início, então aparece o bloco "Fazem 12 anos nos próximos 30 dias", com os participantes ativos da igreja do EBI X que atendem a regra do CA3, ordenados pela data. Sem ninguém na janela, aparece "Ninguém faz 12 anos nos próximos 30 dias.". A leitura grava um `participante.lido` com escopo `ebi:X` e a finalidade como motivo.
- **CA5** — Dado `GET /ebis/{id}/age-alerts` sem finalidade, então a resposta é `422 finalidade_obrigatoria`. Dado um ator sem `excecao.decidir` sobre aquele EBI (outro EBI, auxiliar, adm_regional), então a resposta é `403`. Dado um `super_admin` e um EBI que não existe, então a resposta é `404`. Para os demais papéis, um EBI que não existe dá `403`, porque o Gate roda antes e a resposta não revela se o EBI existe. No Início, um erro da API mostra "Não foi possível carregar os aniversários." e o resto da tela continua funcionando.

### Contingência em lote
- **CA6** — Dada uma sessão `encerrada`, quando a coordenadora, no modo contingência, marca vários participantes (de buscas diferentes), informa um motivo e clica em "Lançar N presenças", então o portal faz um único `POST /sessions/{id}/contingency-attendance/batch`. Cada participante elegível ganha uma presença `origem=contingencia` e um evento `presenca.contingencia` próprio. Quem já tinha presença volta como `ja_existia`, sem novo evento. O toast mostra "‹n› lançadas, ‹m› já existiam".
- **CA7** — Dado um lote com um participante inelegível ou inexistente, então as demais linhas são gravadas e essa linha volta com `erro` (`inelegivel` com `motivo_bloqueio`, ou `not_found`). A tela mostra essas linhas pelo nome. Lote com sessão não `encerrada` → `409 sessao_em_estado_invalido`, sem gravar nada. Lote vazio, com mais de 100 ids ou com ids repetidos → `422`. Motivo com menos de 3 ou mais de 255 caracteres → `422`. Ator sem `sessao.gerenciar` no EBI → `403`.

### Escopo nos eventos de auditoria
- **CA8** — Quando ocorre cada evento da tabela de escopo (`02-especificacao.md`, seção "Escopo por evento"), então `evento_auditoria.escopo` recebe o valor definido ali. Ele só fica `null` nos casos que a tabela prevê. Dado um cadastro (`POST /participants` ou `POST /participants/assisted`) com `igreja_referencia_id` que não existe em `igrejas`, então a resposta é `422`, e nada é gravado.
- **CA9** — Dado um `adm_setor` do setor S, que contém a igreja A, quando ele consulta `GET /audit-events`, então vê o `participante.cadastrado` de um dependente da igreja A cadastrado pelo responsável. Não vê o de um dependente da igreja B, que fica em outro setor, nem o de um dependente cadastrado sem igreja. O `adm_igreja` não tem `auditoria.ler`, por isso o critério usa o `adm_setor`.

### Desistir da troca
- **CA10** — Dada uma vaga minha com troca `pendente` aberta por mim, quando abro Minhas escalas, então a linha mostra o botão "Desistir da troca". Ao confirmar no diálogo, o portal chama `POST /schedule-swaps/{troca_id}/cancel`, mostra "Pedido de troca cancelado." e a linha volta ao status anterior ao pedido (`escalado` ou `confirmado`). `GET /me/schedules` devolve `troca_id` só nessa situação; nas outras, `null`.
- **CA11** — Dada uma troca que já foi decidida enquanto a tela estava aberta, quando clico em "Desistir da troca" e confirmo, então a API responde `409 troca_ja_decidida`, a tela mostra o `detail` ("Esta troca já foi decidida.") e recarrega a lista.

### Limite de motivo
- **CA12** — Quando um motivo passa do limite, a resposta é `422`, sem `500` do banco. O limite é 255 caracteres na contingência (individual e lote), no estorno, no cancelamento e na correção de sessão e no `reason` da transferência de igreja. Na exceção de agenda, o motivo vai de 3 a 247 caracteres, porque o cancelamento automático grava `"agenda: " + motivo` na auditoria. No portal, os campos de motivo da contingência e da exceção de agenda têm `maxLength` e rótulo com o mesmo limite. Na exceção de agenda, o botão "Criar exceção" fica desabilitado com menos de 3 caracteres.

### Território na decisão de exceção
- **CA13** — Dada uma coordenadora do EBI A, quando ela envia `PATCH /age-exceptions/{id}` para a exceção de um participante ligado só ao EBI B, então a resposta é `404 not_found` ("Exceção não encontrada."), o status da exceção não muda e nenhum `excecao.decidida` é gravado. A coordenadora do EBI B decide normalmente. O `super_admin` decide qualquer exceção.

## Fora do escopo

- As outras dívidas da 2.19: notificação para a equipe e a chefia, conflito sem bloqueio opcional e cancelamento ou cópia de escala.
- E-mail, push e preferências de notificação do RF-15 (E12). O aviso existe só na tela, calculado quando a tela é aberta, sem rotina agendada.
- Mesclar presenças (dívida da 2.21).
- Remover a rota individual `POST /sessions/{id}/contingency-attendance`. Ela continua existindo, só com o novo limite de motivo.
- Restringir a presença devolvida pela contingência (individual e lote) aos participantes do território; a busca da sessão já cobre o país inteiro (ADR-0027).
- Seletor de igreja no formulário de correção. O campo continua sendo o ID da igreja, como no cadastro (`FormularioParticipante.tsx`).

## Dependências e premissas

- Branch `feat/dividas-acumuladas`, já criada.
- Premissa: "participantes do EBI" são os participantes `ativo` cuja `igreja_referencia_id` é a igreja sede do EBI. É o mesmo critério que `AutorizacaoParticipante::cobreIgreja` usa.
- Premissa: a coordenadora chefe atua com escopo `ebi` (`backend/database/seeders/UsuariosTesteSeeder.php:41`). Com outro tipo de escopo, o bloco do Início não aparece.
- O aviso ao responsável não leva para a tela "Exceção inclusiva", porque essa tela não aparece no menu do papel `responsavel` (`ListaNav.tsx`, lista `PARTICIPANTES`). O aviso orienta a falar com a coordenação.
- Decisões do humano, registradas na rodada 1 da revisão:
  - o território em `PATCH /age-exceptions/{id}` é corrigido aqui;
  - o limite de 255 está confirmado, incluindo a transferência, e a agenda fica com 3 a 247;
  - o escopo pela igreja é `ebi:<id>` quando a igreja tem EBI, senão `igreja:<id>`, senão `null`.
- Premissa: uma exceção `solicitada` (ainda não decidida) não tira o participante do aviso.
