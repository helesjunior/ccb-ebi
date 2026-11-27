# Plano de implementação

Total: 4P, 14M

Antes de cada rodada do backend, rode `docker compose exec app php artisan optimize:clear`. Os comandos estão em `04-plano-testes.md`. Os ids `CT-xx` também se referem aos casos de lá.

As tarefas T1 a T4 (limite de motivo e desistir da troca) não dependem das outras e podem ir num commit ou num PR antes.

## T1 — Limite de motivo no backend e no OpenAPI
- Arquivos:
  - em `backend/app/Http/Requests/Agenda/`: `ContingenciaPresencaRequest.php`, `EstornarPresencaRequest.php`, `CancelarSessaoRequest.php`, `CorrigirSessaoRequest.php` e `AgendaExcecaoRequest.php`;
  - `backend/app/Http/Requests/Organizacao/TransferenciaRequest.php:22`;
  - `backend/app/Domain/Sessoes/Services/GerenciadorSessao.php` (linhas 59 e 85);
  - `backend/app/Domain/Agenda/Services/GerenciadorAgenda.php:42`;
  - `docs/04-api/openapi.yaml` (linhas 673, 3576, 3932, 3942, 3993 e 3998);
  - testes em `backend/tests/Feature/Agenda/`: `ContingenciaTest.php` (66-74), `EstornoHistoricoTest.php` (71-73), `SessaoTest.php` e `ExcecaoAgendaTest.php`;
  - teste `backend/tests/Feature/Api/ChurchTest.php`.
- O que fazer:
  - aplique a tabela da seção 1 de `02-especificacao.md`, com o comentário `// evento_auditoria.motivo e varchar(255).` ao lado de cada regra;
  - nos testes, troque 501/500 por 256/255;
  - em `SessaoTest.php`, cubra cancelar e corrigir com 256 caracteres → 422;
  - em `ChurchTest.php`, inclua uma transferência com `reason` de 256 → 422 e outra de 255 → 201;
  - em `ExcecaoAgendaTest.php`, inclua 248 → 422, 247 → 201 e "ab" → 422 (CT-30, CT-31).
- Atende: CA12
- Pronto quando: `docker compose run --rm app php artisan test --filter="ContingenciaTest|EstornoHistoricoTest|ExcecaoAgendaTest|SessaoTest|ChurchTest"` passa.
- Estimativa: M

## T2 — Limite de motivo no portal
- Arquivos:
  - `frontend/src/pages/Presenca.tsx` (linhas 66, 67, 281, 282, 406 e 407);
  - `frontend/src/pages/Sessoes.tsx` (linhas 57 e 314);
  - `frontend/src/components/agenda/PainelAgenda.tsx` (linhas 147, 259 e 260);
  - criar `frontend/src/components/agenda/PainelAgenda.test.tsx`;
  - `frontend/src/pages/Presenca.test.tsx`.
- O que fazer:
  - troque 500 por 255 nos limites e nos textos;
  - em `PainelAgenda`, siga a seção 1 da especificação: `minLength={3} maxLength={247}`, o rótulo "Motivo (obrigatório, 3 a 247 caracteres)" e a condição `motivo.trim().length < 3` no `disabled` e em `criarExcecao`;
  - escreva os testes CT-42 e CT-43.
- Atende: CA12
- Pronto quando: `npm run test -- PainelAgenda Presenca` e `npm run build` passam em `frontend/`.
- Estimativa: M

## T3 — `troca_id` em `GET /me/schedules`
- Arquivos:
  - `backend/app/Domain/Escalas/Services/ConsultaEscala.php` (204-273);
  - `backend/app/Domain/Escalas/Contracts/ConsultarEscala.php:60`;
  - `docs/04-api/openapi.yaml` (`MinhaEscala`, linha 3835);
  - `backend/tests/Feature/Escalas/TrocaEscalaTest.php`.
- O que fazer:
  - implemente o mapa `membro_id => troca_id` da seção 2 da especificação;
  - acrescente `troca_id` ao `@return` e ao schema;
  - escreva os testes CT-20 e CT-21.
- Atende: CA10
- Pronto quando: `docker compose run --rm app php artisan test --filter=TrocaEscalaTest` passa.
- Estimativa: P

## T4 — Botão "Desistir da troca"
- Arquivos: `frontend/src/pages/MinhasEscalas.tsx` (bloco 280-282) e `frontend/src/pages/MinhasEscalas.test.tsx`.
- O que fazer:
  - rode `npm run gen:api`;
  - importe `cancelarTroca` (`frontend/src/api/escalas.ts:309`) e `import { ConfirmarAcao } from '../components/ConfirmarAcao'`, como em `Dispositivos.tsx:8`;
  - implemente `desistir()` e o botão da seção 2;
  - no helper `minha()` do teste, inclua `troca_id: null` por padrão;
  - escreva os testes CT-22 e CT-23.
- Atende: CA10, CA11
- Pronto quando: `npm run test -- MinhasEscalas` passa.
- Estimativa: M

## T5 — Helper `EscopoAuditoria` e assinaturas dos serviços
- Arquivos:
  - criar `backend/app/Support/Participantes/EscopoAuditoria.php`;
  - em `backend/app/Domain/Participantes/Contracts/`: `GerirParticipante.php`, `GerirMesclagem.php`, `GerirVinculoResponsavel.php` e `GerirExcecaoEtaria.php`;
  - em `backend/app/Domain/Participantes/Services/`: `GerenciadorParticipante.php`, `GerenciadorMesclagem.php`, `GerenciadorVinculoResponsavel.php` e `GerenciadorExcecao.php`.
- O que fazer:
  - crie o helper exatamente como na seção 3 da especificação, com `ConsultarHierarquia` e `ConsultarOrganizacao`;
  - em cada método da tabela "Serviços", inclua `?string $escopo = null` como último parâmetro, no contrato e no serviço;
  - repasse o valor até a gravação, trocando `'escopo' => null` por `'escopo' => $escopo`;
  - não mude nada nos controllers ainda.
- Atende: CA8 (base)
- Pronto quando:
  - `docker compose run --rm app composer test` continua verde, porque o parâmetro novo é opcional;
  - `docker compose run --rm app composer phpstan` passa;
  - `docker compose run --rm app php artisan test --filter=ModularBoundariesTest` passa.
- Estimativa: M

## T6 — Validar a igreja no cadastro
- Arquivos:
  - `backend/app/Http/Requests/Participantes/CadastrarParticipanteRequest.php:23`;
  - `backend/app/Http/Requests/Participantes/CadastrarAssistidoRequest.php:23`;
  - `docs/04-api/openapi.yaml` (descrição do `422` em `POST /participants` e em `POST /participants/assisted`);
  - testes `backend/tests/Feature/Participantes/CadastroParticipanteTest.php` (linha 30) e `CadastroAssistidoTest.php`.
- O que fazer:
  - troque a regra por `['nullable', 'string', 'exists:igrejas,id']`;
  - em `CadastroParticipanteTest.php:30`, use `igrejaComEbi()['igreja']->id`;
  - escreva o teste CT-40.
- Atende: CA8
- Pronto quando: `docker compose run --rm app php artisan test --filter="CadastroParticipanteTest|CadastroAssistidoTest"` passa.
- Estimativa: P

## T7 — Território na decisão de exceção
- Arquivos:
  - `backend/app/Domain/Participantes/Contracts/GerirExcecaoEtaria.php`;
  - `backend/app/Domain/Participantes/Services/GerenciadorExcecao.php`;
  - `backend/app/Http/Controllers/Api/V1/AgeExceptionController.php` (`update` e `contexto()`);
  - `docs/04-api/openapi.yaml` (`404` de `PATCH /age-exceptions/{id}`);
  - `backend/tests/Feature/Participantes/ExcecaoEtariaTest.php`.
- O que fazer:
  - siga a seção 3a da especificação: `encontrar`, a checagem com `ligado` e o `404`;
  - mude o tipo do parâmetro de `contexto()` para `Illuminate\Http\Request`;
  - ajuste os testes das linhas 35-55 para montar a exceção no território da coordenadora;
  - escreva os testes CT-37 a CT-39.
- Atende: CA13
- Pronto quando: `docker compose run --rm app php artisan test --filter=ExcecaoEtariaTest` passa.
- Estimativa: M

## T8 — Controllers gravam o escopo
- Arquivos:
  - em `backend/app/Http/Controllers/Api/V1/`: `ParticipantController.php` (`store`, `storeAssisted` e `auditarLeitura`), `ParticipantMergeController.php` (`store` e `undo`), `GuardianController.php` (`store` e `pedidos`), `GuardianRequestController.php` (`update`) e `AgeExceptionController.php` (`store` e `update`);
  - teste novo `backend/tests/Feature/Participantes/EscopoAuditoriaParticipanteTest.php`.
- O que fazer:
  - injete `EscopoAuditoria` pelo construtor;
  - leia o contexto com `$request->attributes->get('contexto_atuacao')`, como em `ParticipantController::update`;
  - calcule o escopo conforme a tabela "Escopo por evento" e passe-o ao serviço;
  - escreva os testes CT-15 a CT-19 e CT-41.
- Atende: CA8, CA9
- Pronto quando: `docker compose run --rm app php artisan test --filter="EscopoAuditoriaParticipanteTest|Mesclagem|Vinculo|ExcecaoEtaria|Cadastro"` passa.
- Estimativa: M

## T9 — Cálculo do aviso dos 12 anos
- Arquivos: `backend/app/Domain/Participantes/Contracts/AvaliarElegibilidade.php`, `backend/app/Domain/Participantes/Services/AvaliadorElegibilidade.php` e `backend/tests/Feature/Participantes/AvaliadorElegibilidadeTest.php`.
- O que fazer:
  - implemente `aviso12Anos` e `aviso12AnosDaIgreja` como na seção 4 da especificação, reaproveitando `bissexto()` e o `excecaoVigente` privado;
  - deixe o comentário `ponytail:` do N+1;
  - escreva os testes CT-5 a CT-9.
- Atende: CA3, CA4
- Pronto quando: `docker compose run --rm app php artisan test --filter=AvaliadorElegibilidadeTest` passa.
- Estimativa: M

## T10 — `faz_12_em` em `GET /me/participants`
- Arquivos:
  - `backend/app/Http/Controllers/Api/V1/GuardianController.php` (`meusDependentes`, 57-64);
  - `docs/04-api/openapi.yaml` (schema `Dependente`; `/me/participants`, por volta da linha 2602);
  - teste novo `backend/tests/Feature/Participantes/AvisoDozeAnosTest.php`.
- O que fazer:
  - injete `AvaliarElegibilidade` e `ConsultarOrganizacao`;
  - busque os fusos com uma única chamada a `fusosDasIgrejas` e use `America/Sao_Paulo` como padrão;
  - monte cada item com `array_merge((new ParticipanteResource($p))->resolve($request), ['faz_12_em' => ...])`;
  - escreva o teste CT-10.
- Atende: CA3
- Pronto quando: `docker compose run --rm app php artisan test --filter=AvisoDozeAnosTest` passa.
- Estimativa: M

## T11 — Rota `GET /ebis/{id}/age-alerts`
- Arquivos:
  - `backend/app/Http/Controllers/Api/V1/AgeExceptionController.php` (método `alertas`);
  - `backend/routes/api.php` (depois da linha 215);
  - `docs/04-api/openapi.yaml` (rota e schema `AlertaDozeAnos`);
  - `docs/04-api/endpoints.md` (uma linha depois da 82);
  - `backend/tests/Feature/Participantes/AvisoDozeAnosTest.php`.
- O que fazer:
  - implemente os 6 passos da seção 4, com o fuso padrão quando `timezone` vier vazio;
  - injete `ConsultarEbi` e `AvaliarElegibilidade`;
  - escreva os testes CT-11 a CT-14.
- Atende: CA4, CA5
- Pronto quando: `docker compose run --rm app php artisan test --filter=AvisoDozeAnosTest` passa e `python scripts/check-endpoints.py` não acusa diferença.
- Estimativa: M

## T12 — Aviso em "Meus dependentes"
- Arquivos: `frontend/src/pages/Dependentes.tsx` e o teste novo `frontend/src/pages/Dependentes.test.tsx`.
- O que fazer:
  - rode `npm run gen:api`;
  - acima da tabela (82-106), mostre um `Alert` por dependente com `faz_12_em`, com o texto do CA3 e a data em `formatarData`;
  - escreva os testes CT-1 e CT-2, mockando `api.GET` como em `Participantes.test.tsx`.
- Atende: CA3
- Pronto quando: `npm run test -- Dependentes` passa.
- Estimativa: P

## T13 — Bloco "Fazem 12 anos" no Início
- Arquivos: `frontend/src/pages/Inicio.tsx`, `frontend/src/lib/formatos.ts:189` (`SUGESTOES_FINALIDADE`) e o teste novo `frontend/src/pages/Inicio.test.tsx`.
- O que fazer:
  - implemente o card da seção 4, com `useEffect` dependente de `escopo`;
  - use `cabecalhos()` de `frontend/src/api/sessao.ts`;
  - escreva os testes CT-3 e CT-4.
- Atende: CA4, CA5
- Pronto quando: `npm run test -- Inicio` passa.
- Estimativa: M

## T14 — Contingência em lote: serviço, rota e contrato
- Arquivos:
  - `backend/app/Domain/Sessoes/Contracts/RegistrarPresenca.php`;
  - `backend/app/Domain/Sessoes/Services/RegistradorPresenca.php`;
  - criar `backend/app/Http/Requests/Agenda/ContingenciaLotePresencaRequest.php`;
  - `backend/app/Http/Controllers/Api/V1/PresencaController.php` (método `contingenciaLote`);
  - `backend/routes/api.php` (depois da linha 316);
  - `docs/04-api/openapi.yaml` (rota depois da 2167 e schemas `PresencaContingenciaLoteInput` e `ResultadoContingenciaLote`);
  - `docs/04-api/endpoints.md` (uma linha depois da 120);
  - teste novo `backend/tests/Feature/Agenda/ContingenciaLoteTest.php`, com o mesmo `beforeEach` de `ContingenciaTest.php`.
- O que fazer:
  - implemente `registrarContingenciaLote` como na seção 5, reutilizando `registrarContingencia` linha a linha;
  - deixe o `409` da sessão antes do laço e a checagem de `not_found` antes de cada chamada;
  - crie o Request, o método do controller (serializando `presenca` com `PresencaResource`) e a rota;
  - escreva os testes CT-24 a CT-28.
- Atende: CA6, CA7
- Pronto quando: `docker compose run --rm app php artisan test --filter=ContingenciaLoteTest` passa, `docker compose run --rm app composer phpstan` passa e `python scripts/check-endpoints.py` não acusa diferença.
- Estimativa: M. A tarefa junta o serviço e o HTTP (revisão da rodada 1), mas a decisão de desenho é uma só e já está na especificação.

## T15 — Concorrência do lote
- Arquivos:
  - `backend/tests/Concorrencia/bin/executar.php`;
  - `backend/tests/Concorrencia/Suporte.php`;
  - teste novo `backend/tests/Concorrencia/PresencaConcorrenciaTest.php`.
- O que fazer:
  - no `match` de `executar.php`, inclua a ação `'contingencia' => $app->make(RegistrarPresenca::class)->registrarContingenciaLote($id, explode(',', $alvo), 'lista em papel', $usuarioId)`, com `id` = sessão e `alvo` = ids separados por vírgula;
  - atualize o comentário de uso no topo;
  - em `Suporte`, crie `cenarioContingencia()`. Ela faz o `migrate:fresh` e o `PapelSeeder` como `cenario()`, cria a igreja, o EBI, uma sessão `encerrada`, uma coordenadora e 5 participantes nascidos em 2016-01-01, e devolve `escopo`, `sessao`, `dona` e `participantes`;
  - escreva o teste CT-29.
- Atende: CA6
- Pronto quando: `docker compose run --rm -e TESTE_PGSQL=1 app vendor/bin/pest --testsuite=Concorrencia` passa.
- Estimativa: M

## T16 — Multisseleção na contingência
- Arquivos: `frontend/src/pages/Presenca.tsx` e `frontend/src/pages/Presenca.test.tsx`.
- O que fazer:
  - rode `npm run gen:api`;
  - implemente `marcados`, a lista de marcados, a barra fixa e o `confirmar()` do lote, como na seção 5;
  - mantenha o modo normal (sessão `aberta`) sem mudança.
- Montagem do teste: hoje `Presenca.test.tsx` não tem nenhum caso de contingência, então os casos novos partem do zero.
  - Troque o `POST` fixo do mock (linha 11) por `const post = vi.fn()` e `POST: (...args: unknown[]) => post(...args)`.
  - Acrescente `vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))` e importe `toast` para conferir as chamadas.
  - Em `responder()`, acrescente a rota `'/sessions/{id}/participants'`, que devolve os candidatos conforme `opcoes.params.query.busca`, por exemplo:
    - `'Ana'` → `{ data: { data: [{ id: 'p-ana', nome: 'Ana Clara', matricula: 'M1', elegivel: true }] } }`;
    - `'Bia'` → `{ data: { data: [{ id: 'p-bia', nome: 'Bia Lima', matricula: 'M2', elegivel: true }] } }`.
  - Use `responder('encerrada', escala())` e renderize com `papeis={['coordenadora']}`.
  - Passos:
    1. Clique em "Lançar lista em papel".
    2. Preencha "Motivo da contingência".
    3. Digite "Ana" em "Nome ou matrícula". A finalidade padrão já é `registro_presenca`, e a busca tem debounce de 300 ms, então use `findByRole('button', { name: /Ana Clara/ })`.
    4. Clique no botão; depois troque a busca para "Bia" e clique no outro.
    5. Clique em "Lançar 2 presença(s)".
  - A resposta do lote é `post.mockResolvedValue({ data: { data: [...], resumo: { criadas: 1, ja_existiam: 1, erros: 0 } } })`.
  - Escreva os testes CT-32 e CT-33.
- Atende: CA6, CA7
- Pronto quando: `npm run test -- Presenca` passa.
- Estimativa: M

## T17 — Tela de correção de cadastro
- Arquivos:
  - criar `frontend/src/components/CorrigirParticipante.tsx`;
  - `frontend/src/pages/Participantes.tsx`;
  - `frontend/src/App.tsx:151`;
  - `frontend/src/pages/Participantes.test.tsx`.
- O que fazer:
  - siga a seção 6 da especificação;
  - no teste, inclua o mock de `api.PATCH` e renderize `<Participantes escopo={...} />`;
  - escreva os testes CT-34 a CT-36.
- Atende: CA1, CA2
- Pronto quando: `npm run test -- Participantes`, `npm run lint` e `npm run build` passam.
- Estimativa: M

## T18 — CHANGELOG e verificação final
- Arquivos: `docs/CHANGELOG.md`.
- O que fazer:
  - crie a entrada **2.22** no topo, com os itens e as quebras de contrato da seção 7 da especificação;
  - inclua a correção de território em `PATCH /age-exceptions/{id}` como item entregue, e não como dívida;
  - registre o que ficou fora (seção "Fora do escopo" de `01-historia.md`).
- Atende: todos (registro)
- Pronto quando: todos os comandos de "Como rodar" em `04-plano-testes.md` passam.
- Estimativa: P
