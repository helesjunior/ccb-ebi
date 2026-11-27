# Wiki da versão 2.22

**Como** usuário do portal (coordenação, auxiliar, responsável e administração) **quero** que o manual em `docs/wiki/` descreva o que mudou na 2.22 **para** usar as telas novas sem precisar perguntar a ninguém.

## Contexto

A versão 2.22 (PR #48, já na `main`) mudou o que o usuário vê na tela, mas o wiki não foi atualizado. O resumo está em `docs/CHANGELOG.md`, na entrada 2.22, e os detalhes em `docs/esteira/2026-11-26-dividas-acumuladas/`. Isso descumpre a regra de `docs/wiki/Manutencao-do-wiki.md`: "se mudou o que o usuário vê ou faz, mudou o manual".

Hoje o wiki não menciona:
1. o botão **Corrigir** em Participantes;
2. o aviso dos 12 anos, em Meus dependentes e no Início da coordenação;
3. o lançamento em lote da contingência (**Lançar ‹N› presenças**);
4. o botão **Desistir da troca** em Minhas escalas;
5. a auditoria por território dos eventos de participante;
6. as regras novas: a decisão de exceção só vale no próprio território, a igreja do cadastro precisa existir e estar no escopo, e o motivo tem limite de tamanho.

O wiki também manda o responsável a telas e abas que não aparecem para ele:
- a tela Exceção inclusiva (`Fluxo-do-responsavel.md` §4) e a tela Participantes (`Fluxo-do-responsavel.md:71`). As duas usam a lista `PARTICIPANTES` de `frontend/src/components/layout/ListaNav.tsx:69-78`, que não inclui `responsavel`;
- a aba **Solicitar** de Vínculos (`Fluxo-do-responsavel.md` §1), que só aparece para quem pode buscar participante (`frontend/src/pages/Vinculos.tsx:94-101,209`).

O aviso novo dos 12 anos manda o responsável falar com a coordenação, então o texto atual contradiz a tela.

## Onde fica cada conteúdo

O conteúdo completo de cada assunto fica numa página só: a que já tem o passo a passo daquela tela. As outras páginas trazem uma frase e um link. Os critérios abaixo seguem esta tabela.

| Assunto | Conteúdo completo | Frase + link |
|---|---|---|
| Corrigir cadastro | `Participantes.md` | `Fluxo-da-coordenacao.md` |
| Contingência em lote | `Sessoes-e-agenda.md` | `Fluxo-da-coordenacao.md` |
| Aviso dos 12 anos, lado da coordenação | `Excecao-inclusiva.md` | `Fluxo-da-coordenacao.md`, `Mapa-das-telas.md` |
| Aviso dos 12 anos, lado do responsável | `Fluxo-do-responsavel.md` | `Excecao-inclusiva.md`, `Mapa-das-telas.md` |
| Desistir da troca | `Fluxo-do-auxiliar.md` (onde já está o passo a passo de "Pedir troca") | `Escalas.md`, `Mapa-das-telas.md` |
| Vínculo do responsável | `Fluxo-do-responsavel.md` §1 | `Participantes.md`, seção "Vínculo com o responsável" |

## Critérios de aceite

- **CA1** — Dado `docs/wiki/Participantes.md`, então a seção "Corrigir um cadastro" traz:
  - o passo a passo com os nomes da tela (**Corrigir**, **Nome completo**, **Nome social (opcional)**, **Data de nascimento**, **Igreja de referência (opcional)**, **Motivo da correção**, **Salvar correção**);
  - que o campo de igreja recebe o identificador da igreja, não o nome;
  - quem pode corrigir: a coordenação, só em cadastro ligado ao seu EBI, e o Super Admin;
  - as quatro mensagens de recusa, que aparecem no próprio diálogo, e o aviso em inglês que aparece quando o identificador de igreja não existe.

  A tabela "Quem faz o quê" ganha a linha `| Corrigir cadastro | Coordenação, Super Admin |`. `Fluxo-da-coordenacao.md` §7 cita **Corrigir** com um link para [Participantes](Participantes).
- **CA2** — Dado `docs/wiki/Participantes.md`, seção "Cadastrar", então ela diz que, para quem cadastra pela tela **Participantes**, a igreja de referência precisa existir e estar no escopo. Ela mostra a mensagem de recusa "A igreja de referência não está no seu escopo." e o aviso em inglês para identificador que não existe. Nem essa seção nem a tabela "Quem faz o quê" atribuem ao responsável a tela **Participantes**.
- **CA3** — Dado `docs/wiki/Sessoes-e-agenda.md`, então a subseção "Lançar a lista em papel (contingência)" traz:
  - os passos numerados: **Presença** → **Lançar lista em papel** → motivo (3 a 255 caracteres) → marcar → **Lançar ‹N› presenças**;
  - o resumo "‹n› lançadas, ‹m› já existiam";
  - o aviso "Não lançadas: ‹nomes›";
  - o limite de 100 crianças por lançamento;
  - que só vale para sessão encerrada;
  - a linha `| Lançar lista em papel (contingência) | Coordenação, Admin da igreja |` em "Quem mexe em quê".

  `Fluxo-da-coordenacao.md` §5 cita **Lançar lista em papel** com um link para [Sessões e agenda](Sessoes-e-agenda).
- **CA4** — Dado `docs/wiki/Sessoes-e-agenda.md`, seção "Exceções de calendário", então ela diz que o motivo é obrigatório, com 3 a 247 caracteres.
- **CA5** — Dado `docs/wiki/Fluxo-do-responsavel.md`, então:
  - a §2 traz o texto do aviso dos 12 anos e diz o que fazer;
  - a §4 orienta a pedir a exceção à coordenação do EBI, sem passos de tela, sem **ID do participante** e sem a imagem `22-excecao.png`;
  - a resposta da dúvida "Posso cadastrar meu filho sozinho?" é pedir à coordenação do EBI, com link para a §5, e não cita nenhuma tela.
- **CA6** — Dado `docs/wiki/Excecao-inclusiva.md`, então a seção "Aviso dos 12 anos" explica o bloco do Início:
  - quem vê: coordenadora ou coordenadora chefe atuando no EBI;
  - o que lista: participantes ativos cuja igreja de referência é a igreja do EBI, sem exceção aprovada vigente;
  - o que fazer com criança sem igreja de referência, e que a criança de outra igreja aparece para a coordenação de lá;
  - a mensagem de lista vazia e a mensagem de erro;
  - que abrir o Início registra a consulta na auditoria.

  "Decidir a exceção" diz que a coordenação só decide exceções do próprio EBI e que, fora dele, aparece "Exceção não encontrada.". A página deixa de dizer que a coordenação pede "no lugar do responsável". `Fluxo-da-coordenacao.md` traz uma frase sobre o bloco, com link para [Exceção inclusiva](Excecao-inclusiva).
- **CA7** — Dado `docs/wiki/Fluxo-do-auxiliar.md`, então a §4 explica **Desistir da troca**:
  - quando o botão aparece: a situação é **Troca solicitada**;
  - a confirmação "Desistir da troca?";
  - o sucesso "Pedido de troca cancelado.";
  - a volta para **Escalado** ou **Confirmado**;
  - o erro "Esta troca já foi decidida.".

  `Escalas.md`, seção "Trocas", traz uma frase sobre a desistência, com link para [Fluxo do auxiliar](Fluxo-do-auxiliar).
- **CA8** — Dado `docs/wiki/Fluxo-da-administracao-superior.md`, seção "Auditoria", então ela diz três coisas:
  - os eventos de participante aparecem para quem audita o território em que a ação foi feita;
  - pedido de vínculo, pedido de exceção e cadastro com igreja ficam no território da igreja da criança;
  - os eventos de criança sem igreja de referência nesses casos, os feitos no contexto central e os anteriores à 2.22 só aparecem para o Super Admin.

  O texto não promete que todo evento da criança aparece para o território dela.
- **CA9** — Dado `docs/wiki/Mapa-das-telas.md`, então as descrições de Início, Participantes, Minhas escalas e Meus dependentes mencionam o que mudou, e a de Exceção inclusiva deixa de dizer que o responsável pede pela tela.
- **CA10** — Dada qualquer página alterada, então nenhum link interno termina em `.md` e todo link aponta para uma página, imagem ou título que existe em `docs/wiki/`. Caminho de erro: um link quebrado ou com `.md` reprova a entrega.
- **CA11** — Dado `docs/wiki/Fluxo-do-responsavel.md`, então:
  - a §1 orienta o responsável a pedir o vínculo à coordenação do EBI, sem citar **Vínculos**, **Solicitar** nem a imagem `23-vinculos.png`, e sem prometer anexo pelo portal;
  - a dúvida "Meu filho não aparece em Meus dependentes." manda falar com a coordenação, não olhar **Vínculos**.

  `Participantes.md`, seção "Vínculo com o responsável", diz que o responsável pede à coordenação do EBI.

## Fora do escopo

- Publicar no GitHub Wiki. É o passo manual feito depois do merge (`Manutencao-do-wiki.md`, "Publicar").
- Funcionalidades da lista "Fora desta versão" do CHANGELOG 2.22.
- Rotas, códigos HTTP e o campo `escopo`. O wiki descreve o que a pessoa faz.
- Página nova e mudança em `Home.md`, porque nenhuma tela nova entrou no menu.
- Mudar código ou `docs/CHANGELOG.md`.
- **Resolver o vínculo do responsável no produto.** Nenhuma tela liga a conta do responsável a uma criança: a aba Solicitar não aparece para ele, e o pedido aberto pela coordenação grava a conta de quem pediu (`backend/app/Domain/Participantes/Services/GerenciadorVinculoResponsavel.php:25-33`). O wiki só deixa de mandar o responsável a uma aba que ele não vê (CA11). O furo fica registrado como risco em `02-especificacao.md`.
- **Onde achar o ID do participante e o identificador da igreja.** As telas de busca mostram nome, nascimento e matrícula, nunca o ID (`frontend/src/pages/Participantes.tsx:177-181`; `frontend/src/pages/Inicio.tsx:67-70`). A tela Exceção inclusiva pede **ID do participante** (`frontend/src/pages/ExcecaoInclusiva.tsx:127`), e o campo de igreja pede o identificador (`frontend/src/components/CorrigirParticipante.tsx:119`). Não existe fonte que diga onde o usuário encontra esses valores, e o seletor de igreja está em "Fora desta versão" no CHANGELOG 2.22. É um limite conhecido da tela. O wiki diz que o campo recebe o identificador e não inventa onde obtê-lo.
- Outras citações a Vínculos em páginas que esta demanda não toca, como `Minha-conta.md:79-82` e `Mapa-das-telas.md:19`. O texto delas continua correto: o responsável vê a tela Vínculos, só não vê a aba Solicitar.

## Dependências e premissas

- Branch `feat/wiki-2-22`, criada a partir da `main` com o PR #48.
- O comportamento descrito é o do código atual. Cada texto tem fonte em `02-especificacao.md`.
- As capturas (T8) exigem Docker, Node.js 24+ e um navegador controlável por quem implementa. Se faltar algum, a T8 registra a pendência em `06-entrega.md`.
