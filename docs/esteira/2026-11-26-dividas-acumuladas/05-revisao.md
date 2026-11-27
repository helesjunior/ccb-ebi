# Revisão

## Rodada 1 — Veredito: REPROVADO

### Bloqueante

- `02-especificacao.md`, seção 3 (linha `excecao.decidida` da tabela "Escopo por evento" e a frase "vale o contexto de quem decide, que já foi conferido no território") e seção "Segurança e autorização" ("Achado fora do escopo"); `03-plano-implementacao.md`, T6 e T17 — A frase é falsa para a exceção: `AgeExceptionController::update` (linhas 52-64) só passa por `can:excecao.decidir` (`routes/api.php:214-215`), sem conferir o território do participante. A própria especificação admite o furo e o adia, mas T6 altera esse mesmo método para gravar `doContexto`. Resultado: a coordenadora do EBI A decide a exceção de uma criança do EBI B e o evento fica em `ebi:A`; a trilha do território B, que esta demanda existe para abrir, nunca mostra a decisão. O CT-17 ainda fixa esse comportamento como correto. — Corrigir nesta demanda, em T6: carregar a exceção e o participante antes de decidir e negar com o mesmo padrão de `GuardianRequestController::update` (linha 41, `cobreTerritorio`; `AutorizacaoParticipante` já está injetado no controller). Dizer na especificação como o controller obtém a exceção (o contrato `GerirExcecaoEtaria` pode precisar de um `encontrar`) e qual status devolve. Acrescentar caso de teste: coordenadora de outro EBI → negado, sem alteração de status e sem evento. Retirar a dívida de T17. Alternativa: aceite humano explícito do adiamento, registrado em "Respostas à revisão", e a frase corrigida.

- `02-especificacao.md`, seção 3, `EscopoAuditoria::daIgreja` e linhas `participante.cadastrado` (origem `responsavel`), `vinculo.solicitado` e `excecao.solicitada` — `igreja_referencia_id` do cadastro não é validado contra Organização: `CadastrarParticipanteRequest.php:23` e `CadastrarAssistidoRequest.php:23` aceitam qualquer `string` de até 255, e a coluna `participantes.igreja_referencia_id` é `string` sem chave estrangeira. Dois efeitos do desenho novo: (a) `"igreja:" + id` com id acima de 248 caracteres estoura `evento_auditoria.escopo`, que também é `varchar(255)` (migração, linha 19), e o cadastro passa a dar 500 no PostgreSQL, a mesma classe de defeito que o CA12 corrige; hoje essa requisição grava; (b) o responsável escolhe em qual território o evento aparece, informando a igreja de qualquer setor. — Validar o campo no cadastro com `exists:igrejas,id`, como `CorrigirParticipanteRequest.php:23` já faz, ou definir que `daIgreja` devolve `null` para igreja inexistente. Em qualquer das saídas, incluir caso de teste com id inexistente e com id de 255 caracteres, e conferir os testes atuais que cadastram com id fictício.

- `02-especificacao.md`, seção 1 ("Todo motivo que vai para a auditoria passa a ter limite compatível com isso") e parágrafo "Fica de fora"; `01-historia.md`, CA12 — O inventário está incompleto. `TransferenciaRequest.php:22` aceita `reason` com `max:500`, `ChurchController.php:106` repassa o valor e `ServicoTransferencia.php:100` grava em `evento_auditoria.motivo`. Um motivo de transferência de 256 a 500 caracteres continua derrubando a gravação. Os demais candidatos foram conferidos e não gravam texto livre na auditoria (troca de escala, cancelamento de troca e encerramento de vaga usam constantes ou não enviam `motivo`). — Incluir a transferência na tabela da seção 1, em T1, no OpenAPI e no CT-30 (256 → 422), ou declarar a exclusão em "Fora do escopo" e reescrever a frase da seção 1 e o CA12, com registro da dívida em T17.

### Recomendado

- `02-especificacao.md`, "Decisões", linhas `daIgreja` e "Motivos: limite de 255" — As duas terminam em "**Confirmar.**" sem resposta registrada. A segunda quebra contrato publicado (500 → 255). A justificativa da primeira está errada: a coordenadora não tem `auditoria.ler` (`AutorizadorOperacao`: só `super_admin`, `adm_regional` e `adm_setor`), então "não veria o evento do próprio território" não é argumento; para quem lê a auditoria hoje, `ebi:` e `igreja:` dão o mesmo resultado. — Registrar a decisão humana antes da implementação e trocar a justificativa (por exemplo, coerência com os eventos de presença, que já usam `ebi:`).

- `04-plano-testes.md`, CT-19 — `GET /audit-events?acao=...` usa um filtro que não existe: `AuditEventController` e o OpenAPI (linhas 2333-2350) só aceitam `recurso`, `ator_id`, `de` e `ate`. O parâmetro é ignorado e "vê só o evento da A" falha se houver qualquer outro evento com escopo no setor; quem implementa pode acabar criando o filtro. — Usar `recurso=participante` e filtrar por `acao` na asserção, dizendo isso no caso.

- `03-plano-implementacao.md`, T15 ("os testes que hoje cobrem a contingência individual passam a esperar o `POST .../batch`") — `frontend/src/pages/Presenca.test.tsx` não tem nenhum teste de contingência (os dez casos tratam de finalidade e presença da equipe). CT-32 e CT-33 partem do zero e o plano não diz como montar a sessão `encerrada`, a busca de candidatos e o mock de `api.POST`. — Remover a frase e descrever a montagem (qual `api.GET` devolve a sessão, qual devolve os candidatos, formato da resposta do lote).

- `01-historia.md`, CA12 (última frase); `03-plano-implementacao.md`, T2; `04-plano-testes.md` — O `maxLength` e o rótulo do portal não têm caso de teste; o "pronto quando" de T2 é um `grep`, que não cobre `PainelAgenda` nem roda no ambiente Windows do repositório. — Acrescentar um caso vitest por campo (ou ao menos para `PainelAgenda`, que muda de sem limite para 247) ou tirar a frase do CA12.

- `02-especificacao.md`, seção 1, item `PainelAgenda.tsx:259` — O servidor passa a exigir `min:3`, mas o botão "Criar exceção" continua habilitado com 1 ou 2 caracteres (`!motivo.trim()`, linha 260). O usuário recebe um 422 que hoje não recebe. — Incluir `minLength={3}` e a mesma condição no `disabled`, com o rótulo "3 a 247 caracteres".

- `04-plano-testes.md`, CT-3, CT-11 e CT-13 — `coordenadora_chefe` aparece no CA4 e na condição do card, mas em nenhum caso; `adm_regional`, citado no CA5, não está no CT-13. — Acrescentar os dois papéis aos casos existentes.

- `04-plano-testes.md`, CT-18 — Falta o caso `null` de `vinculo.solicitado` e `excecao.solicitada` para participante sem igreja, que o CA8 exige ("só fica `null` nos casos que a tabela prevê"). — Acrescentar ao CT-18.

- `04-plano-testes.md`, CT-35 — O CA2 lista três respostas (`409 participante_mesclado`, `422 nada_a_corrigir`, `422 igreja_fora_do_escopo`) e o caso cobre uma. — Parametrizar o caso com as três ou reduzir o CA2 a "qualquer erro da API".

- `02-especificacao.md`, seção 5, passo 2 — `ConsultarParticipante::encontrar` exclui participante `mesclado`, então ele volta como `not_found`, e a tela diz "Não lançadas: ‹nome›" sem explicar. O comportamento não está escrito nem testado. — Documentar na especificação e no schema `ResultadoContingenciaLote`, e incluir uma linha mesclada no CT-25.

- `02-especificacao.md`, seção 3, linha `participante.cadastrado` ("ou sem contexto") — Ramo inalcançável: `POST /participants` tem `can:participante.cadastrar` (`routes/api.php:174-175`) e `ParticipantePolicy::decidir` nega sem `ContextoAtuacao`. — Remover o ramo da tabela.

### Opcional

- `02-especificacao.md`, seção 4, passo 4 de `alertas` — Se `EbiDados.timezone` vier vazio, `new DateTimeZone('')` lança exceção e a rota dá 500. Usar o mesmo padrão `America/Sao_Paulo` adotado em `meusDependentes`.

- `01-historia.md`, premissa da coordenadora chefe com outro tipo de escopo — Sem caso de teste; cabe como mais um render no CT-3.

- `02-especificacao.md`, "Segurança e autorização", item Lote — As linhas `criada` e `ja_existia` devolvem a presença de qualquer participante do país cujo id seja informado, como a rota individual já faz. Vale uma frase reconhecendo isso.

- `03-plano-implementacao.md`, T2 — `npm run gen:api` é redundante ali: nenhum tipo gerado é usado pela tarefa.

- `03-plano-implementacao.md`, T12 — Termina sem teste próprio (só `phpstan`); pode ser fundida com T13.

- `03-plano-implementacao.md`, conjunto — T1-T2 (limite de motivo, correção de defeito) e T3-T4 (desistir da troca) não dependem do resto e podem ser entregues antes, reduzindo o tamanho da demanda de 17 para 13 tarefas.

## Rodada 2 — Veredito: APROVADO COM RESSALVAS

### Bloqueante

Nenhum. Os três bloqueantes da rodada 1 foram resolvidos e conferidos no código:

- Território na decisão de exceção — resolvido (`02-especificacao.md`, seção 3a; CA13; T7; CT-37 a CT-39). `AutorizacaoParticipante::ligado` (linhas 114-137) se comporta como a seção descreve, `Operacoes::EXCECAO_DECIDIR` e `GerirParticipante::encontrar` existem, e só `ExcecaoEtariaTest.php` faz `PATCH /age-exceptions/{id}`.
- `igreja_referencia_id` sem validação — resolvido (`exists:igrejas,id` nos dois Requests, `daIgreja` com `null` para igreja inexistente, CT-40 e CT-41). O único teste que cadastra por HTTP com id fictício é `CadastroParticipanteTest.php:30`, já tratado em T6.
- Transferência fora do inventário — resolvido (seção 1, CA12, T1, CT-30; `openapi.yaml:673` confere).

### Recomendado

- `01-historia.md`, CA6; `02-especificacao.md`, seção 5 (barra fixa); `03-plano-implementacao.md`, T16 passo 5; `04-plano-testes.md`, CT-32 — O rótulo do botão aparece de duas formas: "Lançar N presenças" (CA6, CT-32) e "Lançar ‹n› presença(s)" (especificação, T16). O teste procura o botão pelo nome e falha com uma delas. — Fixar um texto só nos quatro lugares.

- `02-especificacao.md`, seção 4, passo 5 de `alertas`; `03-plano-implementacao.md`, T11 ("injete `ConsultarEbi` e `AvaliarElegibilidade`") — `AgeExceptionController` não tem `RegistrarEvento` (o construtor atual só recebe `GerirExcecaoEtaria`, `GerirParticipante` e `AutorizacaoParticipante`), e o passo 5 lista apenas `escopo`, `motivo` e `metadados_minimizados`. — Incluir `App\Domain\Auditoria\Contracts\RegistrarEvento` na lista de injeções do T11 e dizer que os demais campos seguem `ParticipantController::auditarLeitura` (linhas 184-193: `recurso`, `ator_id`, `origem`, `ocorrido_em`).

- `02-especificacao.md`, seção 3, linhas `participante.cadastrado` (origem `assistido` e "Outro papel") — `ParticipantController::store` e `storeAssisted` não conferem se a igreja informada está no escopo de quem cadastra (ao contrário de `update`, linhas 151-154). A coordenadora de `ebi:A` cadastra uma criança na igreja B e o evento fica em `ebi:A`; a trilha do território B não mostra o cadastro. Não há ganho de acesso, mas é o mesmo desvio de trilha que motivou a seção 3a. Além disso, o ramo "Outro papel" de `POST /participants` não tem caso de teste (CT-15 e CT-16 só usam o responsável). — Decidir e registrar: usar `daIgreja` quando o corpo traz igreja, negar com `cobreIgreja` como a correção faz, ou aceitar o comportamento em "Segurança e autorização". Acrescentar um caso para o ramo "Outro papel" ou dizer que nenhum outro papel chega à rota.

- `04-plano-testes.md`, CT-17 — O cadastro assistido dentro de `EscopoAuditoriaParticipanteTest.php` depende da montagem do `beforeEach` de `CadastroAssistidoTest.php` (linhas 12-26: `Storage::fake('quarentena')`, `Storage::fake('laudos')`, mock de `ClienteAntivirus` e `TermoVersao` vigente de cadastro). O plano não cita isso e o teste falha sem ela. — Acrescentar a referência em "Dados e ambiente".

### Opcional

- `04-plano-testes.md`, tabela "Arquivos de teste" — `SessaoTest.php` continua fora da tabela, embora esteja no CT-30 e no T1.

- `04-plano-testes.md`, CT-43 ("montagem do T16") — O T2 vem antes do T16. O caso só precisa de `responder('encerrada', escala())`, que já existe em `Presenca.test.tsx:46`; vale dizer isso para manter T1-T4 independentes.

- `04-plano-testes.md`, CT-14 — Não diz que a chamada leva `finalidade`; sem ela, o passo 2 de `alertas` responde 422 antes do 404.

- `04-plano-testes.md`, CT-13 — `igrejaComEbi()` não cria regional. Como a negação do `adm_regional` vem do papel, qualquer regional serve; o texto "da regional que contém E" obriga a montar a árvore sem necessidade.

- `02-especificacao.md`, "Decisões", linha `daIgreja` ("a trilha do participante fica com um formato só") — Não é verdade: `presenca.lida` grava `igreja:<id>` mesmo quando a igreja tem EBI (`HistoricoPresencaController.php:33`). Trocar a frase ou registrar a divergência como dívida.

- `02-especificacao.md`, "Segurança e autorização", item Cadastro ("O escopo da auditoria deixa de ser escolhido pelo cliente") — O responsável continua escolhendo entre as igrejas existentes; a frase correta é que ele deixa de poder informar uma igreja inexistente.

- `02-especificacao.md`, seção 5 (Front) — O texto do modo contingência em `Presenca.tsx:280` ("Informe o motivo de cada presença.") fica errado com o lote, que usa um motivo para todas.

## Aprovação humana

**Aprovado** pelo humano em 27/11/2026, após a rodada 2 (APROVADO COM RESSALVAS).

Decisão sobre as ressalvas da rodada 2:
- **Recomendado 3 (igreja fora do escopo no cadastro):** **negar.** `POST /participants` (papel de operador) e `POST /participants/assisted` conferem a igreja informada com `AutorizacaoParticipante::cobreIgreja`, como a correção (`ParticipantController::update`) já faz, e respondem `422 igreja_fora_do_escopo`. O escopo do evento passa a vir da igreja (`daIgreja`). Incluir caso de teste para o ramo "outro papel" e para a igreja fora do escopo.
- **Recomendados 1, 2 e 4:** resolvidos pelo implementador: rótulo único "Lançar N presenças"; `RegistrarEvento` injetado no `AgeExceptionController` (T11); CT-17 referencia a montagem de `CadastroAssistidoTest.php`.
- **Opcionais:** a critério do implementador.
