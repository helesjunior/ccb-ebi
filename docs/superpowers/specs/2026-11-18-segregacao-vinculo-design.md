# Design — Segregação de funções no vínculo (RF-03)

**Data:** 18/11/2026 · **Base:** `main` @ `01f0751` · **Épico:** E03 · **Escopo:** impedir que quem abriu o pedido de vínculo o aprove

## Intenção e por quê

O fluxo de vínculo permite que uma pessoa abra o pedido e o aprove sozinha. O caso concreto: a coordenadora cadastra o próprio filho e decide o próprio pedido. O controle de acesso que o vínculo representa (dar a um adulto acesso aos dados de uma criança) vira teatro — e a mesma brecha serve para inventar um responsável que não existe.

A regra de negócio vale como princípio, não só para o caso da coordenadora-mãe: **quem solicita não decide**. A aprovação fica com outro operador do mesmo território.

## Objetivos

- A decisão de um vínculo é recusada com **403** quando o ator é o autor do pedido.
- A tela de Pedidos não oferece os botões de decisão nas linhas de autoria do ator; mostra "Aguardando outro operador".
- O vínculo sai da alçada do autor sem depender do papel: vale para qualquer ator com `vinculo.gerenciar`.

## Não-objetivos

- Impedir que a coordenação crie um vínculo com `usuario_id = null` (cadastro assistido). **Decisão:** passa livre. **Fato do código:** o cadastro assistido (`POST /participants/assisted` → `GerenciadorParticipante::assistido`) grava o vínculo já como `VALIDACAO_APROVADA`, sem passar por `decidir` e sem segundo operador; o que resta como salvaguarda é a **evidência obrigatória** (autorização do conselho tutelar ou documento do responsável), não uma conferência independente. A brecha declarada é "a autora pode criar o pedido em nome de terceiro e ele já nasce aprovado"; registrada como dívida, não fechada aqui.
- Comparar nome/telefone do responsável com o ator (sem identificador equivalente).
- Escalonamento para nível superior quando não há segundo operador no território.
- Notificar o autor de que outro operador decidiu.

## Restrições

- Fronteira modular (ADR-0002): a regra pertence ao domínio `Participantes`; o controller só traduz para HTTP.
- HTTP 403 explicita a existência do vínculo, o que aqui é aceitável: o autor o abriu e ele já está na lista dele.
- O gate existente (`GuardianRequestController::update` → `AutorizacaoParticipante::pode`) continua obrigatório antes da regra nova, mas **não é exclusivamente territorial**: `pode()` libera por propriedade (`AutorizacaoParticipante.php:64-66`) antes de olhar o território. Quem decide vínculo precisa de verificação territorial estrita (`cobreTerritorio`), senão um responsável da EBI A com escopo de coordenação decide pedido de criança da EBI B.

## Abordagens consideradas

- **A — Regra no `decidir` (escolhida).** `GerenciadorVinculoResponsavel::decidir` carrega o vínculo e recusa quando `usuario_id === $atorId`. A regra vale para qualquer chamador do domínio, não só para a rota HTTP, e não depende do controller lembrar dela.
- **B — Regra no controller.** Uma comparação antes da chamada, como o gate territorial. Mais barata, mas deixa o domínio aberto para um segundo chamador (CLI, job, outro endpoint) que ignore a regra.
- **C — Política de autorização dedicada** (`AutorizacaoVinculo::podeDecidir`). Alinhada ao padrão de autorização do projeto, porém cria uma classe para uma comparação de dois campos que o domínio já tem em mãos.

## Decisões

1. **403, não 404.** O autor conhece o vínculo; 403 explica o fluxo (`A decisão deve ser feita por outro operador do seu território.` — vale para aprovar, recusar, pedir documento, revogar e contestar) em vez de fingir inexistência.
2. **A regra vive em `decidir`.** O controller não reimplementa a comparação; o 403 nasce no domínio como `ProblemException('acesso_negado', ..., 403)`. Vale para **todas** as decisões, inclusive `revogar`/`contestar`, e em qualquer estado (pendente, aprovado, encerrado).
3. **`usuario_id = null` segue aprovavel por qualquer operador do território** — cadastro assistido (ver não-objetivos).
4. **Gate territorial estrito no controller.** `update` deixa de usar `pode()` e passa a exigir `cobreTerritorio(...)` com `vinculo.gerenciar`: aprovar/recusar em nome de outrem não é acesso à própria criança. Fora do território, 404 (regra já praticada).
5. **UI**: na linha em que `pedido.usuario_id` é o usuário atual **e o vínculo está em andamento** (`pendente`/`aguardando_documento`), a coluna de ações mostra "Aguardando outro operador" em vez dos botões. Em qualquer estado final do próprio autor (`aprovada`, `recusada`, `encerrado`) a célula fica **vazia**. O autor continua vendo o próprio pedido, porque a lista é a leitura dos pedidos.
6. **Comparação só com identificadores presentes.** `pedido.usuario_id` e o id do usuário logado são opcionais no contrato; a linha só é considerada própria quando ambos existem e são iguais. Sem o id do usuário, a tela trata nenhuma linha como própria e o backend continua sendo a autoridade (403).

## Como fica por dentro

- `GerenciadorVinculoResponsavel::decidir`: já carrega o vínculo; comparar `$vinculo->usuario_id !== null && $vinculo->usuario_id === $atorId` com `$atorId !== ''` e lançar `ProblemException('acesso_negado', 'Acesso negado', 403, '...')` antes de mutar. A comparação ocorre dentro da transação, junto da busca, para não haver leitura fora dela.
- `GuardianRequestController::update`: troca `autorizacao->pode(...)` por `autorizacao->cobreTerritorio($contexto, $participante, Operacoes::VINCULO_GERENCIAR)` mantendo o 404.
- `App.tsx`: `<Vinculos escopos={escopos} usuarioId={usuario.id ?? null} />`. `Vinculos` recebe `usuarioId: string | null` e renderiza "Aguardando outro operador" apenas quando `usuarioId !== null && pedido.usuario_id === usuarioId` e o vínculo está em andamento.
- Contrato OpenAPI: documentar o 403 do `PATCH /guardian-requests/{id}` com a descrição da regra.

## Testes

- Domínio: chamada direta a `GerirVinculoResponsavel::decidir` pelo autor lança `ProblemException` com status 403 e código `acesso_negado`; o vínculo fica byte a byte igual (`validacao`, `conferido_por`, `conferido_em`, `fim`, `updated_at`); nenhuma linha nova em `SolicitacaoVinculo` e nenhum evento `vinculo.decidido`.
- HTTP: `PATCH` do autor → 403 com `code = acesso_negado`; o vínculo continua `pendente`; parametrizado para `aprovar`, `recusar`, `pedir_documento`, `revogar`, `contestar`; outro operador do mesmo território → 200; vínculo `usuario_id = null` → 200; responsável por propriedade sem o território → 404.
- Listagem: o autor continua vendo o próprio pedido (não é filtrado por autoria) — `ListarVinculosTest` e `ListarVinculosHttpTest` rodam no gate, não só `VinculoResponsavelTest`.
