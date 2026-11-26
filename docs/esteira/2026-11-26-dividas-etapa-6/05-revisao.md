# Revisão

## Rodada 1 — Veredito: REPROVADO

Verificação feita por leitura do código e dos documentos. Nenhum teste foi executado.

### Bloqueante

- `02-especificacao.md`, "Segurança e autorização", item "Presença como chave de escopo", e seção 2, passo 4 — a mitigação declarada não se sustenta. `RegistradorPresenca::registrar` só confere a elegibilidade etária, e `ConsultaParticipante::buscar` (usada por `GET /sessions/{id}/participants`) não filtra por território. A coordenadora, ou a auxiliar dela, lança presença de qualquer cadastro do país numa sessão aberta do próprio EBI e passa a estar "ligada" a ele. Com isso ela corrige nome e data de nascimento e ainda troca `igreja_referencia_id` para a própria igreja (passa em `cobreIgreja`) ou para `null` (o passo 4 só confere valor não nulo). O cadastro sai das listas da igreja de origem, e estornar a presença depois não desfaz nada. É o cenário que o ADR-0027 quis impedir ("qualquer coordenadora alteraria cadastros do país inteiro"). — Precisa de decisão do produto registrada na especificação e de um caso de teste para ela. Opção mais barata: quem está ligado só por presença não altera `igreja_referencia_id`, e `null` só é aceito quando a igreja atual está no escopo do ator. Corrigir também o texto da mitigação: o acesso legítimo é à sessão, não ao participante.

- `02-especificacao.md`, seção 2 (`'motivo' => [..., 'max:500']`), schema `CorrecaoParticipanteInput` (`maxLength: 500`) e seção 3 (`FinalidadeLeitura::exigir`) — `evento_auditoria.motivo` é `string` (varchar 255) em `2026_11_08_000001_create_evento_auditoria_table.php`, e não achei migração que altere a coluna. No PostgreSQL, um motivo de 256 a 500 caracteres estoura dentro da transação e a correção vira 500. A suíte roda em SQLite, que não aplica o limite, então nenhum caso do plano pega isso. A finalidade vem do cliente sem limite e vai para a mesma coluna nas duas rotas novas. Inferido da migração, não executei em PostgreSQL. — Limitar `motivo` a 255 no request e no OpenAPI, e recusar finalidade acima de 255 com 422 no helper. A alternativa é uma migração para `text`, o que contradiz a premissa "sem migração" e teria de ser dito.

- `04-plano-testes.md`, casos M6, M7, C7 e F3, e "Dados e ambiente"; `03-plano-implementacao.md`, T3 item 3 — os casos com dois atores no mesmo teste dependem de `app('auth')->forgetGuards()` entre as requisições. Os testes atuais fazem isso (`EstornoHistoricoTest.php` 85, 105 e 160; `PresencaTest.php` 110), mas nenhum documento menciona, e o trecho indicado como modelo (linhas 16–29) não inclui a chamada. Sem ela, a segunda requisição roda com o usuário da primeira. No M6, o cabeçalho `papel=coordenadora` não corresponde a vínculo da chefe e `ResolvedorContextoAtuacao` responde `403 acting_scope_invalido`: o teste passa sem exercitar `exigirLigados`. — Escrever a regra em "Dados e ambiente". No M6, montar a mesclagem direto pelo serviço (`app(GerirMesclagem::class)->mesclar(...)`, como em `MesclagemTest.php` 102), para que o 403 só possa vir da regra nova.

### Recomendado

- `02-especificacao.md`, seção 1, "O `super_admin` passa sempre" — falso com o algoritmo descrito. `cobreTerritorio` retorna `false` antes de chamar `permite` quando não há igreja de referência, e sem presença a lista de EBIs é vazia. Resultado: 403 para o `super_admin` em cadastro sem igreja e sem presença. Esse cadastro (o responsável cadastra com igreja opcional) não é corrigível por ninguém, e a história manda os casos fora do escopo "para a chefe", que na correção segue a mesma regra da coordenadora. — Definir o comportamento (curto-circuito explícito para `super_admin` ou corrigir a frase) e acrescentar um caso de teste.

- `04-plano-testes.md`, C7 e CA5 — o CA7 cita o responsável sem `participante.corrigir`, mas C7 só cobre auxiliar e `adm_igreja`. No CA5, falta a chefe desfazendo sem nenhum dos dois ligados (403) e o desfazer com ligação só por presença. — Acrescentar os três casos.

- `03-plano-implementacao.md`, T5 item 3 — o intervalo "171–183" deixa de fora as linhas 184–185 de `EstornoHistoricoTest.php`, que também concatenam `$url.'?de=...'`. Com a finalidade já na `$url`, viram `?finalidade=teste?de=invalida`: o parâmetro `de` some e o esperado 422 vira 200. — Estender o intervalo para 171–185.

- `04-plano-testes.md`, "Dados e ambiente", `Idempotency-Key` — a chave de `Idempotencia::chave` é usuário + método + caminho + valor, sem o corpo. C4 faz quatro `PATCH` no mesmo caminho com o mesmo usuário; se a chave se repetir, as três últimas devolvem a resposta da primeira. — Escrever "uma chave nova por requisição".

- `02-especificacao.md`, seção 2 (serviço) e "Auditoria" — `participante.corrigido` continua com `escopo => null`, e `ConsultaAuditoria` nunca mostra evento sem escopo a quem não é `super_admin`. A correção feita pela coordenadora fica invisível para `adm_setor` e `adm_regional`, que têm `auditoria.ler`. — Gravar `ebi:<id>` do contexto de atuação (o controller passa ao serviço) ou registrar a limitação como dívida.

- `02-especificacao.md`, "Riscos" — falta o custo de transformar `GET /sessions/{id}/attendance` em escrita. `Presenca.tsx` chama `carregar()` depois de cada registro e de cada estorno (linhas 164 e 186). Cada chamada passa a gravar um evento com todos os `participante_ids` e a tomar o `lockForUpdate` da cadeia de auditoria, disputando com os lançamentos simultâneos das auxiliares. — Registrar o risco e a decisão: aceitar, ou não recarregar a lista após o `POST`.

- `02-especificacao.md`, "Sem vazamento por código de erro: a ordem 404 → 403 → 422 é mantida" — não vale para o `PATCH`, porque o `FormRequest` valida antes do controller e o corpo inválido responde 422 antes de 404 ou 403. Não vaza existência, mas a frase e o item "na ordem" da T4 estão errados. — Corrigir o texto para "validação do corpo → 404 → 403 → `nada_a_corrigir` → `igreja_fora_do_escopo` → 409".

- `03-plano-implementacao.md`, T7 "Pronto quando" — exige que os três arquivos citem `presenca.lida` e `PATCH /participants/{id}`, mas a especificação só manda pôr no ADR-0027 a definição de "ligado" e a regra da chefe. — Alinhar o critério às edições prescritas, arquivo por arquivo.

### Opcional

- `03-plano-implementacao.md`, T1 — depois de `npm run gen:api`, `finalidade` vira obrigatória no tipo e `npm run build` quebra até a T6. Avisar que o build do frontend só volta a passar na T6.
- `03-plano-implementacao.md`, T3 (linhas 233–244) e `04-plano-testes.md` (linhas 236–244) — citam intervalos diferentes do mesmo trecho de `AprovacaoEscalaTest.php`. Unificar.
- `04-plano-testes.md`, T1 — o cenário é ambíguo sobre serem duas ou três presenças válidas em A. Dizer "duas válidas em sessões diferentes de A e uma estornada em B".
- `02-especificacao.md`, seção 2 — um `PATCH` com valores iguais aos atuais grava `participante.corrigido` sem mudança. Comparar antes de gravar ou aceitar por escrito.

## Rodada 2 — Veredito: REPROVADO

Verificação feita por leitura do código e dos documentos. Nenhum teste foi executado.

Bloqueantes da rodada 1: os três estão resolvidos.

- B1 (presença como chave de escopo): resolvido. `ligado` não consulta presença quando há igreja de referência; CA12, M5b e C9 cobrem.
- B2 (limite de 255): resolvido no request, no OpenAPI e no helper; C4 e F7 cobrem a validação.
- B3 (`forgetGuards` e M6): resolvido em "Dados e ambiente"; a mesclagem dos casos de desfazer é montada pelo serviço.

Os recomendados e opcionais da rodada 1 também foram atendidos. O bloqueante abaixo já existia na rodada 1 e não foi apontado nela: a falha é desta revisão, não da correção do arquiteto.

### Bloqueante

- `02-especificacao.md`, "Decisões", linha "Participante `mesclado` não é corrigido (409)", e seção 2 (serviço) — a justificativa protege o cadastro errado. `GerenciadorMesclagem::desfazer` (linha 102) faz `$principal->fill($snapshot['principal'])` e regrava `nome`, `nome_social`, `data_nascimento` e `igreja_referencia_id` do **principal**; no duplicado só muda o `status`. O `snapshot_mesclagem` fica no principal até alguém desfazer, sem prazo. Com o `PATCH` novo, corrigir o principal depois da mesclagem e desfazer mais tarde apaga a correção sem aviso. O evento `participante.mesclagem_desfeita` não grava `antes`, e `participante.corrigido` só grava o valor anterior, então o valor corrigido não é recuperável pela auditoria. Não há regra nem caso de teste. — Registrar a decisão na especificação e acrescentar um caso de teste (mesclar pelo serviço, `PATCH` no principal, desfazer, conferir o resultado decidido). Caminhos possíveis: (a) a correção do principal com snapshot pendente também atualiza os mesmos campos em `snapshot['principal']`; (b) o desfazer só restaura os campos que a mesclagem alterou (`nome_social` e `igreja_referencia_id`); (c) aceitar a perda por escrito e gravar `antes` no evento do desfazer. Corrigir a frase da tabela em qualquer caso.

### Recomendado

- `04-plano-testes.md`, M10 — não testa o que a resposta à revisão diz ("desfazer com ligação só por presença"). `GerenciadorMesclagem::mesclar` (linha 71) copia a igreja do duplicado para o principal sem igreja; depois da mesclagem do par do M1, os dois cadastros estão ligados pela igreja A e a presença nem é consultada. — Inverter o par: principal da igreja A, duplicado sem igreja com presença válida em A.

- `02-especificacao.md`, seção 1 (`undo`) e "Segurança" — pelo mesmo motivo, a chefe pode ficar sem desfazer a própria mesclagem. Principal sem igreja com presença em A e duplicado da igreja B: a chefe de A mescla (um ligado), o principal herda a igreja B, e no desfazer nenhum dos dois está ligado a A: 403. É o caso típico do ADR-0027 (duplicidade entre EBIs). — Decidir e escrever: aceitar (a chefe de B desfaz) ou avaliar o principal pelo estado do snapshot. Acrescentar o caso de teste do comportamento escolhido.

- `04-plano-testes.md`, CA13; `03-plano-implementacao.md`, T3 ("Atende ... CA13 (mesclagem)") — o CA13 diz "corrige ou mescla", mas só existe o C10 (correção). — Acrescentar um caso M com o `super_admin` mesclando dois cadastros sem igreja e sem presença.

- `03-plano-implementacao.md`, T7; `01-historia.md`, "Dependências e premissas" — o texto de CA-27 em `docs/01-requisitos/criterios-de-aceite.md` (linha 33) continua "sem presença ou igreja de referência", o que contradiz o CA12 depois do estreitamento. Só o ADR-0027 é atualizado. — Incluir o arquivo na T7 com a redação nova, ou registrar por que fica como está.

- `02-especificacao.md`, "Segurança", "A chefe só alcança pares em que um dos cadastros está no EBI dela" — na prática ela alcança qualquer cadastro do país como duplicado, desde que o pareie com um dela: o cadastro alheio vira `mesclado`, some da busca da igreja de origem e perde os vínculos de responsável para o principal. É consequência da decisão "chefe: basta um", mas o risco residual não está escrito. — Registrar o risco e a mitigação (reversível, e a chefe da outra igreja consegue desfazer).

### Opcional

- `04-plano-testes.md`, "Super admin" — "se eles não enviarem o header, também não envie" deixa a escolha para quem implementa, e o C10 precisa do id do escopo para conferir `central:<id>`. Fixar: `criarUsuarioComPapel('super_admin', 'central', 'c1')`, como em `ChurchTest.php` (linha 9), e dizer se o header vai ou não.
- `02-especificacao.md`, seção 2, passo 7 — `$contexto` é `?ContextoAtuacao`; `exigirLigados` lança 403 para contexto nulo, mas o PHPStan não estreita o tipo por isso, e o acesso a `$contexto->tipoEscopo` pode ser acusado (não conferi o nível configurado). Dizer onde fica a guarda explícita de nulo.
- `02-especificacao.md`, seção 2 (serviço), "`$aplicados` é o `array_intersect_key` já calculado" — hoje ele é uma expressão inline em `GerenciadorParticipante::corrigir` (linha 224), sem variável. Dizer "extrair para `$aplicados`".
- `04-plano-testes.md`, C5 e C6 — o CA7 exige "nenhuma grava alteração nem evento", mas os dois casos não afirmam a ausência de `participante.corrigido`. C1 também não cobre "só os campos que realmente mudaram" (falta um `PATCH` com um campo novo e outro igual ao atual).
- `02-especificacao.md`, "Segurança", "Troca de igreja" — a coordenadora que põe `null` num cadastro da própria igreja sem presença válida no EBI perde o acesso a ele, e só o `super_admin` corrige depois. Registrar como risco aceito ou recusar `null` nesse caso.

## Aprovação humana
**Aprovado** pelo humano em 26/11/2026, depois de 2 rodadas (a segunda reprovada; os itens pendentes foram levados a ele).

Decisões do produto:
1. Confirmada a regra "presença só liga cadastro sem igreja de referência", proposta pelo arquiteto.
2. Bloqueante da rodada 2: opção (a). A correção do principal também atualiza `snapshot_mesclagem['principal']` (CA14, caso C11).
3. Aceito: quando o principal herda a igreja de outro EBI, quem desfaz é a chefe dessa igreja (caso M11).
4. Aceito o risco residual de a chefe alcançar qualquer cadastro como duplicado.

Os recomendados e opcionais da rodada 2 foram incorporados pelo orquestrador (o arquiteto estava indisponível): M10 invertido, M11, M12, C11, CA-27 em `criterios-de-aceite.md` na T7, riscos em "Segurança" e os ajustes opcionais.
