# Plano de implementação

Total: 5P, 5M

Regras que valem para todas as tarefas:
- Português direto, na voz do usuário ("toque em **Corrigir**").
- Nomes e mensagens copiados de `02-especificacao.md`, tabela "Fonte dos textos". Nas mensagens com parte variável, a parte variável vai entre ‹ ›: "‹n› lançadas, ‹m› já existiam", **Lançar ‹N› presenças**.
- **Mensagem ou rótulo citado fica inteiro numa linha do Markdown.** As páginas quebram linha perto de 100 colunas, mas nunca no meio de uma mensagem entre aspas, de um rótulo em negrito ou de um link. A busca do teste é feita linha a linha.
- Link no formato `[Texto](Nome-da-pagina)`, sem `.md`. Link para seção da mesma página: `[texto](#ancora)`, com a âncora no padrão do GitHub (minúsculas, espaços viram `-`, pontuação sai, acentos ficam).
- Sem rotas e sem códigos HTTP.
- O conteúdo completo de cada assunto fica só na página indicada em `01-historia.md`, tabela "Onde fica cada conteúdo". As demais páginas levam uma frase e um link.
- "Busca X" é a ferramenta `Grep` com o padrão X como **texto literal** (`literal: true`), uma busca por item. "Encontra" quer dizer ao menos uma ocorrência; "vazio", nenhuma.

## T1 — Participantes: corrigir cadastro, igreja do cadastro e responsável
- Arquivos: `docs/wiki/Participantes.md`
- O que fazer:
  1. Em "Cadastrar", trocar a frase "O cadastro nasce ligado a quem cadastrou (a coordenação ou o próprio responsável)." por "O cadastro nasce ligado a quem cadastrou." Depois dela, acrescentar:
     - "A **igreja de referência** precisa existir no cadastro de igrejas e estar no seu escopo. Fora dele, aparece \"A igreja de referência não está no seu escopo.\" e nada é gravado.";
     - "O campo recebe o **identificador** da igreja, não o nome. Se não souber qual é, deixe em branco. Se o identificador não existir, aparece um aviso de dado inválido, em inglês; confira o identificador."
     Não mexer em "Cadastro assistido", que não tem campo de igreja.
  2. Criar a seção `## Corrigir um cadastro` entre "Mesclagem de duplicados" e "Vínculo com o responsável", com:
     - uma frase dizendo para que serve: corrigir dado errado (nome, nome social, nascimento, igreja) sem criar outra ficha;
     - os passos: 1) busque o participante; 2) na linha, toque em **Corrigir**; 3) altere só o que estiver errado nos campos **Nome completo**, **Nome social (opcional)**, **Data de nascimento** e **Igreja de referência (opcional)** (este recebe o identificador, como no cadastro); 4) informe o **Motivo da correção**, de 3 a 255 caracteres; 5) toque em **Salvar correção**. Ao final, a linha mostra o dado novo e aparece "Cadastro corrigido.";
     - a observação de que o botão fica desabilitado enquanto nada mudou ou o motivo está fora do limite;
     - a subseção `### Se aparecer uma mensagem`, em lista, cada recusa com o que fazer. O diálogo continua aberto em todos os casos:
       - "O participante não está ligado a um EBI onde você atua." → a criança é de outro EBI; peça à coordenação de lá;
       - "Cadastro mesclado não pode ser corrigido; desfaça a mesclagem." → desfaça a mesclagem ou corrija o cadastro principal;
       - "Nenhum campo difere do cadastro atual." → altere algum campo;
       - "A nova igreja de referência não está no seu escopo." → use uma igreja do seu escopo;
       - aviso de dado inválido, em inglês → o identificador da igreja não existe; confira;
     - quem pode: coordenação, só no cadastro ligado ao seu EBI, e Super Admin. Os outros perfis não veem o botão;
     - a correção fica na auditoria com o motivo e o estado anterior.
  3. Em "Vínculo com o responsável", trocar "O fluxo é: o responsável pede → a coordenação aprova (ou pede documento, recusa e, depois, pode revogar)." por "O fluxo é: o responsável pede à coordenação do EBI → a coordenação registra e decide (aprova, pede documento ou recusa, e depois pode revogar)."
  4. Em "Quem faz o quê":
     - trocar `| Cadastrar | Administradores, coordenação, auxiliar, responsável |` por `| Cadastrar | Administradores, coordenação, auxiliar |`;
     - acrescentar `| Corrigir cadastro | Coordenação, Super Admin |`;
     - trocar `| Pedir exceção inclusiva | Coordenação, responsável |` por `| Pedir exceção inclusiva | Coordenação (o responsável pede à coordenação) |`;
     - manter `| Cadastro assistido | … responsável |`, porque essa tela está no menu do responsável (`ListaNav.tsx:79`).
- Atende: CA1, CA2, CA5 (coerência), CA11
- Pronto quando: CT1 a CT6, CT8, CT44, CT46, CT47, CT48 e CT55 passam.
- Estimativa: M

## T2 — Sessões e agenda: contingência em lote e motivo da exceção
- Arquivos: `docs/wiki/Sessoes-e-agenda.md`
- O que fazer:
  1. Trocar o parágrafo que começa com `**Contingência.**` pela subseção `### Lançar a lista em papel (contingência)`, com:
     - quando usar: a presença ficou numa lista em papel. Só vale para sessão **Encerrada**;
     - os passos: 1) em **Sessões**, toque em **Presença** na linha da sessão encerrada; 2) toque em **Lançar lista em papel**; 3) informe o **Motivo da contingência**, de 3 a 255 caracteres, uma vez só para a lista inteira; 4) busque e marque cada criança, mesmo que venham de buscas diferentes (os marcados aparecem como etiquetas, e o **×** remove); 5) toque em **Lançar ‹N› presenças**, onde ‹N› é o número de marcados;
     - o resultado: "‹n› lançadas, ‹m› já existiam". Quem já tinha presença não é duplicado. Quem não pôde ser lançado aparece em "Não lançadas: ‹nomes›", e os demais são gravados. Quem aparece como **Bloqueado** não pode ser marcado;
     - o limite: no máximo 100 crianças por lançamento. Lista maior vai em mais de um lançamento;
     - **Limpar seleção** desmarca todos, e **Sair da contingência** volta ao modo normal;
     - a presença lançada aparece com a marca **contingência** e fica na auditoria com o motivo.
  2. Em "Exceções de calendário", acrescentar: "O **motivo** é obrigatório, de 3 a 247 caracteres."
  3. Em "Quem mexe em quê", acrescentar `| Lançar lista em papel (contingência) | Coordenação, Admin da igreja |`.
- Atende: CA3, CA4
- Pronto quando: CT9 a CT13 e CT17, CT18 e CT20 passam.
- Estimativa: M

## T3 — Aviso dos 12 anos, pedido e território da exceção
- Arquivos: `docs/wiki/Excecao-inclusiva.md`, `docs/wiki/Fluxo-do-responsavel.md`
- O que fazer:
  1. Em `Excecao-inclusiva.md`, criar `## Aviso dos 12 anos` antes de "Pedir a exceção", com:
     - a regra: aos 12 anos, sem exceção aprovada vigente, a presença fica bloqueada, e o sistema avisa 30 dias antes;
     - **Coordenação** (coordenadora ou coordenadora chefe atuando no EBI): ao abrir o **Início**, aparece o bloco "Fazem 12 anos nos próximos 30 dias", com nome, matrícula, data e o botão **Exceção inclusiva**, que abre esta tela;
     - o que entra na lista: participantes ativos cuja **igreja de referência** é a igreja do EBI, sem exceção aprovada vigente na data. Uma exceção apenas pedida não tira a criança da lista;
     - quem fica de fora: a criança **sem igreja de referência** não aparece em nenhum Início; se ela frequenta o seu EBI, preencha a igreja dela ([Participantes](Participantes)). A criança de outra igreja aparece para a coordenação de lá;
     - lista vazia: "Ninguém faz 12 anos nos próximos 30 dias.";
     - falha: "Não foi possível carregar os aniversários.", e o resto do Início continua funcionando;
     - a frase: "Abrir o Início registra a consulta na auditoria.";
     - o aviso aparece só na tela, sem e-mail;
     - **Responsável:** uma frase dizendo que ele vê o aviso em **Meus dependentes**, com link para [Fluxo do responsável](Fluxo-do-responsavel).
  2. Em "Pedir a exceção":
     - manter o nome do cartão **Solicitar (responsável)** e acrescentar, logo abaixo da linha do menu: "Apesar do nome, o responsável não tem esta tela no menu: ele pede à coordenação do EBI, que registra o pedido aqui.";
     - apagar a linha "A coordenação também pode pedir a exceção no lugar do responsável.".
  3. Em "Decidir a exceção", acrescentar: "Você só decide exceções de crianças do seu EBI. Fora dele, aparece \"Exceção não encontrada.\" e nada muda. O Super Admin decide qualquer exceção."
  4. Em `Fluxo-do-responsavel.md` §2, acrescentar o aviso com o texto exato da especificação, usando ‹nome› e ‹data›, e dizer o que fazer: procurar a coordenação do EBI antes da data.
  5. Reescrever a §4 de `Fluxo-do-responsavel.md`: tirar os quatro passos (inclusive **ID do participante**) e a imagem `22-excecao.png`. O texto novo diz que o responsável procura a coordenação do EBI, que registra o pedido e decide, e que ele leva a descrição dos apoios de que a criança precisa, não um diagnóstico. Manter o link para [Exceção inclusiva](Excecao-inclusiva).
  6. Em "Dúvidas frequentes" de `Fluxo-do-responsavel.md`:
     - acrescentar **"Apareceu que meu filho faz 12 anos."**, com a resposta: sem exceção aprovada, a presença é bloqueada a partir da data; fale com a coordenação do EBI;
     - trocar a resposta de **"Posso cadastrar meu filho sozinho?"** por: "Não pelo portal. Peça à coordenação do EBI, que faz o cadastro com você; veja [Se você não tem conta no portal](#5-se-você-não-tem-conta-no-portal)." Não citar nenhuma tela.
- Atende: CA5, CA6
- Pronto quando: CT21 a CT31, CT45, CT49, CT50, CT56 e CT57 passam.
- Estimativa: M

## T4 — Responsável: pedir vínculo à coordenação
- Arquivos: `docs/wiki/Fluxo-do-responsavel.md`
- O que fazer:
  1. Reescrever a §1 "Pedir vínculo com a criança":
     - manter "Ninguém vê uma criança só por saber o nome dela. O primeiro passo é o **vínculo**.";
     - trocar os três passos e a imagem `23-vinculos.png` por: "Peça o vínculo à coordenação do EBI, informando a criança e a sua relação com ela. A coordenação registra o pedido e decide: pode aprovar, recusar ou pedir um documento que comprove a relação.";
     - trocar "Se pedirem documento, anexe a comprovação no próprio pedido." por "Se pedirem documento, leve a comprovação à coordenação.";
     - manter "Sem vínculo aprovado, a criança **não aparece** em Meus dependentes.".
  2. Em "Dúvidas frequentes", trocar a resposta de **"Meu filho não aparece em Meus dependentes."** por: "O vínculo da criança com a sua conta ainda não foi aprovado. Fale com a coordenação do EBI."
- Atende: CA11
- Pronto quando: CT51 a CT54 e CT58 passam.
- Estimativa: P

## T5 — Fluxo da coordenação (frases com link)
- Arquivos: `docs/wiki/Fluxo-da-coordenacao.md`
- O que fazer:
  1. No parágrafo de abertura, depois de "…**decidir as trocas**.", acrescentar: "No **Início**, o bloco **Fazem 12 anos nos próximos 30 dias** mostra quem vai precisar de exceção para continuar; veja [Exceção inclusiva](Excecao-inclusiva)."
  2. Na §5, trocar "Se aconteceu sem registro, use o lançamento em contingência com o motivo." por "Se a presença ficou em papel, encerre a sessão e use **Lançar lista em papel**; veja [Sessões e agenda](Sessoes-e-agenda)."
  3. Na §7, acrescentar o item: "Dado errado no cadastro: **Corrigir**, na busca de Participantes; veja [Participantes](Participantes)."
- Atende: CA1, CA3, CA6
- Pronto quando: CT7, CT19 e CT32 passam.
- Estimativa: P

## T6 — Desistir da troca
- Arquivos: `docs/wiki/Fluxo-do-auxiliar.md`, `docs/wiki/Escalas.md`
- O que fazer:
  1. Em `Fluxo-do-auxiliar.md`, na tabela da §1, acrescentar `| **Troca solicitada** | Você pediu troca e ela ainda não foi decidida | Aguardar ou **Desistir da troca** |`.
  2. No fim da §4 de `Fluxo-do-auxiliar.md`, acrescentar "Mudou de ideia?":
     - enquanto a situação é **Troca solicitada**, a linha mostra "Troca pedida; aguardando aprovação." e o botão **Desistir da troca**;
     - toque no botão. Aparece a confirmação "Desistir da troca?" ("Você continua escalada nesta sessão."). Confirme em **Desistir da troca**;
     - aparece "Pedido de troca cancelado.", e a escala volta para **Escalado** ou **Confirmado**, a situação de antes do pedido;
     - se a chefe já tiver decidido, aparece "Esta troca já foi decidida.", e a lista é atualizada com a decisão.
  3. Em `Escalas.md`, seção "Trocas", acrescentar: "Enquanto a troca está pendente, quem pediu pode desistir em **Minhas escalas**; veja [Fluxo do auxiliar](Fluxo-do-auxiliar)."
- Atende: CA7
- Pronto quando: CT33 a CT37 passam.
- Estimativa: P

## T7 — Auditoria e mapa das telas
- Arquivos: `docs/wiki/Fluxo-da-administracao-superior.md`, `docs/wiki/Mapa-das-telas.md`
- O que fazer:
  1. Em `Fluxo-da-administracao-superior.md` §3, depois da lista de perguntas, criar o bloco "**O que cada nível vê dos participantes**", com estas frases:
     - "Desde a versão 2.22, os eventos de participante (cadastro, consulta, mesclagem, correção, vínculo e exceção) ficam registrados no território **onde a ação foi feita**: o EBI ou a igreja de quem agiu.";
     - "Pedido de vínculo, pedido de exceção e cadastro com igreja de referência ficam no território **da igreja da criança**.";
     - "O Admin de setor e o Admin regional veem os eventos do próprio território. **Não** aparecem para eles: ações feitas pelo Super Admin; pedidos e cadastros de criança sem igreja de referência; e eventos anteriores à 2.22. Esses só o Super Admin vê.";
     - "Se um evento não aparece para você, isso não quer dizer que ele não aconteceu. Peça ao Super Admin para consultar."
  2. Em `Mapa-das-telas.md`:
     - na tabela, coluna "Para que serve": Participantes passa a "Buscar, cadastrar e corrigir participantes"; Minhas escalas passa a "Confirmar presença, pedir ou desistir de troca, ver conflitos";
     - em "### Início", acrescentar: "Para a coordenação, o bloco **Fazem 12 anos nos próximos 30 dias**; veja [Exceção inclusiva](Excecao-inclusiva).";
     - criar `### Meus dependentes` depois de "### Início", com uma frase sobre a tela, outra sobre o aviso dos 12 anos, `![Meus dependentes](images/13-dependentes-responsavel.png)` e o link [Fluxo do responsável](Fluxo-do-responsavel);
     - em "### Participantes", acrescentar o botão **Corrigir**;
     - em "### Minhas escalas", acrescentar **Desistir da troca**;
     - em "### Exceção inclusiva", trocar "Pedido de exceção (responsável) e decisão (coordenação) no mesmo lugar." por "Pedido e decisão de exceção, feitos pela coordenação (o responsável pede à coordenação)."
- Atende: CA8, CA9, CA5 (coerência)
- Pronto quando: CT38 a CT43 passam.
- Estimativa: P

## T8 — Preparar o ambiente e o dado de demonstração
- Arquivos: nenhum versionado. Cria `docs/esteira/2026-11-27-wiki-2-22/06-entrega.md` só se algum passo falhar (formato em `02-especificacao.md`, "Contratos").
- O que fazer (comandos na raiz do repositório; só dado de demonstração, só banco local):
  1. Subir o ambiente: `docker compose up -d --build`. O `docker-compose.override.yml` sobe junto o S3 local e o ClamAV. Espere `docker compose ps` mostrar `app` como `healthy`.
  2. Banco e usuários: `docker compose exec app php artisan migrate --seed` e depois `docker compose exec app php artisan db:seed --class=UsuariosTesteSeeder`. O `DatabaseSeeder` não chama o seeder de usuários de teste (`backend/database/seeders/DatabaseSeeder.php:17-23`); sem o segundo comando, os usuários abaixo não existem.
  3. Portal: em `frontend/`, rode `npm ci` (não `npm install`, que pode alterar o `package-lock.json`) e `npm run build`. O build grava em `backend/public/spa` (`frontend/vite.config.ts:16`), servido em `http://localhost:8000`.
  4. Login: e-mail do usuário e senha `senha123` (`UsuariosTesteSeeder.php:32`). Todo papel, menos `responsavel`, pede o segundo fator na tela **Confirmar acesso**, campo **Código**, botão **Validar** (`frontend/src/pages/Login.tsx:244-266`). Gere o código na hora, porque ele vale 30 segundos:
     `docker compose exec app php artisan tinker --execute="echo App\Domain\Identidade\Support\Totp::codigoAtual('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');"`
     O segredo é o de demonstração (`backend/database/seeders/MfaDemoSeeder.php:25`) e não pode aparecer em captura.
  5. **Criança na janela dos 12 anos.** Entre como `chefe@ebi.test`. Em **Participantes**, informe a finalidade `confirmacao_cadastro`, busque "Patota" e toque em **Corrigir** em "Patota (7 anos)". Mude **Data de nascimento** para (hoje + 10 dias − 12 anos), informe o motivo "dado de demonstração" e toque em **Salvar correção**. A criança não tem exceção, então entra no bloco do Início.
  6. **Faixa e sessões.** Ainda como chefe, em **Sessões**, no painel da agenda, aba **Faixas**, formulário **Nova faixa**: **Dia da semana** = o dia da semana de hoje; **Hora de início** e **Hora de fim** quaisquer (ex.: 09:00 e 10:00); **Início da vigência** = hoje. Toque em **Criar faixa**. No topo, ponha **De** = hoje e **Até** = hoje + 14 dias e toque em **Gerar próximas semanas** (o botão só aparece com a lista vazia).
  7. **Sessão encerrada.** Na sessão de hoje: **Abrir**, depois **Encerrar** → **Realizada**.
  8. **Escala aprovada com o auxiliar.** Como chefe, em **Equipes de escala**: **Criar minha equipe** e, em "Incluir da equipe do EBI", toque em "Auxiliar (teste)". Em **Escalas**, no mesmo período, toque em **Montar** na sessão da semana que vem, informe **Estimativa de participantes** (ex.: 10), toque em **Criar escala** e depois em **Aprovar**.
  9. **Troca pendente.** Entre como `auxiliar@ebi.test`. Em **Minhas escalas**, na sessão da semana que vem: **Confirmar**, depois **Pedir troca** → **Enviar pedido**. A linha passa a mostrar **Desistir da troca**.
  10. **Dependente do responsável de teste.** Não existe caminho pela tela (ver Riscos na especificação). Só no ambiente local, rode:
      `docker compose exec app php artisan tinker --execute="App\Domain\Participantes\Models\ResponsavelParticipante::where('nome','Mãe da Patota')->update(['usuario_id'=>App\Domain\Identidade\Models\Usuario::where('email_normalizado','responsavel@ebi.test')->value('id')]);"`
      Confira: o comando deve imprimir `1`. Se imprimir `0`, o passo 2 não rodou.
  11. Se um passo falhar, anote em `06-entrega.md` qual passo, o erro visto e quais capturas da T9 dependem dele, e siga para os passos que ainda dão.
- Atende: apoio a CA1, CA3, CA5, CA6 e CA7 (dá o dado das capturas). Não fecha critério sozinha.
- Pronto quando: como `chefe@ebi.test`, o Início mostra "Patota (7 anos)" no bloco dos 12 anos; como `responsavel@ebi.test`, **Meus dependentes** mostra o aviso; como `auxiliar@ebi.test`, **Minhas escalas** mostra **Desistir da troca**; ou a falha está registrada em `06-entrega.md`.
- Estimativa: M

## T9 — Capturar as telas e inserir no wiki
- Arquivos: em `docs/wiki/images/`, recapturar `07-participantes-chefe.png`, `02-inicio-chefe.png`, `09-inicio-coordenadora.png`, `13-dependentes-responsavel.png` e `12-minhas-escalas-auxiliar.png`, e criar `28-contingencia-lote.png`. Também `docs/wiki/Sessoes-e-agenda.md`.
- O que fazer (captura pelo navegador de `http://localhost:8000`, janela de cerca de 1280 × 800, em PNG, com o mesmo nome do arquivo antigo):
  1. `07-participantes-chefe.png`: como chefe, busca "Patota" com resultado e o botão **Corrigir** visível.
  2. `02-inicio-chefe.png`: Início do chefe com o bloco "Fazem 12 anos nos próximos 30 dias" listando "Patota (7 anos)".
  3. `09-inicio-coordenadora.png`: o mesmo, entrando como `coordenadora2@ebi.test`.
  4. `13-dependentes-responsavel.png`: como `responsavel@ebi.test`, **Meus dependentes** com o aviso dos 12 anos.
  5. `12-minhas-escalas-auxiliar.png`: como `auxiliar@ebi.test`, **Minhas escalas** com **Troca solicitada** e **Desistir da troca**.
  6. `28-contingencia-lote.png`: como chefe, em **Sessões**, **Presença** na sessão encerrada → **Lançar lista em papel**, motivo "lista em papel", marque "Patota (7 anos)" e "Patota Assistida (5 anos)". Capture com o rodapé **Lançar 2 presenças** visível, antes de lançar.
  7. Em `Sessoes-e-agenda.md`, no fim da lista de passos da subseção da T2, inserir `![Contingência em lote](images/28-contingencia-lote.png)`.
  8. Nenhuma captura pode mostrar o código TOTP, o segredo ou o terminal.
  9. Se uma captura não puder ser feita (por exemplo, sem ferramenta de navegador, ou com falha da T8), não sobrescreva a imagem antiga e não insira referência a imagem que não existe. Registre em `06-entrega.md`, tabela "Capturas", o arquivo e o motivo.
- Atende: apoio visual a CA1, CA3, CA5, CA6 e CA7. Não fecha critério sozinha.
- Pronto quando: `git status --short docs/wiki/images` lista os seis arquivos (o `28` como `??`, os outros como `M`) e a busca `28-contingencia-lote.png` encontra em `Sessoes-e-agenda.md`; ou cada arquivo que faltar está em `06-entrega.md` como pendente, e a página não referencia imagem ausente (CT15).
- Estimativa: M

## T10 — Conferência final e registro da entrega
- Arquivos: todos os alterados em `docs/wiki/`; `docs/esteira/2026-11-27-wiki-2-22/06-entrega.md` (criar ou completar)
- O que fazer:
  1. Executar todos os casos de `04-plano-testes.md`, inclusive CT14 e CT15 (links) e CT16 (textos procurados no código).
  2. Escrever ou completar `06-entrega.md` no formato de `02-especificacao.md`, "Contratos": o "Checklist do pull request" de `docs/wiki/Manutencao-do-wiki.md` marcado, a tabela "Capturas" com a situação de cada uma das seis imagens e a pendência de produto do vínculo do responsável.
  3. Conferir que só `docs/` mudou: `git status --short -- . ":!docs"` não mostra nada (`frontend/` e `backend/public/spa` gerados pelo build ficam fora, porque `backend/public/spa/` está no `.gitignore`).
- Atende: CA10 (e verificação de CA1 a CA9 e CA11)
- Pronto quando: todos os CTs passam e `06-entrega.md` tem as três seções preenchidas.
- Estimativa: P
