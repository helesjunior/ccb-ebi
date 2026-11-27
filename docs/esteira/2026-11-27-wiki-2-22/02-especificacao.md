# Especificação técnica

## Solução

A demanda muda o texto de páginas em `docs/wiki/` e acrescenta capturas em `docs/wiki/images/`. Não há mudança de código. Cada assunto tem o conteúdo completo numa única página, e as outras levam uma frase e um link; a tabela "Onde fica cada conteúdo" de `01-historia.md` diz qual é qual. Botões, rótulos e mensagens são copiados da tela, e a tabela abaixo dá a fonte de cada texto.

O resultado da conferência final (checklist do wiki e pendências) vai para um arquivo novo, `docs/esteira/2026-11-27-wiki-2-22/06-entrega.md`. Quem implementa não escreve a descrição do PR; o arquivo serve de base para ela.

### Fonte dos textos (conferida no código)

| Mudança | Texto exato na tela | Fonte |
|---|---|---|
| Corrigir cadastro | Botão **Corrigir** na linha da busca. O diálogo **Corrigir cadastro** diz "Altere só o que estiver errado e informe o motivo." Campos: **Nome completo**, **Nome social (opcional)**, **Data de nascimento**, **Igreja de referência (opcional)**, **Motivo da correção (3 a 255 caracteres)**. Botões **Voltar** e **Salvar correção**; o segundo fica desabilitado enquanto nada mudou ou o motivo não tem de 3 a 255 caracteres. Sucesso: "Cadastro corrigido." | `frontend/src/components/CorrigirParticipante.tsx:56,92,101-129`; `frontend/src/pages/Participantes.tsx:54,186-192` |
| Campo de igreja | Texto livre que recebe o **identificador** da igreja, não o nome. Não há seletor (CHANGELOG 2.22, "Fora desta versão"), e nenhuma tela mostra o identificador. O wiki diz o que o campo recebe e manda deixar como está se não souber. | `CorrigirParticipante.tsx:119`; `frontend/src/components/FormularioParticipante.tsx:112-116` |
| Identificador que não existe | O validador recusa (`exists:igrejas,id`) com uma mensagem em inglês, porque o projeto não tem tradução (`backend/lang` não existe). O wiki não cita o texto; diz "aparece um aviso de dado inválido, em inglês; confira o identificador". Vale para o cadastro e para a correção. | `backend/app/Http/Requests/Participantes/CadastrarParticipanteRequest.php:23`; `.../CorrigirParticipanteRequest.php:23`; `backend/bootstrap/app.php:58` |
| Quem corrige | Quem atua como `coordenadora`, `coordenadora_chefe` ou `super_admin`. A coordenação só corrige cadastro ligado ao seu EBI. | `Participantes.tsx:54`; `backend/app/Http/Controllers/Api/V1/ParticipantController.php:144` |
| Recusas da correção | 1) "O participante não está ligado a um EBI onde você atua." 2) "Cadastro mesclado não pode ser corrigido; desfaça a mesclagem." 3) "Nenhum campo difere do cadastro atual." 4) "A nova igreja de referência não está no seu escopo." Todas aparecem dentro do diálogo, que continua aberto. | `backend/app/Support/Participantes/AutorizacaoParticipante.php:158`; `backend/app/Domain/Participantes/Services/GerenciadorParticipante.php:219`; `ParticipantController.php:155,160`; `CorrigirParticipante.tsx:87-89` |
| Igreja no cadastro (tela **Participantes**) | A igreja precisa existir. Quem cadastra por essa tela (todos os papéis que a veem; o responsável não a vê) só pode usar igreja do próprio escopo. Fora dele aparece "A igreja de referência não está no seu escopo.". A tela Cadastro assistido não tem campo de igreja e fica de fora. | `CadastrarParticipanteRequest.php:23`; `ParticipantController.php:180-199`; `frontend/src/components/layout/ListaNav.tsx:69-78`; `frontend/src/pages/CadastroAssistido.tsx:55-60` |
| Aviso ao responsável | "‹nome› faz 12 anos em ‹dd/mm/aaaa›. Sem exceção inclusiva aprovada, a presença fica bloqueada a partir dessa data. Se for o caso, peça a exceção à coordenação do EBI." | `frontend/src/pages/Dependentes.tsx:73-79` (o texto ocupa três linhas do JSX) |
| Bloco no Início | Título "Fazem 12 anos nos próximos 30 dias". Cada linha tem nome, matrícula e data, e há o botão **Exceção inclusiva**, que abre a tela de mesmo nome. Lista vazia: "Ninguém faz 12 anos nos próximos 30 dias." Erro: "Não foi possível carregar os aniversários." O bloco carrega junto com o Início, sem clique, e só para quem atua como coordenadora ou coordenadora chefe no contexto de EBI. | `frontend/src/pages/Inicio.tsx:27-44,47-87,148-151` |
| O que o bloco lista | Participantes **ativos** cuja **igreja de referência** é a igreja do EBI, que fazem 12 anos de hoje até 30 dias à frente e não têm exceção aprovada vigente na data. Criança sem igreja de referência não aparece em lugar nenhum. Criança de outra igreja aparece para a coordenação do EBI da igreja dela; a coordenação daqui não consegue corrigir esse cadastro (recusa 1 acima). Uma exceção só pedida não tira a criança da lista. Cada carga registra a consulta na auditoria. | `backend/app/Domain/Participantes/Services/AvaliadorElegibilidade.php:94-103`; `AutorizacaoParticipante.php:124-128`; `docs/esteira/2026-11-26-dividas-acumuladas/01-historia.md` CA4 |
| Contingência em lote | Em **Sessões**, botão **Presença** na linha de sessão aberta ou encerrada. Na tela de presença: **Lançar lista em papel** (só sessão encerrada); aviso "Modo contingência: lançando lista em papel de uma sessão encerrada. Marque todos da lista e informe o motivo uma vez."; campo **Motivo da contingência (3 a 255 caracteres)**; os marcados viram etiquetas com **×**; rodapé com **Limpar seleção** e **Lançar ‹N› presenças**. Resultado: "‹n› lançadas, ‹m› já existiam". As falhas aparecem como "Não lançadas: ‹nomes›". Inelegível aparece como "Bloqueado: ‹motivo›" e não pode ser marcado. **Sair da contingência** volta ao modo normal. Limite de 100 por lançamento (a tela não limita). | `frontend/src/pages/Sessoes.tsx:269-271`; `frontend/src/pages/Presenca.tsx:62-66,167,175,302-323,437-442`; `backend/app/Http/Requests/Agenda/ContingenciaLotePresencaRequest.php:18` |
| Quem lança | `adm_igreja`, `coordenadora`, `coordenadora_chefe`. | `Presenca.tsx:62` |
| Exceção de calendário | **Motivo (obrigatório, 3 a 247 caracteres)** | `frontend/src/components/agenda/PainelAgenda.tsx:259` |
| Desistir da troca | Com a troca pedida, a situação da linha é **Troca solicitada**, e aparecem o texto "Troca pedida; aguardando aprovação." e o botão **Desistir da troca**. Confirmação: "Desistir da troca?" / "Você continua escalada nesta sessão.". Sucesso: "Pedido de troca cancelado.". Se a troca já tiver sido decidida: "Esta troca já foi decidida.", e a lista recarrega. A linha volta para **Escalado** ou **Confirmado**. | `frontend/src/lib/formatos.ts:130`; `frontend/src/pages/MinhasEscalas.tsx:109,297-309`; `backend/app/Domain/Escalas/Services/GerenciadorTrocaEscala.php:99,135` |
| Território na exceção | Coordenação de outro EBI recebe "Exceção não encontrada." e nada muda. O Super Admin decide qualquer exceção. | `backend/app/Http/Controllers/Api/V1/AgeExceptionController.php:75` |
| Pedido de exceção pela tela | O cartão continua com o título **Solicitar (responsável)**, mas o responsável não vê a tela. Quem registra o pedido é a coordenação. | `frontend/src/pages/ExcecaoInclusiva.tsx:120`; `ListaNav.tsx:69-78,183` |
| Vínculo do responsável | A aba **Solicitar** de Vínculos não aparece para o responsável. O comentário do código diz que o caminho dele é a coordenação. O wiki manda pedir o vínculo à coordenação do EBI e não promete anexo pelo portal. | `frontend/src/pages/Vinculos.tsx:94-101,209` |
| Auditoria por território | A maioria dos eventos grava o território **de quem agiu** (contexto de atuação): leitura, mesclagem e desfazer, decisão de vínculo e de exceção, correção, e cadastro sem igreja feito pela equipe. Pedido de vínculo, pedido de exceção, cadastro feito pelo responsável e cadastro com igreja gravam o território **da igreja da criança**; se a criança não tem igreja de referência, esses ficam sem território. Evento feito pelo Super Admin fica no contexto central. Eventos anteriores à 2.22 não têm território. Admin de setor e Admin regional veem só os eventos com território dentro do seu. | `backend/app/Support/Participantes/EscopoAuditoria.php:22-42`; `ParticipantController.php:180-199,223`; `backend/app/Http/Controllers/Api/V1/GuardianController.php:57` (pedido de vínculo), `:102` (listagem de pedidos); `backend/app/Http/Controllers/Api/V1/GuardianRequestController.php:51` (decisão de vínculo); `AgeExceptionController.php:59,85`; `backend/app/Http/Controllers/Api/V1/ParticipantMergeController.php:42,63` |

### Páginas afetadas

| Página | O que muda |
|---|---|
| `docs/wiki/Participantes.md` | Seção nova "Corrigir um cadastro". Frase sobre a igreja em "Cadastrar", sem atribuir o cadastro ao responsável. "Vínculo com o responsável" passa a dizer que o pedido é feito à coordenação. Tabela "Quem faz o quê": entra "Corrigir cadastro", e "Cadastrar" e "Pedir exceção inclusiva" perdem "responsável". |
| `docs/wiki/Sessoes-e-agenda.md` | O parágrafo **Contingência** vira uma subseção com passos e captura. Limite do motivo em "Exceções de calendário". Linha nova em "Quem mexe em quê". |
| `docs/wiki/Excecao-inclusiva.md` | Seção "Aviso dos 12 anos". Nota no cartão **Solicitar (responsável)**, sem a linha "no lugar do responsável". Território em "Decidir a exceção". |
| `docs/wiki/Fluxo-do-responsavel.md` | §1 reescrita (vínculo pela coordenação), aviso na §2, §4 reescrita, dúvidas "Meu filho não aparece…" e "Posso cadastrar…" reescritas e dúvida nova sobre os 12 anos. |
| `docs/wiki/Fluxo-da-coordenacao.md` | Frases com link: bloco do Início (no parágrafo de abertura), contingência (§5) e Corrigir (§7). |
| `docs/wiki/Fluxo-do-auxiliar.md` | Linha **Troca solicitada** na tabela da §1 e passo a passo de **Desistir da troca** na §4. |
| `docs/wiki/Escalas.md` | Frase com link em "Trocas". |
| `docs/wiki/Fluxo-da-administracao-superior.md` | §3 Auditoria: o que aparece para cada nível. |
| `docs/wiki/Mapa-das-telas.md` | Tabela (Participantes, Minhas escalas), "### Início", seção nova "### Meus dependentes", "### Participantes", "### Minhas escalas" e "### Exceção inclusiva". |
| `docs/wiki/images/` | Cinco recapturas e uma imagem nova. |
| `docs/esteira/2026-11-27-wiki-2-22/06-entrega.md` | Novo: checklist do wiki marcado e pendências. |

## Contratos

Não há API, esquema ou migração. O único contrato é o formato do arquivo `06-entrega.md`:

```markdown
# Entrega

## Checklist do wiki (Manutencao-do-wiki.md)
- [x] Funcionalidade nova ou fluxo alterado?
- [x] Página do wiki criada ou atualizada em `docs/wiki/`
- [x] Captura nova, se a tela mudou
- [x] Página indexada em `Home.md` e no Mapa das telas — Home não muda (nenhuma tela nova)
- [x] Links conferidos (CT14 e CT15)

## Capturas
| Arquivo | Situação |
|---|---|
| 28-contingencia-lote.png | feita |
| 12-minhas-escalas-auxiliar.png | pendente: <motivo> |

## Pendências
- Produto: o responsável não tem tela para ligar a própria conta a uma criança (ver 02-especificacao.md, Riscos).
```

## Decisões

| Decisão | Alternativa descartada | Motivo |
|---|---|---|
| Atualizar as páginas existentes | Página "Novidades da 2.22" | O wiki é organizado por tela e fluxo, e o histórico já está no `CHANGELOG.md`. |
| Conteúdo completo numa página e frase com link nas outras (tabela em `01-historia.md`) | Repetir o passo a passo em todas | Texto que existe em um lugar só não diverge. |
| **Desistir da troca** completo em `Fluxo-do-auxiliar.md` | Em `Escalas.md` | O passo a passo de "Pedir troca", que é o lado de quem pede, já está em `Fluxo-do-auxiliar.md` §4. `Escalas.md` trata do lado da coordenação. |
| Sem rotas, códigos HTTP nem `escopo` | Documentar a API | `Manutencao-do-wiki.md`: "Não descreva implementação". |
| Vínculo, exceção e cadastro do responsável: "peça à coordenação do EBI", sem citar tela (decisão do humano) | Mandar o responsável ao Cadastro assistido ou a Vínculos → Solicitar | O cadastro assistido grava o vínculo sem conta (`GerenciadorParticipante.php:165`), e a aba Solicitar não aparece para ele. Qualquer tela citada levaria a um beco. |
| Não explicar onde achar o ID do participante nem o identificador da igreja | Inventar um caminho | Nenhuma tela mostra esses valores. O limite fica em "Fora do escopo". |
| Capturas feitas por quem implementa, com o ambiente local (decisão do humano) | Deixar para uma pessoa depois | O humano pediu para tentar agora. Se falhar, a pendência vai para `06-entrega.md`. |
| Vincular o responsável de teste com `tinker`, só no ambiente local | Criar o vínculo pela tela | Não existe caminho pela tela (ver Riscos). O comando altera só o dado de demonstração do banco local. |
| Mensagem citada inteira numa linha do Markdown | Quebrar perto de 100 colunas, como o resto da página | O `Grep` procura linha a linha. Mensagem quebrada dá falso negativo nos casos de teste. |
| Pendências e checklist em `06-entrega.md` | Na descrição do PR | Quem implementa não escreve a descrição do PR e não tem como conferi-la. |

## Segurança e autorização

A demanda não muda autorização, porque é só documentação e captura. Cuidados:
- Capturas só com **dado de demonstração** dos seeders (`Manutencao-do-wiki.md`). O bloco dos 12 anos mostra nome, matrícula e data.
- O segredo TOTP usado no roteiro é o de demonstração (`backend/database/seeders/MfaDemoSeeder.php:25`). Ele não pode aparecer em captura nem no wiki.
- O comando `tinker` da T8 roda só no banco local. Não pode ir para documentação de usuário.
- O texto não pode prometer mais acesso do que o sistema dá. Por isso a auditoria diz o que **não** aparece para setor e regional, e a §1 do responsável não promete que o vínculo aprovado aparece em Meus dependentes.

## Riscos e mitigação

| Risco | Mitigação |
|---|---|
| **Furo de produto:** nenhuma tela liga a conta do responsável a uma criança. A aba Solicitar não aparece para ele (`Vinculos.tsx:94-101`). O pedido aberto pela coordenação grava a conta de quem pediu (`GerenciadorVinculoResponsavel.php:25-33`), e o cadastro assistido grava o vínculo sem conta (`GerenciadorParticipante.php:165`). Consequência: Meus dependentes (`VerificadorPropriedadeParticipante.php:25`) e o aviso dos 12 anos só aparecem para responsável vinculado por fora da tela, como a T8 faz com `tinker`. | O wiki manda pedir à coordenação e não promete o resultado. O furo vai como pendência de produto em `06-entrega.md`, para virar demanda própria. |
| Texto do wiki diferente do texto da tela | Copiar da tabela acima. CT16 busca no código um trecho fixo de cada texto, já sem as partes variáveis. |
| Ambiente não sobe ou o navegador não é controlável | A T8 e a T9 registram a pendência em `06-entrega.md`, e a página não recebe referência à imagem que falta. |
| Link ou âncora quebrados | CT14 e CT15. |

## Respostas à revisão

**Rodada 1:** todos os bloqueantes e recomendados foram aceitos. Opcionais: cortar `npm run lint && npm test` (aceito), limite de 100 no lote (aceito), **Corrigir** em `Fluxo-da-coordenacao.md` (aceito, citado no CA1), "Atende" da T7 (aceito: a tarefa de captura é apoio visual e não fecha critério).

**Rodada 2:** o bloqueante e todos os recomendados e opcionais foram aceitos.

| Item | Resposta |
|---|---|
| Bloqueante: T3.6 manda ao Cadastro assistido | Aceito, por decisão do humano. A resposta passa a ser "peça à coordenação do EBI", com link para a §5, sem tela (CA5, T3, CT24 e CT45). |
| Seeder de usuários e TOTP | Aceito. A T8 roda `UsuariosTesteSeeder`, gera o código com `Totp::codigoAtual` e avisa que o `tinker` é só local. |
| Faixa antes de gerar sessões | Aceito (T8, passo 6). |
| `Lançar N presenças` × `‹N›` | Aceito: a forma `Lançar ‹N› presenças` é usada em todos os lugares. |
| Mensagem quebrada no meio | Aceito: regra geral no plano. |
| CT30 | Aceito: a busca agora é `registra a consulta`. |
| "Corrija a igreja dela" | Aceito: vale só para a criança sem igreja; a de outra igreja aparece para a coordenação de lá. |
| ID do participante e identificador da igreja | Aceito, pela segunda opção da revisão: os dois estão em "Fora do escopo" como limite conhecido da tela. |
| Identificador que não existe | Aceito, com o texto "aviso de dado inválido, em inglês" (CA1, CA2, CT46). |
| `Excecao-inclusiva.md:24` e `Participantes.md:33-34,88` | Aceito (T1 e T3). A linha 89 (Cadastro assistido, com o responsável) fica, porque o menu `CADASTRO` inclui o responsável. |
| Descrição do PR | Aceito: tudo vai para `06-entrega.md`. |
| Consequência da §1 | Aceito. Por decisão do humano, a §1 virou CA11 e T4, e a consequência está em Riscos. |
| Opcionais (`} presenças`, `ID do participante` vazio, `git status`, fonte da decisão de vínculo, seção "0") | Todos aceitos. A frase do Início entra no parágrafo de abertura de `Fluxo-da-coordenacao.md`. |
