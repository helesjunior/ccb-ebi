# Plano de implementação

Total: 3P, 5M (8 tarefas).

Branch: `feat/dividas-etapa-6` (base `main`). Os comandos de backend rodam no contêiner (veja `04-plano-testes.md`). Antes de cada rodada de teste, rode `docker compose exec app php artisan optimize:clear`: a matriz é `const` e fica no Opcache.

Ordem obrigatória do projeto: **OpenAPI → catálogo → código** (design da Etapa 6, §6.10).

## T1 — Contrato da API (OpenAPI e catálogo)
- Arquivos: `docs/04-api/openapi.yaml`, `docs/04-api/endpoints.md`, `frontend/src/api/schema.d.ts` (gerado)
- O que fazer:
  1. Em `/participants/{id}` (linha ~2466), acrescentar `patch:` conforme a seção "Contratos" da especificação. Criar o schema `CorrecaoParticipanteInput` com `motivo` de `maxLength: 255`.
  2. Em `GET /sessions/{id}/attendance` (linha ~559) e `GET /participants/{id}/attendance` (linha ~2490), acrescentar:
     - `- $ref: '#/components/parameters/FinalidadeLeitura'`;
     - a resposta `'422'`;
     - a `description` citando a auditoria `presenca.lida`.

     Conferir se o parâmetro `FinalidadeLeitura` (linha ~2968) tem `maxLength: 255`. Se não tiver, acrescentar.
  3. Em `docs/04-api/endpoints.md`, inserir depois da linha 64: `| PATCH | \`/participants/{id}\` | Corrigir cadastro (CA-27, motivo obrigatório) *novo* (\`no OpenAPI\`) | RF-02 |`.
  4. Rodar `npm run gen:api` em `frontend/`.
- **Aviso:** depois do `gen:api`, a `finalidade` vira obrigatória no tipo das duas rotas, e o `npm run build` do frontend **falha até a T6**. Isso é esperado. Não ajuste o portal nesta tarefa.
- Atende: CA6, CA7, CA8, CA9 (contrato)
- Pronto quando:
  - `python scripts/check-endpoints.py` sai com código 0;
  - `frontend/src/api/schema.d.ts` tem `patch` em `"/participants/{id}"` e `finalidade` em `query` nas duas rotas de presença.
- Estimativa: M

## T2 — Contrato `ConsultarPresenca::ebisComPresencaValida`
- Arquivos: `backend/app/Domain/Sessoes/Contracts/ConsultarPresenca.php`, `backend/app/Domain/Sessoes/Services/ConsultaPresenca.php`, `backend/tests/Feature/Agenda/PresencaTest.php`
- O que fazer:
  1. Declarar o método no contrato.
  2. Implementá-lo como na especificação, seção 1.
  3. Acrescentar o caso T1 do plano de testes no fim de `PresencaTest.php`.
- Atende: CA1, CA4 (base)
- Pronto quando: `docker compose run --rm app php artisan test --filter=PresencaTest` passa.
- Estimativa: P

## T3 — Regra "ligado" em `AutorizacaoParticipante` e na mesclagem
- Arquivos: `backend/app/Support/Participantes/AutorizacaoParticipante.php`, `backend/app/Http/Controllers/Api/V1/ParticipantMergeController.php`, `backend/app/Domain/Participantes/Services/GerenciadorMesclagem.php` (só o comentário), `backend/tests/Feature/Participantes/MesclagemEscopoTest.php` (novo)
- O que fazer:
  1. Em `AutorizacaoParticipante`:
     - injetar `ConsultarPresenca`;
     - extrair `cobreIgreja` do corpo atual de `cobreTerritorio` e fazer `cobreTerritorio` delegar a ele;
     - criar `ligado`, seguindo os passos 1 a 4 da especificação, seção 1 (`super_admin` primeiro; com igreja, só `cobreIgreja`; sem igreja, presença válida);
     - criar `exigirLigados`.
  2. Em `ParticipantMergeController`:
     - `store` e `undo` passam a usar `exigirLigados`;
     - o `undo` inclui o duplicado do snapshot quando ele existir;
     - remover `exigirEscopo` e atualizar os comentários.
  3. Criar `MesclagemEscopoTest.php` com os casos M1 a M12, seguindo as regras de "Dados e ambiente" do plano de testes (`forgetGuards`, chave de idempotência nova, mesclagem pelo serviço em M6, M7, M9 e M10).
- Atende: CA1, CA2, CA3, CA4, CA5, CA12, CA13 (mesclagem)
- Pronto quando: `docker compose run --rm app php artisan test --filter="MesclagemEscopoTest|MesclagemTest|AutorizacaoParticipanteTest"` passa sem editar `MesclagemTest` nem `AutorizacaoParticipanteTest`.
- Estimativa: M

## T4 — `PATCH /participants/{id}` (correção)
- Arquivos: `backend/app/Http/Requests/Participantes/CorrigirParticipanteRequest.php` (novo), `backend/app/Http/Controllers/Api/V1/ParticipantController.php`, `backend/app/Domain/Participantes/Contracts/GerirParticipante.php`, `backend/app/Domain/Participantes/Services/GerenciadorParticipante.php`, `backend/routes/api.php`, `backend/tests/Feature/Participantes/CorrecaoParticipanteTest.php` (novo)
- O que fazer: seguir a especificação, seção 2.
  - A rota fica no grupo `IdempotenciaMiddleware`, sob `can:participante.corrigir`.
  - `update` responde nesta ordem: corpo inválido (o `FormRequest`, antes do controller) → 404 → `exigirLigados` (403) → filtro dos campos que mudaram (`nada_a_corrigir`) → `igreja_fora_do_escopo` → serviço (409 ou 200).
  - O serviço ganha os parâmetros `$motivo` e `$escopo`, o 409 para cadastro `mesclado` e a transação, e passa a auditar `motivo`, `escopo` e os campos aplicados.
  - Com `snapshot_mesclagem` pendente no principal, os campos aplicados também vão para `snapshot['principal']`.
  - Casos de teste: C1 a C11.
- Atende: CA6, CA7, CA12, CA13, CA14 (correção)
- Pronto quando: `docker compose run --rm app php artisan test --filter=CorrecaoParticipanteTest` passa.
- Estimativa: M

## T5 — Finalidade obrigatória nas leituras de presença
- Arquivos: `backend/app/Support/FinalidadeLeitura.php` (novo), `backend/app/Http/Controllers/Api/V1/PresencaController.php`, `backend/app/Http/Controllers/Api/V1/ParticipantController.php`, `backend/app/Http/Controllers/Api/V1/HistoricoPresencaController.php`, `backend/tests/Feature/Agenda/PresencaTest.php`, `backend/tests/Feature/Agenda/EstornoHistoricoTest.php`, `backend/tests/Feature/Agenda/FinalidadePresencaTest.php` (novo)
- O que fazer:
  1. Criar `FinalidadeLeitura::exigir(Request): string`, com a resposta `finalidade_obrigatoria` e o limite de 255 (`finalidade_invalida`). Trocar os métodos privados `finalidade()` dos dois controllers pelo helper e apagá-los.
  2. Em `PresencaController::index` e `HistoricoPresencaController::operador`, exigir a finalidade **depois** da autorização e auditar `presenca.lida` (especificação, seção 3).
  3. Acrescentar a finalidade às chamadas `GET` de sucesso das duas rotas nos testes existentes:
     - `PresencaTest.php`, linha 125: `?finalidade=teste`.
     - `EstornoHistoricoTest.php`, linhas 81 e 93: `?finalidade=teste`.
     - `EstornoHistoricoTest.php`, linha 161: `?finalidade=teste`.
     - `EstornoHistoricoTest.php`, linhas **171–185**: ponha `?finalidade=teste` na própria `$url` (linha 170) e troque o `?` das concatenações `$url.'?de=...'` e `$url.'?ate=...'` por `&`, inclusive nas linhas 184 e 185, que precisam continuar respondendo `422`.

     As chamadas que esperam 403 ou 404 (linhas 89, 106, 146 e 158) continuam sem finalidade e mantêm o status.
  4. Criar `FinalidadePresencaTest.php` com os casos F1 a F7.
- Atende: CA8, CA9, CA10
- Pronto quando: `docker compose run --rm app php artisan test --filter="FinalidadePresencaTest|PresencaTest|EstornoHistoricoTest|FinalidadeLeituraTest"` passa.
- Estimativa: M

## T6 — Portal envia a finalidade fixa
- Arquivos: `frontend/src/pages/Presenca.tsx`, `frontend/src/components/HistoricoPresencas.tsx`, `frontend/src/pages/Presenca.test.tsx`, `frontend/src/components/HistoricoPresencas.test.tsx` (novo)
- O que fazer:
  1. Em `Presenca.tsx` (linha 79), a chamada `/sessions/{id}/attendance` passa a usar `query: { finalidade: 'registro_presenca' }`.
     - Monte os parâmetros dela separados de `options`, porque `/sessions/{id}` não aceita `query`.
     - Acima de `carregar()`, deixe o comentário: `// ponytail: cada recarga grava presenca.lida; se a cadeia de auditoria disputar com as auxiliares, anexar a presença do POST à lista em vez de recarregar.`
  2. Em `HistoricoPresencas.tsx` (linha 48), o ramo `operador` recebe `query: { finalidade: 'consulta_historico' }`.
  3. Acrescentar o caso P1 em `Presenca.test.tsx`.
  4. Criar `HistoricoPresencas.test.tsx` com o caso P2, seguindo o padrão de mock de `api` de `Presenca.test.tsx` (linhas 4–13).
- Atende: CA11
- Pronto quando: em `frontend/`, `npm run test`, `npm run lint` e `npm run build` passam. O build volta a passar aqui.
- Estimativa: M

## T7 — Documentação
- Arquivos: `docs/05-seguranca-e-privacidade/matriz-de-autorizacao.md`, `docs/06-arquitetura/decisoes/ADR-0027-escopo-de-mesclagem.md`, `docs/01-requisitos/criterios-de-aceite.md`, `docs/CHANGELOG.md`
- O que fazer: aplicar as edições da seção "Documentação" da especificação.
- Atende: todos (registro)
- Pronto quando:
  - **matriz:**
    - a linha 17 descreve "ligado" com a restrição "sem igreja e com presença válida" e "chefe: basta um";
    - a tabela de operações tem `participante.mesclar` e `participante.corrigir`;
    - a linha `presenca.ler` cita `presenca.lida`;
    - a nota da linha 68 lista as rotas que já aplicam a finalidade;
  - **ADR-0027:**
    - tem a seção "Implementação (2.21)", com a definição de "ligado";
    - diz que a presença só liga cadastro sem igreja e explica o motivo;
    - traz a regra "coordenadora: os dois; chefe: basta um";
    - registra que o desfazer confere os dois cadastros;
    - registra os riscos aceitos: a chefe alcança qualquer cadastro como duplicado, e quem desfaz quando o principal herda outra igreja é a chefe dessa igreja;
  - **criterios-de-aceite.md, linha do CA-27:** passa a dizer "coordenadora não mescla nem corrige participante que não esteja ligado a EBI onde atua (igreja de referência no EBI; sem igreja, presença válida no EBI); a coordenadora chefe mescla quando ao menos um dos cadastros está ligado ao EBI dela";
  - **CHANGELOG:**
    - tem a versão 2.21, citando `PATCH /participants/{id}` e `presenca.lida`;
    - lista as cinco dívidas restantes.
- Estimativa: P

## T8 — Gates completos
- Arquivos: nenhum novo (só as correções que os gates apontarem)
- O que fazer: rodar a suíte inteira, o Pint, o PHPStan, o teste de arquitetura e os gates do frontend, e corrigir o que falhar.
- Atende: todos
- Pronto quando: estes comandos passam:
  - `docker compose run --rm app composer test`;
  - `docker compose run --rm app composer pint -- --test`;
  - `docker compose run --rm app composer phpstan`;
  - `python scripts/check-endpoints.py`;
  - em `frontend/`: `npm run lint`, `npm run build` e `npm run test`.
- Estimativa: P
