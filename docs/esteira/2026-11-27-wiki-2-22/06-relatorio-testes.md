# Relatório de testes

Intervalo: `b122d17..HEAD` (branch `feat/wiki-2-22`, 10 commits). Fonte: `01-historia.md` e `04-plano-testes.md`.

## Veredito: APROVADO

## Critérios de aceite
| CA | Situação (atendido/parcial/ausente) | Evidência |
|---|---|---|
| CA1 | atendido | `Participantes.md` § "Corrigir um cadastro": passos com os 7 rótulos, identificador, quem corrige, 4 recusas + aviso em inglês; linha `| Corrigir cadastro | Coordenação, Super Admin |`; `Fluxo-da-coordenacao.md` §7 cita **Corrigir** com link `(Participantes)`. CT1–CT7, CT46 |
| CA2 | atendido | Seção "Cadastrar": escopo, "A igreja de referência não está no seu escopo.", aviso em inglês; "ou o próprio responsável" e o responsável na linha "Cadastrar" foram removidos. CT8, CT46–CT48 |
| CA3 | atendido | `Sessoes-e-agenda.md` § "Lançar a lista em papel (contingência)": passos 1–5, resumo, "Não lançadas", 100 crianças, só sessão Encerrada, linha na tabela; `Fluxo-da-coordenacao.md` §5 com link. CT9–CT13, CT17–CT19 |
| CA4 | atendido | "O **motivo** é obrigatório, de 3 a 247 caracteres." CT20 |
| CA5 | atendido | §2 com o aviso literal; §4 sem passos, sem ID e sem `22-excecao.png`; dúvida "Posso cadastrar…" manda à coordenação, com link para §5. CT21–CT24, CT45, CT49, CT56 |
| CA6 | atendido | `Excecao-inclusiva.md` § "Aviso dos 12 anos" com quem vê, o que lista, criança sem igreja/outra igreja, vazio, erro, auditoria; "Exceção não encontrada." em Decidir; "no lugar do responsável" removido; frase + link em `Fluxo-da-coordenacao.md`. CT25–CT32, CT50, CT57 |
| CA7 | atendido | `Fluxo-do-auxiliar.md` §4: linha **Troca solicitada**, confirmação, sucesso, volta a Escalado/Confirmado, erro; frase + link em `Escalas.md`. CT33–CT37 |
| CA8 | atendido | Auditoria: "onde a ação foi feita", "da igreja da criança" só nos pedidos/cadastros, sem igreja/Super Admin/anteriores à 2.22 só para o Super Admin; "território da criança" ausente. CT38–CT41 |
| CA9 | atendido | `Mapa-das-telas.md`: Início, Participantes, Minhas escalas e nova seção Meus dependentes; Exceção inclusiva sem "(responsável)". CT42, CT43 |
| CA10 | atendido | 0 links terminando em `.md`; 79 links das 9 páginas alteradas conferidos, todos os alvos existem. CT14, CT15 |
| CA11 | atendido | §1 sem Vínculos/Solicitar/`23-vinculos.png`/"anexe"; dúvida "Meu filho não aparece…" manda à coordenação; `Participantes.md` "pede à coordenação do EBI". CT51–CT55, CT58 |

## Casos de teste
| ID | Resultado | Comando / evidência |
|---|---|---|
| CT1–CT13 | OK | `Select-String -SimpleMatch -CaseSensitive` por caso (script único). Contagens: CT1=1, CT2=2, CT3=1, CT4=1, CT5=4, CT6a–d=1 cada, CT7=1, CT8–CT13=1 |
| CT14 | OK (vazio) | `Select-String -Path docs/wiki/*.md -Pattern '\]\([^)]*\.md\)'` → nenhuma linha |
| CT15 | OK | Script extrai `](alvo)` das 9 páginas alteradas (79 links), `Test-Path` para página/imagem e slug dos títulos para âncoras → nenhum "FALTA" |
| CT16 | OK | 33 buscas literais nos arquivos da tabela "Trechos para conferir no código": todas com n≥1 (ex.: `max:100` em `ContingenciaLotePresencaRequest.php` n=1; `Esta troca já foi decidida.` em `GerenciadorTrocaEscala.php` n=2; `a aba Solicitar não aparece para ele` em `Vinculos.tsx` n=1) |
| CT17–CT22 | OK | CT17=1, CT18=0, CT19=1, CT20=1, CT21=1, CT22=1 |
| CT23–CT24 | OK (vazio) | 0, 0, 0 |
| CT25–CT40 | OK | todos n≥1 (CT27=2) |
| CT41 | OK (vazio) | 0 |
| CT42a–e | OK | 1 cada |
| CT43, CT44 | OK (vazio) | 0, 0 |
| CT45, CT46 | OK | 1, 2 |
| CT47–CT52 | OK (vazio) | 0 cada (CT51 com diferenciação de maiúsculas) |
| CT53 | OK | 1 |
| CT54 | OK (vazio) | 0 |
| CT55–CT58 | OK | 1 cada (CT56: link e título `## 5. Se você não tem conta no portal` presentes) |
| CT59 | OK (por inspeção das capturas) | Portal: `Invoke-WebRequest http://localhost:8000` → `HTTP 200`. Abri as 6 capturas: 02 e 09 mostram o bloco "Fazem 12 anos nos próximos 30 dias"; 07 mostra **Corrigir** em cada linha e o toast "Cadastro corrigido."; 12 mostra "Troca solicitada" + **Desistir da troca**; 13 mostra o aviso dos 12 anos ao responsável; 28 mostra modo contingência com 2 etiquetas e **Lançar 2 presenças**. Sem código TOTP; só dados de seed (`*@ebi.test`, "Patota") |
| Escopo | OK | `git diff --name-only b122d17..HEAD -- . ":!docs"` → vazio; `git status --short` → vazio |

Discriminação dos testes: amostra de casos "Encontra" rodada contra `b122d17` (`git show b122d17:docs/wiki/…`): `Salvar correção`, `100 crianças`, `coordenadora chefe`, `igreja de referência` (Excecao-inclusiva), `identificador`, `Desistir da troca?` → n=0 na base. Os casos falhariam sem a mudança.

Conferência extra contra o código (fora do plano): `Participantes.tsx:54` só mostra **Corrigir** para `coordenadora`, `coordenadora_chefe` e `super_admin` (bate com "Os outros perfis não veem o botão"); `Presenca.tsx:62-64` libera a contingência a `adm_igreja`/coordenação em sessão `encerrada` (bate com a linha "Coordenação, Admin da igreja"); `Limpar seleção`, `Sair da contingência`, `Bloqueado` e a etiqueta `contingência` existem em `Presenca.tsx`; `MinhasEscalas.tsx:105` recarrega a lista no erro da desistência (bate com "a lista é atualizada").

## Entregue e quebrado
Nada. Nenhum caso falhou.

## Não verificado
- Não exercitei as telas no navegador (login com TOTP). A verificação do CT59 foi feita pelas capturas e pelo HTTP 200 do portal. O caminho de erro de cada mensagem só foi conferido pelo texto literal no código.
- Leitura humana: tom de voz do usuário, ausência de jargão e se cada frase está na seção certa. O grep não vê a seção.
- Depois de publicar no GitHub Wiki: imagens e a âncora `#5-se-você-não-tem-conta-no-portal`, porque o slug do GitHub Wiki pode ser outro.
- Pendência de produto registrada em `06-entrega.md`: o responsável não tem tela para se vincular a uma criança. Fica fora desta demanda.

## Escalar ao super-testador
Nada. O plano não tem casos `[COMPLEXO]` e não houve falha intermitente nem resultado sem explicação.
