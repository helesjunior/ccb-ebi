# Revisão

## Rodada 1 — Veredito: REPROVADO

As citações de arquivo e linha de `02-especificacao.md` foram conferidas no código e batem. Os problemas estão no que o plano manda escrever e em como o plano de testes confere.

### Bloqueante

- `01-historia.md` CA6 e CA7 × `02-especificacao.md` (decisão "passo a passo só na página de referência") × `03-plano-implementacao.md` T4.1 e T5.3 × `04-plano-testes.md` CT8 e CT10 — os critérios exigem que **as duas** páginas expliquem tudo (CA6: quem vê, o que lista, lista vazia, erro e auditoria em `Fluxo-da-coordenacao.md`; CA7: quando aparece, confirmação, sucesso, volta e erro em `Escalas.md`). O plano põe uma frase com link em cada uma, e os casos de teste só procuram o título do bloco e o nome do botão. Entregue como planejado, CA6 e CA7 reprovam na leitura literal. Além disso, em CA7 o passo a passo vai para a página de fluxo (`Fluxo-do-auxiliar.md`) e a frase vai para a de referência (`Escalas.md`), o inverso da decisão da especificação. — Reescrever CA6 e CA7 dizendo qual página leva o conteúdo completo e qual leva só frase e link, e alinhar T4, T5, CT8 e CT10 a isso.

- `04-plano-testes.md` CT1, CT4, CT5, CT8, CT10, CT12, CT13 — usam `A\|B\|C` como alternância. A ferramenta `Grep` é ripgrep, em que `\|` é uma barra literal: as buscas voltam vazias mesmo com a página correta. O próprio plano usa `\|` como barra literal em T1 ("Pronto quando": `Corrigir cadastro \| Coordenação, Super Admin`), então a mesma sintaxe tem dois sentidos. Mesmo corrigida, a alternância não prova que os três textos existem. — Uma busca por texto, cada uma com resultado esperado próprio.

- `03-plano-implementacao.md` T8.2 e `04-plano-testes.md` CT16 — mandam procurar cada texto "igual" no código, mas quatro deles não existem literalmente: "Lançar N presenças" é `Lançar ${marcados.length} presenças` (`Presenca.tsx:440`); "‹n› lançadas, ‹m› já existiam" e "Não lançadas: ‹nomes›" são templates (`Presenca.tsx:167,175`); o aviso dos 12 anos está quebrado em três linhas de JSX (`Dependentes.tsx:75-77`). A única mitigação do risco "texto diferente da tela" dá falso negativo, e quem implementa não tem como saber qual trecho buscar. — Listar em CT16 o trecho exato a buscar para cada texto (por exemplo `já existiam`, `Não lançadas:`, `faz 12 anos em`, `peça a`), com arquivo.

- `04-plano-testes.md` — partes de critério sem caso: "quem pode corrigir" e a linha da tabela (CA1), "quem pode lançar" (CA3), "quem vê" e "a consulta fica registrada na auditoria" (CA6), a confirmação "Desistir da troca?" (CA7). Com a alternância corrigida (bloqueante acima), CT5 e CT12 passam hoje, sem mudança nenhuma: "Encerrada" e "Admin de setor" já estão nas páginas (`Sessoes-e-agenda.md:22`, `Fluxo-da-administracao-superior.md:29`), e `Grep` não restringe busca a uma subseção. — Acrescentar um caso por parte faltante e trocar CT5 e CT12 por textos que só existem depois da mudança.

- `03-plano-implementacao.md` T1.1 e `02-especificacao.md` linha "Igreja no cadastro" — (a) manda escrever em "Cadastro assistido" que a regra da igreja vale ali, mas a tela não tem campo de igreja nem envia o valor (`frontend/src/pages/CadastroAssistido.tsx:55-60`, rótulos nas linhas 104-154). O manual descreveria uma regra sobre algo que o usuário não vê; CA2 só pede "Cadastrar". (b) O texto ditado diz "para a coordenação e a administração", e o código aplica a regra a todo mundo que não é responsável, inclusive o auxiliar (`ParticipantController.php:186-196`; `AutorizadorOperacao.php:114-117`). A especificação diz "quem não é responsável" e o plano diz outra coisa. — Tirar a frase do cadastro assistido da T1 e da tabela "Páginas afetadas"; trocar o sujeito da frase por quem cadastra pela tela **Participantes**.

- `03-plano-implementacao.md` T6.1 e `01-historia.md` CA8 — o texto ditado promete ao Admin de setor e ao regional os eventos de participante "do território da criança". Não é o que o sistema faz: a maioria dos eventos grava o contexto de quem agiu (`EscopoAuditoria::doContexto`), então uma leitura feita pelo Super Admin não aparece para o setor; e pedido de vínculo, pedido de exceção e cadastro pelo responsável de criança **sem igreja de referência** continuam sem território na 2.22 (`EscopoAuditoria::daIgreja` devolve `null`; `docs/esteira/2026-11-26-dividas-acumuladas/01-historia.md` CA9 diz isso). O texto dá a entender que só eventos antigos ficam de fora, e um auditor leria "não aparece" como "não aconteceu". Viola a regra da própria especificação: "o texto não pode prometer mais acesso do que o sistema dá". — Reescrever a frase: o evento aparece para quem audita o território onde a ação foi feita; eventos de criança sem igreja de referência e eventos anteriores à 2.22 só aparecem para o Super Admin. Ajustar CA8 e CT12.

### Recomendado

- `03-plano-implementacao.md` T5.1 — a linha nova entra na coluna "Situação" com "Troca pedida; aguardando aprovação". A situação que a tela mostra é **Troca solicitada** (`frontend/src/lib/formatos.ts:130`); a frase citada é um texto à parte na linha (`MinhasEscalas.tsx:298`). — Usar "Troca solicitada" na coluna e citar a frase na explicação.

- `03-plano-implementacao.md` T3.1 — falta dizer o que o bloco lista, exigido por CA6: só participantes ativos cuja **igreja de referência** é a igreja do EBI (`AvaliadorElegibilidade.php:101-103`). Criança sem igreja de referência ou de outra igreja não aparece, mesmo frequentando o EBI. A regra está na especificação e sumiu do plano. — Incluir na seção "Aviso dos 12 anos".

- `03-plano-implementacao.md` T3.1 — "abrir o bloco registra a consulta": não há o que abrir, o bloco carrega com o Início (`Inicio.tsx:27-44`). — Escrever "abrir o Início".

- `03-plano-implementacao.md` T1.2 — o campo **Igreja de referência (opcional)** é texto livre que recebe o identificador da igreja, não o nome (`CorrigirParticipante.tsx:119`; CHANGELOG 2.22, "seletor de igreja" fora da versão). O passo a passo não diz o que digitar, e "precisa existir" fica sem sentido para o usuário. — Dizer que o campo recebe o identificador e onde consegui-lo, ou registrar em "Fora do escopo" que isso não será explicado.

- `03-plano-implementacao.md` T1 e T3 — sobram contradições com a §4 nova que ficam em páginas já tocadas pela demanda: `Participantes.md:91` ("Pedir exceção inclusiva | Coordenação, responsável"), `Mapa-das-telas.md:94` ("Pedido de exceção (responsável)") e `Fluxo-do-responsavel.md:71` (manda o responsável a **Participantes → Cadastrar participante**, tela que ele também não tem, pela mesma lista `PARTICIPANTES` de `ListaNav.tsx:69-78` usada como prova na história). — Corrigir as três linhas nas tarefas que já editam essas páginas, ou listar em "Fora do escopo" com a justificativa.

- `03-plano-implementacao.md` T1, T2 "Pronto quando" — "uma ocorrência cada" falha com o próprio texto ditado: **Salvar correção** aparece no passo 5 e na observação. — Trocar por "ao menos uma".

- `03-plano-implementacao.md` T7 e `04-plano-testes.md` "Dados e ambiente" — o seed não tem criança na janela dos 12 anos (`ParticipanteSeeder.php:36-70`: 2, 5, 7, 8, 9 e 13 anos exatos), os vínculos do seed não têm usuário (`usuario_id` nulo, linha 105), e não há receita para a sessão encerrada nem para a troca pendente. Quem captura precisa adivinhar como montar o dado. — Escrever a receita (por exemplo: corrigir a data de nascimento de uma criança de demonstração pela tela **Corrigir**; qual usuário de teste vincular).

- `03-plano-implementacao.md` T7 "Pronto quando" — "data de modificação posterior a 27/11/2026" exclui captura feita hoje, não é conferível com `Grep`/`Glob` e o git não guarda essa data. — Trocar por "os arquivos aparecem em `git diff --stat main -- docs/wiki/images`".

- `02-especificacao.md` linha "Erros da correção" e T1.2 — falta a quarta recusa, "O participante não está ligado a um EBI onde você atua." (`AutorizacaoParticipante.php:158`). O plano diz "só o cadastro ligado ao seu EBI" e não diz o que aparece quando não é. — Incluir na lista "Se der errado".

### Opcional

- `04-plano-testes.md` "Como rodar" — `npm run lint && npm test` não confere nada desta demanda; o `git diff --stat main -- . ':!docs'` já cobre. Cortar.
- `03-plano-implementacao.md` T2.1 — o lote aceita no máximo 100 crianças por lançamento (`ContingenciaLotePresencaRequest.php:18`) e a tela não limita. Uma frase na subseção evita a dúvida.
- `03-plano-implementacao.md` T4.3 — **Corrigir** em `Fluxo-da-coordenacao.md` não é pedido por nenhum critério (CA1 só cita `Participantes.md`). Manter e citar no CA, ou cortar.
- `03-plano-implementacao.md` T7 — "Atende: CA3, CA9" não confere: nenhum dos dois critérios pede imagem.

## Rodada 2 — Veredito: REPROVADO

Bloqueantes da rodada 1: os seis foram resolvidos (CA6/CA7 × decisão; alternância `\|`; trechos fixos do CT16, conferidos um a um no código; partes de critério sem caso; cadastro assistido e sujeito da frase; texto da auditoria). Sobra um bloqueante novo, criado na reescrita.

### Bloqueante

- `03-plano-implementacao.md` T3.6, resposta de "Posso cadastrar meu filho sozinho?" — manda o responsável usar **Participantes → Cadastro assistido**. O texto não tem fonte na tabela "Fonte dos textos" e leva a um beco: o cadastro assistido grava o vínculo sem conta (`GerenciadorParticipante.php:165`, `usuario_id => null`), então a criança não aparece em **Meus dependentes** (`VerificadorPropriedadeParticipante.php:25`) e o aviso dos 12 anos nunca chega a esse responsável. A própria especificação afirma que nenhuma tela liga a conta do responsável à criança (Decisões, linha da §1) e deixou a §1 de fora por depender de decisão de produto; a T3.6 toma essa decisão por conta própria. Também contradiz a §5 da mesma página e `Participantes.md` "Cadastro assistido" (ferramenta presencial da coordenação), faz o responsável atestar o próprio termo, e "Pergunte antes se a criança já tem ficha" não o impede de criar duplicado, porque ele não tem busca. — Trocar a resposta por "peça à coordenação do EBI" (com link para a §5), sem citar tela; ou levar o caso para a mesma pendência da §1 e registrar a fonte e a consequência na especificação.

### Recomendado

- `03-plano-implementacao.md` T7.1 — `php artisan migrate --seed` roda o `DatabaseSeeder`, que não chama o `UsuariosTesteSeeder` (`backend/database/seeders/DatabaseSeeder.php:17-23`). `chefe@ebi.test`, `auxiliar@ebi.test` e `responsavel@ebi.test` não existem depois do passo 1, e o `tinker` do passo 3 grava `usuario_id` nulo sem acusar erro. Falta também dizer que o login pede o código TOTP de demonstração. — Acrescentar `php artisan db:seed --class=UsuariosTesteSeeder` e a nota do TOTP; dizer que o `tinker` é só para o ambiente local.

- `03-plano-implementacao.md` T7.4 e T7.5 — o seed não cria faixa de horário; sem ela "gere as sessões" não gera nada, e não há sessão de hoje nem futura. — Incluir o passo de criar a faixa (dia da semana de hoje) antes de gerar.

- `03-plano-implementacao.md`, regra geral da linha 7 × T2.1, CA3 e CT10 — a regra manda a parte variável entre ‹ ›, e a especificação escreve **Lançar ‹N› presenças**; T2, CA3 e CT10 exigem o literal `Lançar N presenças`. Quem seguir a regra reprova no CT10. — Escolher uma forma e usar nos quatro lugares.

- `03-plano-implementacao.md`, regras gerais — as páginas do wiki quebram linha perto de 100 colunas, e os casos buscam frases inteiras numa linha (`O participante não está ligado a um EBI onde você atua.`, `peça a exceção à coordenação do EBI`, entre outras). Texto correto quebrado no meio dá falso negativo em CT6, CT8, CT22, CT28 e CT29. — Regra: mensagem citada fica inteira numa linha.

- `03-plano-implementacao.md` T3.1 × `04-plano-testes.md` CT30 — o texto ditado da auditoria é "abrir o Início registra a consulta", sem negrito; o `abrir o **Início**` que o CT30 busca só existe no item do bloco. O caso passa mesmo sem a frase da auditoria. — Buscar `registra a consulta`.

- `03-plano-implementacao.md` T3.1, "corrija a igreja dela" — para criança **de outra igreja**, a coordenação recebe "O participante não está ligado a um EBI onde você atua." (`AutorizacaoParticipante.php:124-128`), a primeira recusa da própria T1. E a orientação sugere mudar a igreja de referência, que define quem enxerga a ficha, só para a criança entrar numa lista. — Limitar a orientação à criança sem igreja de referência; para a de outra igreja, dizer que o aviso sai para a coordenação de lá.

- `03-plano-implementacao.md` T3.1 — o botão **Exceção inclusiva** do bloco abre uma tela que pede **ID do participante**, e o bloco mostra nome, matrícula e data. O texto não diz onde obter o ID; a pessoa trava no passo seguinte ao aviso. Mesmo caso do identificador da igreja em T1.1: "peça à administração da igreja" não tem fonte dizendo onde a administração o encontra. — Dizer onde cada ID aparece, ou registrar os dois em "Fora do escopo" como limite conhecido da tela.

- `03-plano-implementacao.md` T1.2 e `01-historia.md` CA1 ("as quatro mensagens") — falta a recusa mais provável de um campo de texto livre: identificador que não existe (`CorrigirParticipanteRequest.php:23`, `exists:igrejas,id`). A mensagem sai em inglês, do validador (`backend/bootstrap/app.php:58`; `config/app.php:83`, sem `backend/lang`). — Acrescentar um item sem citar o texto: "aparece um aviso de dado inválido, em inglês; confira o identificador". Vale para "Cadastrar" também.

- `03-plano-implementacao.md` T3.2 — acrescenta a nota ao cartão, mas mantém `Excecao-inclusiva.md:24` ("A coordenação também pode pedir a exceção no lugar do responsável"), que contradiz a nota na linha seguinte. Do mesmo tipo, em página já editada pela T1: `Participantes.md:33-34` ("ou o próprio responsável") e `:88` ("Cadastrar | … responsável"), tela que o responsável não tem. — Remover a linha 24 na T3.2; corrigir as duas de `Participantes.md` na T1 ou listar em "Fora do escopo".

- `03-plano-implementacao.md` T7.8, T8.3 e "Pronto quando" da T8 — três itens dependem da "descrição do PR", que quem implementa não tem como escrever nem conferir. — Indicar um arquivo da pasta da demanda para o checklist e as pendências (T7 e §1), e conferir por ele.

- `01-historia.md`, "Fora do escopo", §1 — a consequência não está escrita: sem caminho de tela para o vínculo, o aviso da §2 (CA5) só aparece para responsável vinculado por fora da tela, como a própria T7.3 faz com `tinker`. — Dizer isso na pendência, para a decisão humana ser tomada sabendo.

### Opcional

- `04-plano-testes.md` CT16, linha "Lançar N presenças" — `presenças\`` deixa dúvida se a barra faz parte da busca. Buscar `} presenças`.
- `04-plano-testes.md` — CA5 "sem passos de tela" só tem o caso da imagem (CT23). Acrescentar `ID do participante` em `Fluxo-do-responsavel.md`, Vazio.
- `03-plano-implementacao.md` T7 "Pronto quando" — `git diff --stat` não lista arquivo novo não adicionado (`28-contingencia-lote.png`). Usar `git status --short docs/wiki/images`.
- `02-especificacao.md`, linha "Auditoria por território" — a fonte da decisão de vínculo é `GuardianRequestController.php:51`; `GuardianController.php:102` é a listagem.
- `03-plano-implementacao.md` T4.1 — seção numerada "0" para uma frase; a frase cabe no parágrafo de abertura da página.

## Aprovação humana

Aprovado pelo humano em 27/11/2026 15:31, opção "Aprovar com a correção": o bloqueante da rodada 2 (T3.6) passa a responder "peça à coordenação do EBI", e os recomendados da rodada 2 entram sem nova revisão. Decisões: §1 do Fluxo do responsável corrigida no texto (o furo de produto fica como risco); capturas feitas agora pelo implementador, com pendência em 06-entrega.md se falharem.
