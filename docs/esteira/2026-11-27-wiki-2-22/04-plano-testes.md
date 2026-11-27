# Plano de testes

## Como rodar

O projeto não tem lint nem teste para `docs/wiki/`: o CI (`.github/workflows/ci.yml`) valida só `docs/04-api/openapi.yaml`. A verificação usa a ferramenta `Grep`, sempre com `literal: true` e **uma busca por texto**. Não use alternância (`A|B`). A busca é feita linha a linha, por isso a regra do plano: mensagem citada fica inteira numa linha.

Conferir que só `docs/` mudou: `git status --short -- . ":!docs"` não deve mostrar nada.

Ambiente para as capturas (T8 e T9), conforme `CONTRIBUTING.md`:
- `docker compose up -d --build`
- `docker compose exec app php artisan migrate --seed`
- `docker compose exec app php artisan db:seed --class=UsuariosTesteSeeder`
- em `frontend/`: `npm ci` e `npm run build`

## Casos

Coluna "Cenário": texto literal buscado e arquivo em `docs/wiki/`. "Encontra" quer dizer ao menos uma ocorrência; "Vazio", nenhuma. Todo caso "Encontra" usa um texto que não existe na página antes da mudança, salvo quando a coluna diz o contrário.

| ID | Critério | Nível | Cenário (busca) | Resultado esperado |
|---|---|---|---|---|
| CT1 | CA1 | integração | `## Corrigir um cadastro` em `Participantes.md` | Encontra |
| CT2 | CA1 | integração | `Salvar correção` em `Participantes.md` | Encontra |
| CT3 | CA1 | integração | `Motivo da correção` em `Participantes.md` | Encontra |
| CT4 | CA1 (quem pode) | integração | `| Corrigir cadastro | Coordenação, Super Admin |` em `Participantes.md` | Encontra |
| CT5 | CA1 (campo igreja) | integração | `identificador` em `Participantes.md` | Encontra |
| CT6 | CA1 (erro) | integração | Uma busca para cada recusa em `Participantes.md`: `O participante não está ligado a um EBI onde você atua.`, `Cadastro mesclado não pode ser corrigido; desfaça a mesclagem.`, `Nenhum campo difere do cadastro atual.`, `A nova igreja de referência não está no seu escopo.` | As quatro encontram |
| CT7 | CA1 (link) | integração | `**Corrigir**` em `Fluxo-da-coordenacao.md` | Encontra (o link `(Participantes)` já existe na página e não prova nada) |
| CT8 | CA2 (erro) | integração | `A igreja de referência não está no seu escopo.` em `Participantes.md` | Encontra |
| CT9 | CA3 | integração | `### Lançar a lista em papel (contingência)` em `Sessoes-e-agenda.md` | Encontra |
| CT10 | CA3 | integração | `Lançar ‹N› presenças` em `Sessoes-e-agenda.md` | Encontra |
| CT11 | CA3 | integração | `‹n› lançadas, ‹m› já existiam` em `Sessoes-e-agenda.md` | Encontra |
| CT12 | CA3 (erro) | integração | `Não lançadas: ‹nomes›` em `Sessoes-e-agenda.md` | Encontra |
| CT13 | CA3 (limite) | integração | `100 crianças` em `Sessoes-e-agenda.md` | Encontra |
| CT14 | CA10 | integração | Regex (não literal) `\]\([^)]*\.md\)` em `docs/wiki` | Vazio |
| CT15 | CA10 (erro) | integração | Para cada `](Nome)` e `](images/x.png)` das páginas alteradas, `Glob` de `docs/wiki/Nome.md` ou `docs/wiki/images/x.png`. Para cada `](#ancora)`, conferir que existe na mesma página um título que gera essa âncora (minúsculas, pontuação removida, espaços viram `-`). | Todo alvo existe; um que falte reprova |
| CT16 | CA1, CA3, CA5–CA7, CA11 | integração | Procurar cada trecho fixo da tabela "Trechos para conferir no código" abaixo, no arquivo indicado | Cada busca encontra |
| CT17 | CA3 (quem pode) | integração | `| Lançar lista em papel (contingência) | Coordenação, Admin da igreja |` em `Sessoes-e-agenda.md` | Encontra |
| CT18 | CA3 (texto velho) | integração | `**Contingência.**` em `Sessoes-e-agenda.md` | Vazio |
| CT19 | CA3 (link) | integração | `Lançar lista em papel` em `Fluxo-da-coordenacao.md` | Encontra |
| CT20 | CA4 | integração | `3 a 247` em `Sessoes-e-agenda.md` | Encontra |
| CT21 | CA5 | integração | `faz 12 anos em` em `Fluxo-do-responsavel.md` | Encontra |
| CT22 | CA5 | integração | `peça a exceção à coordenação do EBI` em `Fluxo-do-responsavel.md` | Encontra |
| CT23 | CA5 (erro: tela que não existe) | integração | `22-excecao.png` em `Fluxo-do-responsavel.md` | Vazio |
| CT24 | CA5 (erro: tela que não existe) | integração | Uma busca para cada texto em `Fluxo-do-responsavel.md`: `Cadastrar participante`, `Participantes → Cadastr` | As duas vazias |
| CT25 | CA6 | integração | `## Aviso dos 12 anos` em `Excecao-inclusiva.md` | Encontra |
| CT26 | CA6 (quem vê) | integração | `coordenadora chefe` em `Excecao-inclusiva.md` | Encontra |
| CT27 | CA6 (o que lista) | integração | `igreja de referência` em `Excecao-inclusiva.md` | Encontra |
| CT28 | CA6 (vazio) | integração | `Ninguém faz 12 anos nos próximos 30 dias.` em `Excecao-inclusiva.md` | Encontra |
| CT29 | CA6 (erro) | integração | `Não foi possível carregar os aniversários.` em `Excecao-inclusiva.md` | Encontra |
| CT30 | CA6 (auditoria) | integração | `registra a consulta` em `Excecao-inclusiva.md` | Encontra |
| CT31 | CA6 (erro: território) | integração | `Exceção não encontrada.` em `Excecao-inclusiva.md` | Encontra |
| CT32 | CA6 (link) | integração | `Fazem 12 anos nos próximos 30 dias` em `Fluxo-da-coordenacao.md` | Encontra (o link `(Excecao-inclusiva)` já existe na página) |
| CT33 | CA7 | integração | `| **Troca solicitada** |` em `Fluxo-do-auxiliar.md` | Encontra |
| CT34 | CA7 (confirmação) | integração | `Desistir da troca?` em `Fluxo-do-auxiliar.md` | Encontra |
| CT35 | CA7 | integração | `Pedido de troca cancelado.` em `Fluxo-do-auxiliar.md` | Encontra |
| CT36 | CA7 (erro) | integração | `Esta troca já foi decidida.` em `Fluxo-do-auxiliar.md` | Encontra |
| CT37 | CA7 (link) | integração | `pode desistir em **Minhas escalas**` em `Escalas.md` | Encontra |
| CT38 | CA8 | integração | `onde a ação foi feita` em `Fluxo-da-administracao-superior.md` | Encontra |
| CT39 | CA8 | integração | `sem igreja de referência` em `Fluxo-da-administracao-superior.md` | Encontra |
| CT40 | CA8 | integração | `anteriores à 2.22` em `Fluxo-da-administracao-superior.md` | Encontra |
| CT41 | CA8 (não prometer demais) | integração | `território da criança` em `Fluxo-da-administracao-superior.md` | Vazio (a frase certa é "território **da igreja da criança**", só nos pedidos e cadastros) |
| CT42 | CA9 | integração | Uma busca para cada texto em `Mapa-das-telas.md`: `### Meus dependentes`, `corrigir participantes`, `desistir de troca`, `Fazem 12 anos`, `o responsável pede à coordenação` | Os cinco encontram |
| CT43 | CA9 (erro) | integração | `Pedido de exceção (responsável)` em `Mapa-das-telas.md` | Vazio |
| CT44 | CA5 (coerência) | integração | `Coordenação, responsável |` em `Participantes.md` | Vazio |
| CT45 | CA5 (dúvida "Posso cadastrar…") | integração | `Peça à coordenação do EBI, que faz o cadastro com você` em `Fluxo-do-responsavel.md` | Encontra |
| CT46 | CA1, CA2 (erro: identificador inexistente) | integração | `em inglês` em `Participantes.md` | Encontra |
| CT47 | CA2 (responsável não cadastra pela tela) | integração | `| Cadastrar | Administradores, coordenação, auxiliar, responsável |` em `Participantes.md` | Vazio |
| CT48 | CA2 (responsável não cadastra pela tela) | integração | `ou o próprio responsável` em `Participantes.md` | Vazio |
| CT49 | CA5 (sem passos de tela) | integração | `ID do participante` em `Fluxo-do-responsavel.md` | Vazio |
| CT50 | CA6 (contradição) | integração | `no lugar do responsável` em `Excecao-inclusiva.md` | Vazio |
| CT51 | CA11 (erro: aba que não existe) | integração | `Vínculos` em `Fluxo-do-responsavel.md` (diferencia maiúsculas) | Vazio |
| CT52 | CA11 (erro: aba que não existe) | integração | `23-vinculos.png` em `Fluxo-do-responsavel.md` | Vazio |
| CT53 | CA11 | integração | `Peça o vínculo à coordenação do EBI` em `Fluxo-do-responsavel.md` | Encontra |
| CT54 | CA11 (sem anexo pelo portal) | integração | `anexe` em `Fluxo-do-responsavel.md` | Vazio |
| CT55 | CA11 (página de referência) | integração | `pede à coordenação do EBI` em `Participantes.md` | Encontra |
| CT56 | CA5 (link da dúvida) | integração | `(#5-se-você-não-tem-conta-no-portal)` em `Fluxo-do-responsavel.md` | Encontra; o título `## 5. Se você não tem conta no portal` continua na página |
| CT57 | CA6 (criança de outra igreja) | integração | `coordenação de lá` em `Excecao-inclusiva.md` | Encontra |
| CT58 | CA11 (dúvida "Meu filho não aparece…") | integração | `ainda não foi aprovado. Fale com a coordenação do EBI.` em `Fluxo-do-responsavel.md` | Encontra |
| CT59 | Capturas (T9) | e2e | Abrir `http://localhost:8000` com o dado da T8 e conferir cada tela listada na T9 | Cada tela mostra a novidade; o que não puder ser feito está em `06-entrega.md` |

### Trechos para conferir no código (CT16)

Cada trecho é fixo, ou seja, não tem a parte que varia na tela. Basta encontrar o trecho literal no arquivo.

| Texto no wiki | Trecho a buscar | Arquivo |
|---|---|---|
| Cadastro corrigido. | `Cadastro corrigido.` | `frontend/src/components/CorrigirParticipante.tsx` |
| Salvar correção | `Salvar correção` | `frontend/src/components/CorrigirParticipante.tsx` |
| Motivo da correção (3 a 255 caracteres) | `Motivo da correção (3 a 255 caracteres)` | `frontend/src/components/CorrigirParticipante.tsx` |
| Recusa: não ligado | `O participante não está ligado a um EBI onde você atua.` | `backend/app/Support/Participantes/AutorizacaoParticipante.php` |
| Recusa: mesclado | `Cadastro mesclado não pode ser corrigido; desfaça a mesclagem.` | `backend/app/Domain/Participantes/Services/GerenciadorParticipante.php` |
| Recusas: nada mudou e igreja | `Nenhum campo difere do cadastro atual.` e `não está no seu escopo.` | `backend/app/Http/Controllers/Api/V1/ParticipantController.php` |
| Identificador inexistente | `exists:igrejas,id` | `backend/app/Http/Requests/Participantes/CorrigirParticipanteRequest.php` e `CadastrarParticipanteRequest.php` |
| Lançar lista em papel | `Lançar lista em papel` | `frontend/src/pages/Presenca.tsx` |
| Lançar ‹N› presenças | `} presenças` (fim do template `Lançar ${marcados.length} presenças`) | `frontend/src/pages/Presenca.tsx` |
| ‹n› lançadas, ‹m› já existiam | `lançadas, ` e `já existiam` | `frontend/src/pages/Presenca.tsx` |
| Não lançadas: ‹nomes› | `Não lançadas:` | `frontend/src/pages/Presenca.tsx` |
| Motivo da contingência | `Motivo da contingência (3 a 255 caracteres)` | `frontend/src/pages/Presenca.tsx` |
| Limite de 100 | `max:100` | `backend/app/Http/Requests/Agenda/ContingenciaLotePresencaRequest.php` |
| Motivo da exceção de calendário | `3 a 247 caracteres` | `frontend/src/components/agenda/PainelAgenda.tsx` |
| Aviso ao responsável | `faz 12 anos em`, `Sem exceção inclusiva`, `aprovada, a presença fica bloqueada a partir dessa data. Se for o caso, peça a` e `exceção à coordenação do EBI.` (o texto está quebrado em linhas) | `frontend/src/pages/Dependentes.tsx` |
| Bloco do Início | `Fazem 12 anos nos próximos 30 dias`, `Ninguém faz 12 anos nos próximos 30 dias.` e `Não foi possível carregar os aniversários.` | `frontend/src/pages/Inicio.tsx` |
| Território na exceção | `Exceção não encontrada.` | `backend/app/Http/Controllers/Api/V1/AgeExceptionController.php` |
| Troca solicitada | `'Troca solicitada'` | `frontend/src/lib/formatos.ts` |
| Desistir da troca | `Desistir da troca?`, `Você continua escalada nesta sessão.`, `Pedido de troca cancelado.` e `Troca pedida; aguardando aprovação.` | `frontend/src/pages/MinhasEscalas.tsx` |
| Erro da desistência | `Esta troca já foi decidida.` | `backend/app/Domain/Escalas/Services/GerenciadorTrocaEscala.php` |
| Cartão do pedido | `Solicitar (responsável)` | `frontend/src/pages/ExcecaoInclusiva.tsx` |
| Aba Solicitar escondida do responsável | `a aba Solicitar não aparece para ele` | `frontend/src/pages/Vinculos.tsx` |

## Dados e ambiente

- CT1 a CT58 só precisam do repositório.
- CT59 precisa do ambiente e do dado montados na T8 (`03-plano-implementacao.md`): usuários do `UsuariosTesteSeeder`, senha `senha123`, código TOTP gerado com `Totp::codigoAtual` sobre o segredo de demonstração, criança na janela dos 12 anos, faixa de hoje, sessão encerrada, troca pendente e o responsável de teste ligado a "Mãe da Patota".

## Fora da cobertura automática

- Ler as páginas e confirmar que um usuário comum entende o passo a passo: voz do usuário, sem jargão.
- Confirmar que cada frase está na seção certa. O `Grep` acha o texto no arquivo, mas não diz em que seção ele está.
- Abrir as capturas novas e confirmar que mostram a novidade, sem código TOTP e sem dado real de criança.
- Depois do merge, publicar no GitHub Wiki e conferir as imagens e a âncora `#5-se-você-não-tem-conta-no-portal`, porque o GitHub Wiki pode gerar âncoras diferentes das do repositório.
- O furo de produto do vínculo do responsável (`02-especificacao.md`, Riscos) não é testado aqui: é pendência para uma demanda própria.
