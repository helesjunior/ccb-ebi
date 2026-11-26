# Plano de testes

## Como rodar

Backend (o PHP roda sempre no contêiner; veja `README.md`):

```bash
docker compose exec app php artisan optimize:clear     # antes de cada rodada (a matriz fica no Opcache)
docker compose run --rm app composer test               # suíte inteira (Pest)
docker compose run --rm app php artisan test --filter=MesclagemEscopoTest
docker compose run --rm app composer pint -- --test     # formatação
docker compose run --rm app composer phpstan            # análise estática
python scripts/check-endpoints.py                       # catálogo × OpenAPI
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
| `backend/tests/Feature/Participantes/MesclagemEscopoTest.php` | novo | M |
| `backend/tests/Feature/Participantes/CorrecaoParticipanteTest.php` | novo | C |
| `backend/tests/Feature/Agenda/FinalidadePresencaTest.php` | novo | F |
| `frontend/src/components/HistoricoPresencas.test.tsx` | novo | P2 |
| `backend/tests/Feature/Agenda/PresencaTest.php` | existente, ganha caso | T1 |
| `frontend/src/pages/Presenca.test.tsx` | existente, ganha caso | P1 |

Cenário base dos casos M e C:

- EBI A, da igreja A, e EBI B, da igreja B;
- a coordenadora de A e a chefe de A atuam em `ebi:A`;
- "sem igreja" significa `igreja_referencia_id = null`.

| ID | Critério | Nível | Cenário | Resultado esperado |
|---|---|---|---|---|
| T1 | CA1, CA4 | integração | O participante tem duas presenças válidas em sessões diferentes de A e uma presença estornada em B. Chamar `ebisComPresencaValida` | `[A]` (um elemento só) |
| M1 [COMPLEXO] | CA1 | integração | Principal sem igreja e com presença válida em A; duplicado da igreja A. A coordenadora de A mescla | `200`; o duplicado fica `mesclado`; evento `participante.mesclado` |
| M2 [COMPLEXO] | CA2 | integração | Principal da igreja A; duplicado da igreja B, sem presença. A coordenadora de A mescla | `403 acesso_negado`; os dois continuam `ativo` |
| M3 [COMPLEXO] | CA3 | integração | O par do M2. A chefe de A mescla | `200`; o duplicado fica `mesclado` |
| M4 [COMPLEXO] | CA3 | integração | Os dois cadastros da igreja B, sem presença. A chefe de A mescla | `403`; nada muda |
| M5 [COMPLEXO] | CA4 | integração | Duplicado sem igreja, cuja única presença em A está `estornada`; principal da igreja A. A coordenadora de A mescla | `403` |
| M5b [COMPLEXO] | CA12 | integração | Duplicado da igreja B, **com presença válida em A**; principal da igreja A. A coordenadora de A mescla | `403`; nada muda |
| M6 [COMPLEXO] | CA5 | integração | O par do M2, mesclado **pelo serviço** (`app(GerirMesclagem::class)->mesclar(...)`). A coordenadora de A desfaz | `403`; o duplicado continua `mesclado` |
| M7 [COMPLEXO] | CA5 | integração | O mesmo estado do M6. A chefe de A desfaz | `200`; o duplicado volta a `ativo`; evento `participante.mesclagem_desfeita` |
| M8 [COMPLEXO] | CA2 | integração | A coordenadora de B tenta mesclar o par do M1 (fronteira horizontal) | `403` |
| M9 [COMPLEXO] | CA5 | integração | Os dois cadastros da igreja B, mesclados pelo serviço. A chefe de A desfaz | `403`; o duplicado continua `mesclado` |
| M10 [COMPLEXO] | CA5 | integração | Principal da igreja A; duplicado **sem igreja, com presença válida em A** (a ligação do duplicado é só por presença), mesclados pelo serviço. A coordenadora de A desfaz | `200`; o duplicado volta a `ativo` |
| M11 [COMPLEXO] | CA5 | integração | Principal sem igreja e com presença válida em A; duplicado da igreja B. A chefe de A mescla (`200`; o principal herda a igreja B). Depois a chefe de A desfaz; então a chefe de B desfaz | chefe de A: `403`, nada muda; chefe de B: `200`, o principal volta a não ter igreja |
| M12 [COMPLEXO] | CA13 | integração | Dois cadastros sem igreja e sem presença. O `super_admin` mescla | `200`; o duplicado fica `mesclado` |
| C1 | CA6 | integração | Participante sem igreja, com presença válida em A. A coordenadora de A envia `PATCH {nome: <novo>, nome_social: <atual>, motivo}` | `200` com o nome novo; evento `participante.corrigido` com `motivo`, `campos = ['nome']` (o campo igual fica fora), `antes.nome` antigo e `escopo = ebi:A` |
| C2 | CA6 | integração | A chefe de A corrige um participante da igreja A | `200` |
| C3 [COMPLEXO] | CA7 | integração | A coordenadora de A corrige um participante da igreja B, sem presença | `403`; nome inalterado; nenhum evento |
| C4 | CA7 | integração | Quatro `PATCH`, um por situação: sem `motivo`; `motivo` com 256 caracteres; `data_nascimento` futura; `igreja_referencia_id` inexistente | `422` em todos; nada alterado |
| C4b | CA7 | integração | Dois `PATCH`: um só com `{motivo}`; outro com `{nome: <nome atual>, motivo}` | `422`, `code = nada_a_corrigir`, nos dois; nenhum evento |
| C5 [COMPLEXO] | CA7 | integração | A coordenadora de A troca a igreja de referência de um participante da igreja A para B | `422 igreja_fora_do_escopo`; igreja inalterada; nenhum `participante.corrigido` |
| C6 | CA7 | integração | Participante `mesclado` da igreja A | `409 participante_mesclado`; nenhum `participante.corrigido` |
| C7 [COMPLEXO] | CA7 | integração | Três atores tentam corrigir um participante da igreja A: o auxiliar de A, o `adm_igreja` de A e um responsável com vínculo aprovado (sem `X-Acting-Scope`) | `403` nos três |
| C8 | CA7 | integração | Participante inexistente, com corpo válido | `404` |
| C9 [COMPLEXO] | CA12 | integração | Participante da igreja B com presença válida em A. A coordenadora de A envia `PATCH {nome, motivo}` e depois `PATCH {igreja_referencia_id: null, motivo}` | `403` nos dois; cadastro inalterado |
| C10 [COMPLEXO] | CA13 | integração | Participante sem igreja e sem presença. O `super_admin` corrige (escopo `central`) | `200`; evento com `escopo = central:<id>` |
| C11 [COMPLEXO] | CA14 | integração | Dois cadastros da igreja A, mesclados pelo serviço. A coordenadora de A corrige o `nome` do principal pelo `PATCH`; depois desfaz a mesclagem | `200` nas duas; depois do desfazer o principal mantém o nome corrigido e o duplicado volta a `ativo` |
| F1 | CA8 | integração | A coordenadora de A chama `GET /sessions/{id}/attendance` sem finalidade | `422`, `code = finalidade_obrigatoria` |
| F2 | CA8 | integração | A mesma chamada com `?finalidade=registro_presenca`, com uma presença na sessão | `200`; um evento `presenca.lida` com `motivo = registro_presenca`, `escopo = ebi:A` e `metadados_minimizados = {sessao_id, participante_ids: [id]}` |
| F3 [COMPLEXO] | CA8, CA9 | integração | Sem finalidade: a coordenadora de A lê uma sessão de B e um participante da igreja B; depois, ids inexistentes | `403` e `404`, nunca `422`; nenhum `presenca.lida` |
| F4 | CA9 | integração | `GET /participants/{id}/attendance` sem finalidade e depois com `?finalidade=consulta_historico` | primeiro `422 finalidade_obrigatoria`; depois `200` e um `presenca.lida` com `metadados_minimizados = {participante_id}` |
| F5 | CA10 | integração | Responsável com vínculo aprovado chama `GET /me/participants/{id}/attendance` sem finalidade | `200`; nenhum `presenca.lida` |
| F6 | CA8 | integração | Header `X-Finalidade: registro_presenca`, sem query | `200`; evento gravado |
| F7 | CA8 | integração | `?finalidade=` com 256 caracteres | `422`, `code = finalidade_invalida`; nenhum evento |
| P1 | CA11 | unidade (vitest) | Renderizar `Presenca` com a sessão aberta | `get` chamado com `'/sessions/{id}/attendance'` e `params.query.finalidade = 'registro_presenca'` |
| P2 | CA11 | unidade (vitest) | `HistoricoPresencas` com `rota="operador"` e depois com `rota="responsavel"`, clicando em "Histórico" | operador: `query.finalidade = 'consulta_historico'`; responsável: sem `query` |
| R1 | CA1–CA14 | regressão | Suíte inteira; `MesclagemTest`, `AutorizacaoParticipanteTest`, `FinalidadeLeituraTest` e `ModularBoundariesTest` sem edição | verde |

## Dados e ambiente

- **Helpers** de `backend/tests/Pest.php`: `igrejaComEbi()`, `criarUsuarioComPapel($papel, 'ebi', $ebiId)` e `participanteNaIgreja($igreja, [...])`. Para um cadastro sem igreja, use `Participante::factory()->create(['igreja_referencia_id' => null, ...])`.
- **Factories:** `Sessao::factory()` e `Presenca::factory()` (`status` `valida` ou `estornada`), como em `tests/Feature/Agenda/EstornoHistoricoTest.php`.
- **Dois atores no mesmo teste:** chame **`app('auth')->forgetGuards();` antes de cada requisição feita por outro usuário**, como em `EstornoHistoricoTest.php` (linhas 85, 105 e 160) e `PresencaTest.php` (linha 110). Sem isso, a segunda requisição roda com o usuário da primeira e pode responder `403 acting_scope_invalido` sem passar pela regra nova. Isso vale para M6, M7, M8, M9, M11, C7, C10, C11, F3 e F5.
- **Montar o estado "já mesclado":** em M6, M7, M9, M10, C6 e C11 (no M11 a mesclagem é feita pela API), use `app(GerirMesclagem::class)->mesclar($usuarioId, $principalId, $duplicadoId)`, como em `MesclagemTest.php` (linha 102). Assim o `403` do desfazer só pode vir da regra nova.
- **Chefe:** `criarUsuarioComPapel('coordenadora_chefe', 'ebi', $ebiA->id)` com `X-Acting-Scope: papel=coordenadora_chefe;escopo=ebi:<A>`. Se a vaga única de chefe atrapalhar, crie o vínculo direto, como em `tests/Feature/Escalas/AprovacaoEscalaTest.php` (linhas 236–244).
- **Super admin:** `criarUsuarioComPapel('super_admin', 'central', 'c1')`, como em `ChurchTest.php` (linha 9), sempre com `X-Acting-Scope: papel=super_admin;escopo=central:c1`. O C10 confere `escopo = central:c1`.
- **Responsável:** `ResponsavelParticipante::factory()` com `validacao = aprovada`, como em `EstornoHistoricoTest.php` (linhas 115–121).
- **Idempotência:** a chave é usuário + método + caminho + `Idempotency-Key`, sem o corpo. **Use uma chave nova (`(string) Str::uuid()`) em cada requisição** de `PATCH`, de mesclar e de desfazer. Isso vale principalmente para C4, C4b e C9, que repetem o mesmo caminho.
- **Banco:** os testes rodam no banco padrão da suíte, SQLite, que não aplica o limite de `varchar`. Por isso C4 e F7 conferem o `422` da validação, e não o erro do banco. Nenhum caso exige `TESTE_PGSQL=1`.

## Fora da cobertura automática

- **Conferência manual no portal**, com `docker compose up -d`:
  - abrir uma sessão na tela Presença: a lista carrega, e o evento aparece em `GET /audit-events` com o `adm_setor`;
  - abrir o Histórico em Participantes: a lista carrega;
  - em Dependentes, como responsável, abrir o Histórico: a lista carrega.
- **Leitura dos textos** novos na matriz, no ADR-0027 e no CHANGELOG 2.21.
- **Limite de 255 no PostgreSQL:** a suíte usa SQLite. Quem quiser confirmar que não há `500` pode rodar C4 e F7 com `TESTE_PGSQL=1`.
- **Corrida entre mesclar e registrar presença ao mesmo tempo:** fora da cobertura. A autorização é lida antes da transação. O pior caso é um `403` para uma presença ainda não gravada, que se resolve tentando de novo.
- **Disputa da cadeia de auditoria pela recarga da lista:** risco aceito na especificação; não há teste de carga.
