# Plano de testes

## Como rodar

O backend roda sempre no contêiner (veja `README.md`):

```bash
docker compose exec app php artisan optimize:clear     # antes de cada rodada (a matriz fica no Opcache)
docker compose run --rm app composer test               # suíte inteira (Pest)
docker compose run --rm app php artisan test --filter=ContingenciaLoteTest
docker compose run --rm app composer pint -- --test     # formatação
docker compose run --rm app composer phpstan            # análise estática
python scripts/check-endpoints.py                       # catálogo × OpenAPI
docker compose run --rm -e TESTE_PGSQL=1 app vendor/bin/pest --testsuite=Concorrencia   # PostgreSQL real (CI: .github/workflows/ci.yml:81)
```

Frontend (em `frontend/`):

```bash
npm run gen:api   # depois de mexer no openapi.yaml
npm run test      # vitest
npm run lint      # oxlint
npm run build     # tsc + vite
```

## Casos

Arquivos de teste:

| Arquivo | Situação | Casos |
|---|---|---|
| `frontend/src/pages/Dependentes.test.tsx` | novo | CT-1, CT-2 |
| `frontend/src/pages/Inicio.test.tsx` | novo | CT-3, CT-4 |
| `backend/tests/Feature/Participantes/AvaliadorElegibilidadeTest.php` | muda | CT-5 a CT-9 |
| `backend/tests/Feature/Participantes/AvisoDozeAnosTest.php` | novo | CT-10 a CT-14 |
| `backend/tests/Feature/Participantes/EscopoAuditoriaParticipanteTest.php` | novo | CT-15 a CT-19, CT-41 |
| `backend/tests/Feature/Participantes/ExcecaoEtariaTest.php` | muda | CT-37 a CT-39 |
| `CadastroParticipanteTest.php` e `CadastroAssistidoTest.php` (em `backend/tests/Feature/Participantes/`) | mudam | CT-40 |
| `backend/tests/Feature/Api/ChurchTest.php` | muda | CT-30 (transferência) |
| `frontend/src/components/agenda/PainelAgenda.test.tsx` | novo | CT-42 |
| `backend/tests/Feature/Escalas/TrocaEscalaTest.php` | muda | CT-20, CT-21 |
| `frontend/src/pages/MinhasEscalas.test.tsx` | muda | CT-22, CT-23 |
| `backend/tests/Feature/Agenda/ContingenciaLoteTest.php` | novo | CT-24 a CT-28 |
| `backend/tests/Concorrencia/PresencaConcorrenciaTest.php` | novo | CT-29 |
| `ContingenciaTest.php`, `EstornoHistoricoTest.php`, `ExcecaoAgendaTest.php` (em `backend/tests/Feature/Agenda/`) | mudam | CT-30, CT-31 |
| `frontend/src/pages/Presenca.test.tsx` | muda | CT-32, CT-33, CT-43 |
| `frontend/src/pages/Participantes.test.tsx` | muda | CT-34 a CT-36 |

| ID | Critério | Nível | Cenário | Resultado esperado |
|---|---|---|---|---|
| CT-1 | CA3 | unidade (vitest) | `GET /me/participants` devolve dois dependentes: um com `faz_12_em: '2026-12-10'` e outro com `null`. | Um único `data-testid="aviso-12-anos"`, com o nome do primeiro e "10/12/2026". |
| CT-2 | CA3 (erro) | unidade (vitest) | `GET /me/participants` responde 500. | A mensagem de erro que a tela já mostra hoje aparece, e não há aviso. |
| CT-3 | CA4 | unidade (vitest) | Parametrizado (`it.each`) com `papel:'coordenadora'` e `papel:'coordenadora_chefe'`, ambos com `tipo_escopo:'ebi', escopo_id:'E1'`; a API devolve 1 alerta. Outro render com uma lista vazia. Outro com `papel:'auxiliar'`. Outro com `papel:'coordenadora_chefe', tipo_escopo:'igreja'`. | A chamada usa `path.id = 'E1'` e `query.finalidade = 'aviso_12_anos'`. O card lista o nome e a data; clicar em "Exceção inclusiva" chama `onSelecionarTela('excecao')`. Com a lista vazia, aparece "Ninguém faz 12 anos nos próximos 30 dias.". Com `auxiliar` e com a chefe em escopo `igreja`, não há card nem chamada a `/ebis/{id}/age-alerts`. |
| CT-4 | CA5 (erro) | unidade (vitest) | `GET /ebis/{id}/age-alerts` responde 403. | Aparece "Não foi possível carregar os aniversários.", e o resto do Início continua sendo renderizado. |
| CT-5 | CA3 | unidade (Pest) | `aviso12Anos` com `agora` = 2026-11-26 12:00Z e fuso `America/Sao_Paulo`. Nascimentos: 2014-11-26 (hoje), 2014-12-26 (dia 30), 2014-12-27 (dia 31) e 2014-11-25 (já fez). | Devolve `'2026-11-26'`, `'2026-12-26'`, `null` e `null`. |
| CT-6 | CA3 | unidade (Pest) | Nascido em 2014-12-10 e exceção `aprovada` com `vigente_ate` `null`. Outra com `vigente_ate` 2026-12-31. Outra com `vigente_ate` 2026-12-01. Outra só `solicitada`. | `null`, `null`, `'2026-12-10'` e `'2026-12-10'`. |
| CT-7 | CA3 | unidade (Pest) | Virada do dia: `agora` = 2026-11-27 02:00Z, que ainda é 26/11 em São Paulo. Nascido em 2014-12-26. | Com `America/Sao_Paulo`, devolve `'2026-12-26'`. Com `UTC`, a janela vai até 27/12 e também devolve. Com nascimento em 2014-12-27, só `UTC` devolve. |
| CT-8 | CA3 | unidade (Pest) | Nascido em 2016-02-29, `agora` = 2028-02-15. (Nascido em 29/02 + 12 anos sempre cai em ano bissexto até 2100, então o ramo 01/03 não é exercitável com datas reais; não teste.) | Devolve `'2028-02-29'`, sem exceção de data inválida. |
| CT-9 | CA4 | unidade (Pest) | `aviso12AnosDaIgreja` numa igreja com 4 participantes: dois dentro da janela, um `inativo`/`mesclado` dentro da janela e um fora. Também um participante de outra igreja dentro da janela. | Devolve só os dois ativos da igreja, ordenados por data. |
| CT-10 | CA3 | integração | Responsável com dois dependentes aprovados, um dentro da janela e outro fora. `Carbon::setTestNow('2026-11-26 12:00:00')`. | `GET /api/v1/me/participants` → 200, `data.*.faz_12_em` = data / `null`. Nenhum evento `participante.lido` é criado. |
| CT-11 | CA4 | integração | Parametrizado com `coordenadora` e `coordenadora_chefe` em `ebi:E` (`igrejaComEbi()`), com participante da igreja dentro da janela. `GET /api/v1/ebis/E/age-alerts?finalidade=aviso_12_anos`. | Nos dois papéis: 200 com `data[0].faz_12_em`. É criado um `participante.lido` com `escopo = "ebi:E"`, `motivo = "aviso_12_anos"` e `metadados_minimizados.origem_leitura = "aviso_12_anos"`. |
| CT-12 | CA5 (erro) | integração | A mesma coordenadora, sem `finalidade`. | 422 `finalidade_obrigatoria`, sem evento. |
| CT-13 `[COMPLEXO]` | CA5 (segurança) | integração | Coordenadora `ebi:E` consulta `ebis/OUTRO/age-alerts`. Auxiliar `ebi:E` consulta `ebis/E`. Coordenadora `ebi:E` consulta um id inexistente. `adm_regional` da regional que contém E consulta `ebis/E` (tem `excecao.ler`, mas não `excecao.decidir`). | 403 nos quatro, sem nome de participante no corpo e sem evento `participante.lido`. |
| CT-14 | CA5 | integração | `super_admin` consulta um EBI inexistente. | 404. |
| CT-15 | CA8 | integração | Responsável (`central`) faz `POST /participants` com `igreja_referencia_id` da igreja do `igrejaComEbi()`. Outra chamada sem igreja. | O escopo de `participante.cadastrado` é `"ebi:<ebi>"` e depois `null`. |
| CT-16 | CA8 | integração | Uma igreja sem EBI (`Igreja::factory()`) e um responsável que cadastra nela. | `"igreja:<id>"`. |
| CT-17 | CA8 | integração | Coordenadora `ebi:E` (de `igrejaComEbi()`): cadastro assistido, `GET /participants?...`, `GET /participants/{id}`, mesclar e desfazer, decidir vínculo, decidir exceção e listar vínculos (`vinculo.listado`). Os participantes, o pedido de vínculo e a exceção são montados com `participanteNaIgreja($igreja)`, para passar pela checagem de território (CA13). | Todos os eventos com `escopo = "ebi:E"`. |
| CT-18 | CA8 | integração | Responsável solicita vínculo e exceção de um participante da igreja de `igrejaComEbi()`. Repita com um participante sem igreja (`igreja_referencia_id = null`). | `vinculo.solicitado` e `excecao.solicitada` com `"ebi:<ebi>"`; no participante sem igreja, os dois com `escopo = null`. |
| CT-19 `[COMPLEXO]` | CA9 (segurança) | integração | `arvoreRelatorio()` e `arvoreRelatorio()` de novo: dois setores, igrejas A e B. Responsável cadastra um dependente na A, outro na B e outro sem igreja. `adm_setor` do setor de A faz `GET /api/v1/audit-events?recurso=participante`. (Não existe filtro `acao`; filtre o `data` por `acao === 'participante.cadastrado'` na asserção.) | Entre os `participante.cadastrado`, vê só o da A. O da B e o sem igreja não aparecem. `super_admin`, na mesma consulta, vê os três. |
| CT-20 | CA10 | integração | Auxiliar com troca `pendente` aberta por ela, e outra vaga sem troca. `GET /api/v1/me/schedules?inicio=...&fim=...`. | A vaga com troca traz `troca_id` = id da troca. A outra traz `troca_id: null`. |
| CT-21 | CA10 | integração | A troca foi `aprovada`/`recusada`/`cancelada`. | `troca_id: null`. |
| CT-22 | CA10 | unidade (vitest) | `minha({ status: 'troca_solicitada', troca_id: 'T1' })`. Clicar em "Desistir da troca" e confirmar. Também `status: 'troca_solicitada'` com `troca_id: null`. | `cancelarTroca('T1')` é chamado, aparece o toast "Pedido de troca cancelado." e a lista é recarregada (segundo `minhasEscalas`). Sem `troca_id`, o botão não aparece. |
| CT-23 | CA11 (erro) | unidade (vitest) | `cancelarTroca` responde `409` com `detail: 'Esta troca já foi decidida.'`. | O texto aparece no `Alert` de erro, e `minhasEscalas` é chamado de novo. |
| CT-24 | CA6 | integração | Sessão `encerrada` e 3 participantes elegíveis, um deles com presença `valida` já gravada. `POST .../contingency-attendance/batch`. | 200. `resumo = {criadas: 2, ja_existiam: 1, erros: 0}`, 3 presenças `valida` no banco, só 2 eventos `presenca.contingencia` novos, cada um com o motivo e `escopo = "ebi:<ebi>"`. |
| CT-25 | CA7 | integração | Lote com 1 elegível, 1 nascido em 2010-01-01 (inelegível), 1 ULID inexistente e 1 participante com `status = 'mesclado'`. | 200, e a linha 1 é `criada`. A linha 2 traz `erro: inelegivel` e `motivo_bloqueio` preenchido. As linhas 3 e 4 trazem `erro: not_found`. Há 1 presença gravada. |
| CT-26 | CA7 (erro) | integração | Sessão `aberta`, `prevista` e `cancelada`. | 409 `sessao_em_estado_invalido` e nenhuma presença gravada. |
| CT-27 | CA7 (erro) | integração | `participante_ids: []`, 101 ids, ids repetidos, `"abc"`, motivo `"ab"` e motivo de 256 caracteres. | Todos dão 422. Com 100 ids e motivo de 255, a validação passa. |
| CT-28 `[COMPLEXO]` | CA7 (segurança) | integração | Coordenadora de outro EBI. `auxiliar` do mesmo EBI. | 403 nos dois casos e nenhuma presença gravada. |
| CT-29 `[COMPLEXO]` | CA6 (concorrência) | integração (PostgreSQL) | Dois processos (`Suporte::correr`) lançam o mesmo lote de 5 participantes na mesma sessão. | Os dois saem `ok`, sem `erro:`. Há exatamente 5 presenças `valida` e exatamente 5 eventos `presenca.contingencia`. |
| CT-30 | CA12 (erro) | integração | Motivo de 256 caracteres na contingência individual, no estorno, no cancelamento e na correção de sessão (`ContingenciaTest`, `EstornoHistoricoTest` e `SessaoTest`) e `reason` de 256 em `POST /churches/{id}/transfers` (`ChurchTest`, com o `super_admin` do `beforeEach`). | 422. Com 255 caracteres, a chamada passa (a transferência dá 201), e `evento_auditoria.motivo` guarda os 255. |
| CT-31 | CA12 (erro) | integração | Exceção de agenda (`ExcecaoAgendaTest`) com motivo de 248 caracteres, de 247 e `"ab"`. | 422, 201 e 422. Com 247 caracteres e uma sessão prevista na data, o evento de cancelamento tem `motivo` de 255 caracteres (`"agenda: "` + 247). |
| CT-32 | CA6 | unidade (vitest) | Sessão `encerrada`. Buscar "Ana" e marcar, buscar "Bia" e marcar, informar o motivo e clicar em "Lançar 2 presenças". A resposta é `resumo {criadas:1, ja_existiam:1}`. | Há um único `api.POST` para `/sessions/{id}/contingency-attendance/batch`, com os 2 ids e o motivo. Aparece o toast "1 lançadas, 1 já existiam", e a lista de marcados é esvaziada. |
| CT-33 | CA7 (erro) | unidade (vitest) | Uma resposta com uma linha `erro: inelegivel`. Outro cenário: resposta 409. Outro: motivo com 2 caracteres. | O primeiro mostra "Não lançadas: ‹nome›". O 409 mostra o `detail`. Com 2 caracteres, o botão "Lançar" fica desabilitado. |
| CT-34 | CA1 | unidade (vitest) | `<Participantes escopo={{papel:'coordenadora',...}} />`. Buscar, clicar em "Corrigir", mudar o nome, preencher o motivo e salvar. | `api.PATCH` é chamado com `body = {nome, motivo}`, sem os campos não alterados. A linha mostra o nome novo e aparece o toast "Cadastro corrigido.". |
| CT-35 | CA2 (erro) | unidade (vitest) | Parametrizado (`it.each`): `api.PATCH` responde `409 participante_mesclado`, `422 nada_a_corrigir` e `422 igreja_fora_do_escopo`, cada um com um `detail` diferente. | Nos três, o diálogo continua aberto e mostra o `detail` da resposta. |
| CT-36 | CA2 | unidade (vitest) | `escopo.papel = 'auxiliar'`, `'adm_igreja'` e `null`. | Não há botão "Corrigir". |
| CT-37 `[COMPLEXO]` | CA13 (segurança) | integração | Dois `igrejaComEbi()`: A e B. Coordenadora em `ebi:<ebiA>`. Exceção `solicitada` de `participanteNaIgreja($igrejaB)`. `PATCH /api/v1/age-exceptions/{id}` com `decisao: aprovar`. Repita com um id de exceção inexistente. | 404 `not_found` com `detail` "Exceção não encontrada." nos dois. A exceção continua `solicitada`, `decidido_por` continua `null` e não há evento `excecao.decidida`. |
| CT-38 | CA13 | integração | A mesma coordenadora decide a exceção de `participanteNaIgreja($igrejaA)`. | 200, `status = aprovada` e um evento `excecao.decidida`. (O valor do `escopo` é conferido no CT-17, depois do T8.) |
| CT-39 `[COMPLEXO]` | CA13 (segurança) | integração | Participante sem igreja: (a) com presença `valida` numa sessão do EBI A; (b) sem presença nenhuma. Coordenadora de A decide cada exceção. `super_admin` decide a exceção de (b). | (a) 200; (b) 404 para a coordenadora e 200 para o `super_admin`. |
| CT-40 | CA8 (erro) | integração | `POST /participants` (responsável) e `POST /participants/assisted` (coordenadora) com `igreja_referencia_id` = `(string) Str::ulid()` que não existe, e com uma string de 300 caracteres. | 422 com erro em `igreja_referencia_id`, nenhum participante novo e nenhum `participante.cadastrado`. Com a igreja de `igrejaComEbi()`, 201. |
| CT-41 | CA8 | unidade (Pest) | `app(EscopoAuditoria::class)->daIgreja(...)` com: `null`, `''`, id inexistente (cadastro antigo), igreja sem EBI e igreja com EBI. | `null`, `null`, `null`, `"igreja:<id>"` e `"ebi:<ebi>"`. |
| CT-42 | CA12 | unidade (vitest) | `<PainelAgenda ebiId="E1" podeGerenciar />`, com `api.GET` mockado devolvendo agenda e exceções vazias. Clicar na aba "Exceções" com `userEvent.click`, preencher a data e digitar no campo "Motivo". | O input tem `maxLength = 247` e `minLength = 3`, e o rótulo contém "3 a 247 caracteres". Com "ab", o botão "Criar exceção" fica desabilitado; com "abc", fica habilitado. |
| CT-43 | CA12 | unidade (vitest) | `Presenca` em sessão `encerrada` com `papeis={['coordenadora']}`, depois de clicar em "Lançar lista em papel" (montagem do T16). | O campo "Motivo da contingência" tem `maxLength = 255`, e o rótulo diz "3 a 255 caracteres". |

## Dados e ambiente

- Use os helpers de `backend/tests/Pest.php`:
  - `igrejaComEbi()` cria uma igreja com EBI;
  - `arvoreRelatorio()` cria a árvore com setor, para o CT-19; chamá-la duas vezes cria dois setores;
  - `criarUsuarioComPapel(papel, tipo, id)`;
  - `participanteNaIgreja(Igreja $igreja, array $atributos = [])` (`Pest.php:121`), para pôr participante, vínculo e exceção no território da coordenadora (CT-17, CT-37 a CT-39).
- CT-39 (a): crie a sessão do EBI A e a presença `valida` com as factories de `backend/app/Domain/Sessoes/Database/Factories/` (`SessaoFactory` e `PresencaFactory`), copiando o corpo de `escopoPresencaEm` (`backend/tests/Feature/Participantes/MesclagemEscopoTest.php:38`). Não chame a função de lá: ela é declarada em outro arquivo de teste.
- CT-42: as abas usam Radix (`Tabs`); troque de aba com `userEvent.click` (`@testing-library/user-event`, já instalado), não com `fireEvent`.
- Cabeçalho de contexto: `X-Acting-Scope: papel=<papel>;escopo=<tipo>:<id>`, como em `ContingenciaTest.php:13`.
- Fixe o relógio com `Carbon::setTestNow(...)` em todo teste do aviso dos 12 anos. Nos testes de unidade de `aviso12Anos`, passe `agora` explicitamente.
- Concorrência: precisa do PostgreSQL do compose, banco `ebi_teste`, e de `TESTE_PGSQL=1`. Sem isso, `tests/Pest.php` pula a suíte.
- Front: mock de `../api/client`. Siga `Participantes.test.tsx` para `api.GET` e inclua `api.PATCH` e `api.POST` quando o teste precisar. Para `MinhasEscalas`, faça mock de `../api/escalas`, como o teste já faz.

## Fora da cobertura automática

O humano confere:

- O visual do card do Início e do aviso em "Meus dependentes" em uma tela de celular de 360 px.
- No portal, com os dados do `UsuariosTesteSeeder`:
  - lançar um lote de ~30 presenças em papel numa sessão encerrada, conferir que o tempo de resposta fica aceitável (até ~5 s) e que o histórico de presenças mostra todas;
  - desistir de uma troca pelo portal e conferir que a coordenadora não a vê mais como pendente;
  - entrar como `adm_setor` e conferir, em Auditoria, os eventos de cadastro e de vínculo do seu setor.
- O texto do aviso ao responsável, que é decisão de produto (CA3).
