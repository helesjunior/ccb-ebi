# Etapa 5 — Participantes e responsáveis: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar o cadastro único do participante, os responsáveis e vínculos, o cadastro assistido com pipeline de storage, a elegibilidade etária com exceção inclusiva e a deduplicação/mesclagem auditada (E03 / RF-02, RF-03).

**Architecture:** Módulo novo `App\Domain\Participantes` (models, contracts, services, factories), consumindo contratos de `Organizacao`, `Privacidade`, `DadosSensiveis` e `Auditoria` (ADR-0002). Pipeline de storage reutilizável em `App\Support` (quarentena → validação de tipo → ClamAV → bucket cifrado → URL assinada). Rotas `/api/v1` sob gates `can:` derivados da matriz; o caminho do responsável usa verificação de **propriedade** (dependentes), não ABAC territorial. Contract-first: OpenAPI antes do código.

**Tech Stack:** PHP 8.5 / Laravel (Octane+FrankenPHP), Pest, PostgreSQL, Redis + Horizon, MinIO (S3), ClamAV (clamd 3310), Vite + React 19 + TS + Tailwind v4 + shadcn.

**Spec:** `docs/superpowers/specs/2026-11-11-participantes-responsaveis-etapa-5-design.md`

## Global Constraints

- Backend roda via `docker compose run --rm app <cmd>` (nunca `php` do host). Frontend usa Node do host em `frontend/`.
- Gates de merge: backend `docker compose run --rm app composer test` + `vendor/bin/pint --test` + `vendor/bin/phpstan analyse`; frontend `npm run build` + `npm run lint`; `scripts/check-endpoints.py` alinhado; `ModularBoundariesTest` verde.
- Após mudanças de backend, reiniciar containers: `docker compose restart app horizon scheduler` (Octane/FrankenPHP não recarrega código).
- Toda escrita sensível chama `RegistrarEvento`; nunca logar dado pessoal.
- Fronteiras modulares (ADR-0002): nenhum módulo lê tabela/model de outro; só contratos. `ModularBoundariesTest` cobre.
- Sem dados reais (seeder com dados fictícios).
- Idioma de UI e comentários de domínio: português (padrão do repo).
- Contract-first: toda rota entra em `docs/04-api/openapi.yaml` e é marcada `no OpenAPI` em `docs/04-api/endpoints.md`; `scripts/check-endpoints.py` deve passar.
- Matrícula: 8 caracteres base32 **Crockford** (sem I, L, O, U) + 1 dígito verificador; imutável, não derivada de sequência/data/dado pessoal.
- Elegibilidade: idade calculada no **fuso da igreja**; nascido em 29/02 completa ano em 01/03 nos anos não bissextos; `< 3` bloqueado sempre; `>= 12` só com exceção `aprovada` vigente.
- `*` (finalidade registrada): leitura identificável/sensível exige **finalidade declarada**, auditada. A exceção do ADR-0029 (nomeado/prazo/DPO) fica fora (dívida).

## Review Focus

Inputs/condições que a spec implica mas nenhum teste de task exercita por padrão, mais propensos a morder o usuário (mais provável primeiro). Cada linha tem o teste que a fixa na task dona:

1. **Colisão de matrícula** ao gerar (índice unique): o gerador tenta de novo e devolve matrícula nova. Teste em Task 2.
2. **29/02 em data de sessão de ano não bissexto** na véspera do aniversário: a idade não pode avançar cedo. Teste em Task 6 (`AvaliadorElegibilidade`).
3. **Fuso da igreja** muda a data local e, na virada, a idade: participante completa 12 no dia local correto. Teste em Task 6.
4. **Evidência do cadastro assistido sem arquivo** (upload ausente): cadastro assistido é recusado; não fica sem evidência. Teste em Task 8.
5. **Responsável contestado/revogado** tentando ler o dependente: o gate exige `validacao = aprovada`. Teste em Task 4.

---

## File Structure

**Backend — módulo novo `backend/app/Domain/Participantes/`:**
- `Models/Participante.php`, `Models/ResponsavelParticipante.php`, `Models/SolicitacaoVinculo.php`, `Models/ExcecaoEtaria.php` — entidades.
- `Contracts/GerirParticipante.php` (criar/consultar/corrigir), `Contracts/GerirVinculoResponsavel.php`, `Contracts/GerirExcecaoEtaria.php`, `Contracts/AvaliarElegibilidade.php`, `Contracts/GerirMesclagem.php` — superfícies públicas.
- `Services/GeradorMatricula.php`, `Services/GerenciadorParticipante.php`, `Services/GerenciadorVinculoResponsavel.php`, `Services/GerenciadorExcecao.php`, `Services/AvaliadorElegibilidade.php`, `Services/GerenciadorMesclagem.php`, `Services/ConsultaParticipante.php`, `Services/VerificadorPropriedadeParticipante.php`.
- `Database/Factories/*.php`; `Exceptions/*.php`.

**Backend — storage `backend/app/Support/Arquivo/`:** `PipelineArquivo.php`, `Contratos/*`, `Jobs/ProcessarArquivoJob.php`, `ClienteAntivirus.php`.

**Backend — camada HTTP:** `app/Http/Controllers/Api/V1/ParticipantController.php`, `GuardianController.php`, `GuardianRequestController.php`, `AgeExceptionController.php`, `ArquivoController.php`; `app/Http/Requests/Participantes/*`; `app/Http/Resources/*`; `routes/api.php`.

**Frontend:** novas páginas `frontend/src/pages/{Participantes,Dependentes,CadastroAssistido,Mesclagem,Vinculos,ExcecaoInclusiva}.tsx`, rotas em `App.tsx`.

**Migrations:** `backend/database/migrations/2026_11_12_100001_create_participante_tables.php` e `..._100002_create_arquivo_table.php`.

**Docs:** `docs/03-dados/modelo-conceitual.md`, `dicionario-de-dados.md`, `estados.md`, `docs/04-api/openapi.yaml`, `endpoints.md`, `CHANGELOG.md`, novo ADR.

---

## Task 1: Schema + models + factories do módulo Participantes

**Files:**
- Create: `backend/database/migrations/2026_11_12_100001_create_participante_tables.php`
- Create: `backend/app/Domain/Participantes/Models/{Participante,ResponsavelParticipante,SolicitacaoVinculo,ExcecaoEtaria}.php`
- Create: `backend/app/Domain/Participantes/Database/Factories/{ParticipanteFactory,ResponsavelParticipanteFactory,SolicitacaoVinculoFactory,ExcecaoEtariaFactory}.php`
- Test: `backend/tests/Feature/Participantes/EsquemaTest.php`

**Interfaces:**
- Produces: models com `HasUlids`, tabelas `participante`, `responsavel_participante`, `solicitacao_vinculo`, `excecao_etaria`; constantes de status/validação.

- [ ] **Step 1: Escrever o teste de schema que falha**

```php
// tests/Feature/Participantes/EsquemaTest.php
use App\Domain\Participantes\Models\{Participante, ResponsavelParticipante, SolicitacaoVinculo, ExcecaoEtaria};

it('cria participante com matricula unica e origem', function () {
    $p = Participante::factory()->create(['origem_cadastro' => 'responsavel']);
    expect($p->matricula)->not->toBeEmpty();
    expect($p->origem_cadastro)->toBe('responsavel');
});

it('rejeita matricula duplicada', function () {
    Participante::factory()->create(['matricula' => 'ABCD2345']);
    expect(fn () => Participante::factory()->create(['matricula' => 'ABCD2345']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('responsavel pode existir sem conta (usuario_id nulo)', function () {
    $r = ResponsavelParticipante::factory()->create(['usuario_id' => null, 'validacao' => 'pendente']);
    expect($r->usuario_id)->toBeNull();
});

it('solicitacao de vinculo e log append-only com tipo', function () {
    $s = SolicitacaoVinculo::factory()->create(['tipo' => 'contestacao']);
    expect($s->tipo)->toBe('contestacao');
});

it('excecao aponta responsavel concordante nullable', function () {
    $e = ExcecaoEtaria::factory()->create(['responsavel_concordante_id' => null, 'status' => 'solicitada']);
    expect($e->responsavel_concordante_id)->toBeNull();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\EsquemaTest`
Expected: FAIL (tabelas/models inexistentes).

- [ ] **Step 3: Criar a migration e os models**

Migration `2026_11_12_100001_create_participante_tables.php` cria as quatro tabelas com os campos da design §6.2:
- `participante`: `ulid id` PK, `matricula` string unique, `nome`, `nome_social` nullable, `data_nascimento` date, `status` (default `ativo`), `igreja_referencia_id` nullable, `origem_cadastro` (`responsavel|assistido`), `cadastrado_por` nullable, `evidencia_consentimento_id` nullable, timestamps; índices em `matricula`, `nome`, `igreja_referencia_id`.
- `responsavel_participante`: `ulid id`, `usuario_id` nullable (FK `usuarios`), `nome`, `telefone` nullable, `participante_id` FK, `tipo_relacao`, `permissoes` json, `restricoes` nullable, `status`, `validacao` (`pendente|aprovada|aguardando_documento|recusada`), `conferido_em` nullable, `conferido_por` nullable, `inicio` date, `fim` nullable, timestamps.
- `solicitacao_vinculo`: `ulid id`, `usuario_id` FK, `participante_id` FK, `responsavel_participante_id` nullable, `tipo` (`solicitacao|contestacao|revogacao`), `status`, `decidido_por` nullable, `decidido_em` nullable, `motivo` nullable, timestamps.
- `excecao_etaria`: `ulid id`, `participante_id` FK, `tipo_necessidade`, `apoios_necessarios` text, `justificativa_funcional` text, `status` (`solicitada|aprovada|recusada|suspensa|revogada|expirada`), `solicitado_por` nullable, `decidido_por` nullable, `responsavel_concordante_id` nullable (FK `responsavel_participante`), `decidido_em` nullable, `vigente_ate` nullable, `revisao_prevista_em` nullable, `revogado_em` nullable, `motivo` nullable, timestamps.

Models seguem `Organizacao\Models\Ebi`: `HasFactory` + `HasUlids`, `$table`, `$fillable`, casts, `newFactory()`, e constantes `public const VALIDACAO_APROVADA = 'aprovada';` etc. Relações `belongsTo`/`hasMany`.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\EsquemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_11_12_100001_create_participante_tables.php backend/app/Domain/Participantes backend/tests/Feature/Participantes/EsquemaTest.php
git commit -m "feat(participantes): schema, models e factories do modulo Participantes"
```

---

## Task 2: Gerador de matrícula (Crockford + dígito verificador)

**Files:**
- Create: `backend/app/Domain/Participantes/Services/GeradorMatricula.php`
- Create: `backend/app/Domain/Participantes/Exceptions/MatriculaEsgotadaException.php`
- Modify: `backend/app/Domain/Participantes/Models/Participante.php` (hook de geração)
- Test: `backend/tests/Unit/GeradorMatriculaTest.php`

**Interfaces:**
- Produces: `GeradorMatricula::gerar(): string` (9 chars: 8 + DV) e `GeradorMatricula::comDigito(string $base8): string`; constante `ALFABETO = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'`.

- [ ] **Step 1: Escrever o teste que falha (inclui colisão — Review Focus 1)**

```php
use App\Domain\Participantes\Services\GeradorMatricula;

it('gera matricula de 9 chars no alfabeto Crockford', function () {
    $m = (new GeradorMatricula())->gerar();
    expect($m)->toMatch('/^[0-9A-HJKMNP-TV-Z]{9}$/');
});

it('o digito verificador valida e uma alteracao invalida', function () {
    $g = new GeradorMatricula();
    $base = 'ABCD2345';
    $completa = $g->comDigito($base);
    expect($g->valida($completa))->toBeTrue();
    expect($g->valida('Z'.$base))->toBeFalse();
});

it('gera matriculas distintas em sequencia', function () {
    $g = new GeradorMatricula();
    $m = collect(range(1, 50))->map(fn () => $g->gerar());
    expect($m->unique()->count())->toBe(50);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --testsuite=Unit --filter=GeradorMatriculaTest`
Expected: FAIL.

- [ ] **Step 3: Implementar `GeradorMatricula`**

`gerar()`: sorteia 8 símbolos do alfabeto Crockford via `random_int`, anexa DV (MOD 37 ou módulo base-32 com pesos — algoritmo próprio, simétrico em `valida()`). `valida()` recomputa e compara. Sem dependência no banco.

- [ ] **Step 4: Ligar a geração ao model com retry em colisão (Review Focus 1)**

Em `Participante::booted()`, no evento `creating`, se `matricula` vazio, chama o gerador; em `QueryException` de unique, tenta até 5 vezes e então lança `MatriculaEsgotadaException`. (Teste de colisão: criar um participante, forçar o gerador a repetir a matrícula via container bind de teste e conferir que a segunda criação vem com matrícula diferente.)

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --testsuite=Unit --filter=GeradorMatriculaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Participantes/Services/GeradorMatricula.php backend/app/Domain/Participantes/Exceptions/MatriculaEsgotadaException.php backend/app/Domain/Participantes/Models/Participante.php backend/tests/Unit/GeradorMatriculaTest.php
git commit -m "feat(participantes): gerador de matricula Crockford com retry em colisao"
```

---

## Task 3: Cadastro do participante pelo responsável

**Files:**
- Create: `backend/app/Domain/Participantes/Contracts/GerirParticipante.php`
- Create: `backend/app/Domain/Participantes/Services/GerenciadorParticipante.php`
- Create: `backend/app/Domain/Participantes/Services/ConsultaParticipante.php`
- Create: `backend/app/Http/Controllers/Api/V1/ParticipantController.php`
- Create: `backend/app/Http/Requests/Participantes/CadastrarParticipanteRequest.php`
- Create: `backend/app/Http/Resources/ParticipanteResource.php`
- Modify: `backend/routes/api.php`, `backend/app/Providers/DomainServiceProvider.php`, `backend/app/Domain/Identidade/Support/Operacoes.php`
- Test: `backend/tests/Feature/Participantes/CadastroParticipanteTest.php`

**Interfaces:**
- Consumes: `GeradorMatricula` (Task 2), `RegistrarEvento` (Auditoria), `ConsultarOrganizacao` (valida igreja de referência).
- Produces: `POST /api/v1/participants` (201 ParticipanteResource: `{id, matricula, nome, nome_social, data_nascimento, status, origem_cadastro, igreja_referencia_id}`); operação `participante.cadastrar`; `GerirParticipante::cadastrar(ContextoAtuacao $ctx, array $dados): Participante`.

- [ ] **Step 1: Escrever o teste de feature que falha**

```php
it('responsavel cadastra participante e recebe matricula', function () {
    $resp = usuarioAutenticado('responsavel'); // helper de teste existente
    $resp = $this->actingAs($resp)->withHeader('Idempotency-Key', (string) Str::uuid());
    $this->postJson('/api/v1/participants', [
        'nome' => 'Ana Souza', 'data_nascimento' => '2016-05-10',
    ])->assertCreated()->assertJsonPath('origem_cadastro', 'responsavel')
      ->assertJsonStructure(['id', 'matricula', 'nome']);
});

it('grava auditoria do cadastro', function () {
    // cria, entao: expect(EventoAuditoria::where('acao','participante.cadastrado')->exists())->toBeTrue();
});

it('rejeita data de nascimento futura', function () {
    // 422
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\CadastroParticipanteTest`
Expected: FAIL (404).

- [ ] **Step 3: Contrato + serviço**

`GerirParticipante` com `cadastrar(ContextoAtuacao $ctx, array $dados): Participante`. `GerenciadorParticipante` injeta `RegistrarEvento`; numa transação cria o `Participante` (`origem_cadastro = responsavel`, `cadastrado_por = $ctx->usuarioId`) e registra auditoria `participante.cadastrado`, `recurso = participante`, `escopo = <contexto>`, metadados minimizados. Valida `igreja_referencia_id` via contrato de Organizacao quando presente; `data_nascimento <= hoje`.

- [ ] **Step 4: Controller, request, resource, rota e binding**

`CadastrarParticipanteRequest` valida (`nome` required string, `data_nascimento` required date, `nome_social`/`igreja_referencia_id` nullable). Rota em `routes/api.php` dentro do grupo `auth:sanctum`, sob `IdempotenciaMiddleware` e `can:participante.cadastrar`. Adicionar `Operacoes::PARTICIPANTE_CADASTRAR = 'participante.cadastrar'` e o papel à `MATRIZ` (responsavel, assistido, coordenadora R, adm_igreja G — conforme matriz). `ParametroResource` devolve o JSON acima. Bind `GerirParticipante` → `GerenciadorParticipante` no `DomainServiceProvider`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\CadastroParticipanteTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Participantes backend/app/Http backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests/Feature/Participantes/CadastroParticipanteTest.php
git commit -m "feat(participantes): cadastro do participante pelo responsavel"
```

---

## Task 4: Vínculos do responsável (propriedade/dependentes)

**Files:**
- Create: `backend/app/Domain/Participantes/Contracts/GerirVinculoResponsavel.php`
- Create: `backend/app/Domain/Participantes/Services/GerenciadorVinculoResponsavel.php`
- Create: `backend/app/Domain/Participantes/Services/VerificadorPropriedadeParticipante.php`
- Create: `backend/app/Http/Controllers/Api/V1/GuardianController.php`, `GuardianRequestController.php`
- Modify: `routes/api.php`, `Operacoes.php`, `DomainServiceProvider.php`
- Test: `backend/tests/Feature/Participantes/VinculoResponsavelTest.php`, `backend/tests/Feature/Participantes/AutorizacaoDependenteTest.php`

**Interfaces:**
- Produces: `POST /participants/{id}/guardians`, `GET /me/participants`, `PATCH /guardian-requests/{id}`; operações `vinculo.gerenciar`, `participante.ler_dependente`; `GerirVinculoResponsavel::{solicitar,decidir,contestar,revogar}`; `VerificadorPropriedadeParticipante::podeAcessar(string $usuarioId, string $participanteId): bool` (exige `responsavel_participante` com `validacao=aprovada` e `usuario_id`).

- [ ] **Step 1: Escrever os testes que falham (inclui Review Focus 5)**

```php
// VinculoResponsavelTest
it('solicitacao cria vinculo pendente e log append-only', function () { /* POST /participants/{id}/guardians => 201, validacao pendente, solicitacao_vinculo tipo solicitacao */ });
it('coordenadora aprova e validacao vira aprovada', function () { /* PATCH /guardian-requests/{id} {"decisao":"aprovar"} => ok */ });
it('duvida muda para aguardando_documento', function () { /* {"decisao":"pedir_documento"} */ });
it('contestacao e revogacao encerram o vinculo preservando historico', function () { /* fim preenchido, log mantido */ });

// AutorizacaoDependenteTest (Review Focus 5)
it('responsavel aprovado le o dependente', function () { /* GET /me/participants inclui o participante */ });
it('responsavel contestado nao le o dependente', function () { /* validacao recusada => 403 / ausente */ });
it('conhecer a matricula nao basta (CA-04)', function () { /* GET /participants/{id} sem vinculo => 403 */ });
it('responsavel nao ve dependente de outro (horizontal)', function () { /* 403 */ });
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\VinculoResponsavelTest`
Expected: FAIL.

- [ ] **Step 3: Implementar serviços e verificador de propriedade**

`GerenciadorVinculoResponsavel`: `solicitar` cria `ResponsavelParticipante` (`validacao=pendente`) + linha em `solicitacao_vinculo` (`tipo=solicitacao`); `decidir` atualiza `validacao` (`aprovar|recusar|pedir_documento` → `aprovada|recusada|aguardando_documento`, grava `decidido_por/decidido_em` em nova linha de log); `contestar`/`revogar` preenchem `fim` e log. Toda escrita chama `RegistrarEvento`. `VerificadorPropriedadeParticipante::podeAcessar` é a checagem de **propriedade** (não territorial).

- [ ] **Step 4: Controllers, rotas e gates**

`GET /me/participants` filtra por `usuario_id = ator` e `validacao = aprovada`. `PATCH /guardian-requests/{id}` sob `can:vinculo.gerenciar` + escopo territorial (coordenadora). `POST /participants/{id}/guardians` autenticado. A leitura de dependente usa o Verificador (não o `can:` territorial). Adicionar `Operacoes::VINCULO_GERENCIAR = 'vinculo.gerenciar'` e os papéis à `MATRIZ`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Participantes`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Participantes backend/app/Http backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/app/Domain/Identidade/Support/Operacoes.php backend/tests/Feature/Participantes
git commit -m "feat(participantes): vinculos do responsavel com autorizacao por propriedade"
```

---

## Task 5: Elegibilidade etária + exceção inclusiva

**Files:**
- Create: `backend/app/Domain/Participantes/Contracts/{AvaliarElegibilidade,GerirExcecaoEtaria}.php`
- Create: `backend/app/Domain/Participantes/Services/{AvaliadorElegibilidade,GerenciadorExcecao}.php`
- Create: `backend/app/Http/Controllers/Api/V1/AgeExceptionController.php`
- Modify: `routes/api.php`, `Operacoes.php`, `DomainServiceProvider.php`
- Test: `backend/tests/Unit/AvaliadorElegibilidadeTest.php`, `backend/tests/Feature/Participantes/ExcecaoEtariaTest.php`

**Interfaces:**
- Produces: `AvaliarElegibilidade::avaliar(Participante $p, \DateTimeInterface $dataSessao, string $fusoIgreja, ?ExcecaoEtaria $excecao): ResultadoElegibilidade` (`{permitido: bool, motivo: string}`); `POST /participants/{id}/age-exceptions`, `PATCH /age-exceptions/{id}`; operações `excecao.decidir`, `excecao.ler`.

- [ ] **Step 1: Escrever o teste de tabela que falha (Review Focus 2 e 3)**

```php
use App\Domain\Participantes\Services\AvaliadorElegibilidade;

it('bloqueia menor de 3 anos mesmo com excecao', function () { /* data: hoje 2026-11-11, nasc 2025-01-01 => permitido=false */ });
it('permite 3 anos, bloqueia vespera dos 12, permite dia dos 12 com excecao aprovada', function () {
    // nasc 2014-06-15; sessao 2026-06-14 => 11 (sem excecao, bloqueia? nao: 11 permite)
    // sessao 2026-06-15 => 12 sem excecao bloqueia; com excecao aprovada vigente permite
});
it('29 de fevereiro completa ano em 01/03 em ano nao bissexto (RN-16)', function () {
    // nasc 2016-02-29; sessao local 2028-02-29 => 12 exatos? e 2027-02-28 => 10
});
it('fuso da igreja decide a data local na virada', function () {
    // instante UTC proximo da meia-noite; America/Sao_Paulo vs America/Manaus => idades podem diferir
});
it('excecao revogada ou expirada bloqueia nova presenca', function () { /* CA-17 */ });
it('excecao suspensa bloqueia; historico preservado', function () { /* RN-15 */ });
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --testsuite=Unit --filter=AvaliadorElegibilidadeTest`
Expected: FAIL.

- [ ] **Step 3: Implementar `AvaliadorElegibilidade`**

Converte `$dataSessao` para o fuso da igreja (`\DateTimeImmutable::setTimezone`) e calcula a idade: compara (ano, mês, dia) locais; para 29/02 em ano não bissexto, o "aniversário" é 01/03. Regras: `<3` → bloqueia (CA-19), `3..11` → permite, `>=12` sem exceção vigente → bloqueia, exceção `aprovada` e (`vigente_ate` nulo ou `>= data`) → permite; `suspensa|revogada|expirada` → bloqueia (CA-17/RN-15). Não depende de `presenca`/sessão.

- [ ] **Step 4: Exceção inclusiva (serviço + rotas)**

`GerenciadorExcecao::solicitar` e `::decidir` (aprovar/recusar/suspender/revogar), gravando `decidido_por`, `responsavel_concordante_id` (nullable, aponta para `responsavel_participante`), datas, `motivo`; auditoria em `excecao.decidida`. Rota `PATCH /age-exceptions/{id}` sob `can:excecao.decidir` + escopo; `POST /participants/{id}/age-exceptions` autenticado. Adicionar `Operacoes::EXCECAO_DECIDIR`/`EXCECAO_LER` à `MATRIZ`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=AvaliadorElegibilidadeTest` e `--filter=ExcecaoEtariaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Participantes backend/app/Http backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/tests/Unit/AvaliadorElegibilidadeTest.php backend/tests/Feature/Participantes/ExcecaoEtariaTest.php
git commit -m "feat(participantes): elegibilidade etaria e excecao inclusiva"
```

---

## Task 6: Deduplicação + mesclagem auditada e reversível

**Files:**
- Create: `backend/app/Domain/Participantes/Contracts/GerirMesclagem.php`
- Create: `backend/app/Domain/Participantes/Services/{DetectorDuplicidade,GerenciadorMesclagem}.php`
- Modify: `ParticipantController.php`, `routes/api.php`, `Operacoes.php`, `DomainServiceProvider.php`
- Test: `backend/tests/Feature/Participantes/MesclagemTest.php`

**Interfaces:**
- Produces: `POST /participants/{id}/merge`; operação `participante.mesclar`; `GerirMesclagem::{detectar,mesclar,desfazer}`.

- [ ] **Step 1: Escrever o teste que falha (escopo CA-27)**

```php
it('detecta possivel duplicidade por nome normalizado + data', function () { /* nao revela terceiros: retorna apenas sinal */ });
it('mescla e desfaz preservando o estado anterior', function () { /* mesclar -> dados fundidos; desfazer -> restaurado */ });
it('coordenadora so mescla com igreja de referencia em EBI onde atua', function () { /* fora do escopo => 403 */ });
it('fora do escopo vai para a coordenadora chefe', function () { /* documenta o caminho */ });
it('mesclagem grava auditoria', function () { /* acao participante.mesclado */ });
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\MesclagemTest`
Expected: FAIL.

- [ ] **Step 3: Implementar detector e gerenciador**

`DetectorDuplicidade::detectar(array $dados): array` — normaliza nome (minúsculas, sem acento/espaço duplo) e casa com `data_nascimento`; devolve apenas o **sinal** (ids internos para a coordenadora), nunca dados de terceiros ao responsável. `GerenciadorMesclagem::mesclar($ctx, $principalId, $duplicadoId)` numa transação: guarda um snapshot do estado anterior (JSON em coluna `snapshot_mesclagem` em `participante` ou tabela de log dedicada), move vínculos/presenças futuras e encerra o duplicado; `desfazer($ctx, $snapshotId)` restaura. Escopo: coordenadora só se `igreja_referencia_id` pertence a EBI onde atua (contrato Organizacao); senão coordenadora chefe. Auditoria `participante.mesclado`/`participante.mesclagem_desfeita`.

- [ ] **Step 4: Rota e gate**

`POST /participants/{id}/merge` sob `IdempotenciaMiddleware` + `can:participante.mesclar` + escopo. Adicionar `Operacoes::PARTICIPANTE_MESCLAR`.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\MesclagemTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Domain/Participantes backend/app/Http backend/routes/api.php backend/app/Providers/DomainServiceProvider.php backend/app/Domain/Identidade/Support/Operacoes.php backend/tests/Feature/Participantes/MesclagemTest.php
git commit -m "feat(participantes): deduplicacao e mesclagem auditada e reversivel"
```

---

## Task 7: Pipeline de storage reutilizável (`App\Support\Arquivo`)

**Files:**
- Create: `backend/database/migrations/2026_11_12_100002_create_arquivo_table.php`
- Create: `backend/app/Support/Arquivo/{Contratos/ArmazenarArquivo.php,Contratos/ProcessarArquivo.php}`
- Create: `backend/app/Support/Arquivo/{PipelineArquivo.php,ClienteAntivirus.php,Jobs/ProcessarArquivoJob.php}`
- Modify: `backend/config/filesystems.php` (disco `laudos` cifrado), `.env.example`
- Test: `backend/tests/Feature/Arquivo/PipelineArquivoTest.php`

**Interfaces:**
- Produces: `ArmazenarArquivo::enfileirar(UploadedFile $f, string $finalidade, ?string $enviadoPor): Arquivo` (grava em `quarentena`, cria registro, despacha job); `Arquivo` model (`status_antivirus`: `pendente|limpo|infectado|invalido`, `chave_storage`, `mime`, `tamanho`, `sha256`, `bucket`, `cifrado`); `ClienteAntivirus::scan(string $path): bool`; job `ProcessarArquivoJob`.

- [ ] **Step 1: Escrever o teste que falha (Storage fake + dublê clamd)**

```php
use Illuminate\Support\Facades\Storage;

it('arquivo limpo e promovido para o bucket laudos cifrado', function () {
    Storage::fake('quarentena'); Storage::fake('laudos');
    $this->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->andReturn(true));
    // enfileira um PDF valido; processa o job; expect status_antivirus = 'limpo', bucket = 'laudos'
});

it('arquivo infectado e apagado e auditado', function () {
    $this->mock(ClienteAntivirus::class, fn ($m) => $m->shouldReceive('scan')->andReturn(false));
    // expect status_antivirus = 'infectado' e arquivo inexistente; evento registrado
});

it('tipo real invalido (assinatura) e recusado', function () {
    // arquivo .pdf com assinatura nao-PDF => status 'invalido', apagado
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Arquivo\\PipelineArquivoTest`
Expected: FAIL.

- [ ] **Step 3: Migration, modelos e disco**

Migration cria `arquivo` (campos da design §6.2). `filesystems.php` ganha disco `quarentena` (s3, sem cifra) e `laudos` (s3, **cifrado** com `laudos_key` — via `options`/middleware de cifragem ou driver S3 com chave própria). `ClienteAntivirus` conecta em `CLAMAV_HOST:CLAMAV_PORT` (3310) com timeout; em `testing`, usa dublê.

- [ ] **Step 4: Pipeline + job**

`PipelineArquivo` valida o **tipo real** por assinatura de bytes (magic numbers de PDF/JPEG/PNG), calcula `sha256`, grava na `quarentena`. `ProcessarArquivoJob` (na fila `default`): lê da quarentena, roda `ClienteAntivirus::scan`; se limpo, move para `laudos` (cifrado) e marca `limpo`; se infectado/inválido, apaga e marca e registra auditoria.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Arquivo\\PipelineArquivoTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_11_12_100002_create_arquivo_table.php backend/app/Support/Arquivo backend/config/filesystems.php backend/.env.example backend/tests/Feature/Arquivo
git commit -m "feat(arquivo): pipeline de storage com antivirus e bucket cifrado"
```

---

## Task 8: Cadastro assistido + evidência obrigatória

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/ArquivoController.php`
- Modify: `ParticipantController.php` (rota assistida), `GerenciadorParticipante.php`, `routes/api.php`, `Operacoes.php`
- Modify: `backend/app/Domain/Privacidade/Contracts/RegistrarConsentimento.php` + `GerenciadorConsentimento.php` (`$usuarioId` nullable; evidência com arquivo)
- Modify: `backend/app/Support/EvidenciaAceite.php` (`?string $arquivoId`)
- Test: `backend/tests/Feature/Participantes/CadastroAssistidoTest.php`

**Interfaces:**
- Consumes: `ArmazenarArquivo` (Task 7), `RegistrarConsentimento` (estendido).
- Produces: `POST /participants/assisted` (multipart: dados + `evidencia` arquivo), `GET /arquivos/{id}` (URL assinada); evidência vinculada a `participante.evidencia_consentimento_id`; cadastro marcado `assistido`.

- [ ] **Step 1: Escrever o teste que falha (Review Focus 4)**

```php
it('cadastro assistido exige evidencia e grava origem assistido', function () {
    // multipart com arquivo valido => 201 origem_cadastro=assistido, evidencia_consentimento_id preenchido
});
it('cadastro assistido sem evidencia e recusado', function () { /* sem arquivo => 422 */ });
it('responsavel assistido pode existir sem usuario_id', function () { /* usuario_id null */ });
it('download da evidencia so por URL assinada e autorizado', function () { /* GET /arquivos/{id} sem permissao => 403 */ });
it('registra consentimento com origem assistido e usuario_id nulo', function () { /* manifestacao criada */ });
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\CadastroAssistidoTest`
Expected: FAIL.

- [ ] **Step 3: Estender o contrato de consentimento**

`RegistrarConsentimento::registrar(?string $usuarioId, ...)`; `GerenciadorConsentimento` aceita nulo e grava `participante_id` quando passado; `EvidenciaAceite` ganha `?string $arquivoId` e `textoEvidencia` inclui `arquivo=<id>`. Sem mudança de schema (colunas já existem).

- [ ] **Step 4: Rota assistida + evidência**

`GerenciadorParticipante::cadastrarAssistido($ctx, array $dados, UploadedFile $evidencia)`: exige evidência, chama `ArmazenarArquivo::enfileirar`, cria `Participante` (`origem_cadastro=assistido`, `evidencia_consentimento_id`), cria `ResponsavelParticipante` sem conta, registra consentimento (origem assistido, usuario nulo), audita. `ArquivoController::show` valida autorização e devolve URL assinada de curta duração.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\CadastroAssistidoTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app backend/routes/api.php backend/tests/Feature/Participantes/CadastroAssistidoTest.php
git commit -m "feat(participantes): cadastro assistido com evidencia obrigatoria cifrada"
```

---

## Task 9: OpenAPI + catálogo + finalidade declarada nas leituras

**Files:**
- Modify: `docs/04-api/openapi.yaml`, `docs/04-api/endpoints.md`
- Modify: `ParticipantController.php` (leitura identificável exige finalidade), `Operacoes.php`, `routes/api.php`
- Regenerate: `frontend/src/api/schema.d.ts`
- Test: `backend/tests/Feature/Participantes/ContratoParticipantesTest.php`, `backend/tests/Feature/Participantes/FinalidadeLeituraTest.php`

**Interfaces:**
- Produces: operações no OpenAPI para todas as rotas das Tasks 3–8; leitura identificável/sensível exige `finalidade` (query/header) registrada na auditoria; operação `evidencia.ler`.

- [ ] **Step 1: Escrever o teste que falha**

```php
it('leitura identificavel sem finalidade e recusada', function () { /* GET /participants?q=... sem finalidade => 422 */ });
it('leitura identificavel com finalidade registra auditoria', function () { /* acao participante.lido, metadados com finalidade */ });
it('catalogo e openapi alinhados', function () { /* roda o check-endpoints por dentro ou e coberto no CI */ });
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm app php artisan test --filter=Participantes\\FinalidadeLeituraTest`
Expected: FAIL.

- [ ] **Step 3: Descrever as operações no OpenAPI e marcar no catálogo**

Adicionar as rotas novas (`/participants`, `/participants/{id}`, `/participants/assisted`, `/participants/{id}/guardians`, `/guardian-requests/{id}`, `/me/participants`, `/participants/{id}/age-exceptions`, `/age-exceptions/{id}`, `/participants/{id}/merge`, `/arquivos/{id}`) com schemas, segurança e respostas. Marcar cada linha `no OpenAPI` em `endpoints.md`.

- [ ] **Step 4: Exigir finalidade declarada nas leituras**

No controller de leitura identificável/sensível, exigir `finalidade` (query param ou header `X-Finalidade`), recusar 422 sem ela, e registrar em `RegistrarEvento` (`acao = participante.lido`, `motivo = <finalidade>`). **Não** reusar `termo_versao.finalidade`.

- [ ] **Step 5: Regenerar o cliente e rodar o checker**

Run: `npm run generate:api` (em `frontend/` ou comando do repo) e `docker compose run --rm app php artisan test --filter=Participantes`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add docs/04-api frontend/src/api/schema.d.ts backend/tests/Feature/Participantes backend/app
git commit -m "feat(participantes): contrato OpenAPI, catalogo e finalidade declarada nas leituras"
```

---

## Task 10: Telas (frontend shadcn)

**Files:**
- Create: `frontend/src/pages/{Participantes,Dependentes,CadastroAssistido,Mesclagem,Vinculos,ExcecaoInclusiva}.tsx`
- Modify: `frontend/src/App.tsx`
- Test: nenhum runner (validado por `lint` + `build`, como as telas anteriores)

**Interfaces:**
- Consumes: cliente gerado em `frontend/src/api/client.ts` (`api.GET/POST/PATCH`).

- [ ] **Step 1: Implementar as telas**

Cada página segue o padrão de `Dispositivos.tsx` (estado + `api.GET/POST`, `lerErro`); usa componentes shadcn (`Button`) e Tailwind v4; acessível por teclado e legível no celular. Telas: cadastro de participante (responsável), meus dependentes, cadastro assistido (upload de evidência), revisão/mesclagem, vínculos, exceção inclusiva.

- [ ] **Step 2: Registrar as telas em `App.tsx`**

Adicionar entradas ao tipo `Tela`, botões na `nav` e os `{tela === '...' && <Tela />}` no corpo autenticado.

- [ ] **Step 3: Rodar build e lint**

Run: `npm run lint` e `npm run build` (em `frontend/`)
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add frontend/src
git commit -m "feat(participantes): telas de cadastro, dependentes, assistido, mesclagem, vinculos e excecao"
```

---

## Task 11: Seeder com casos-limite + docs + verificação

**Files:**
- Create: `backend/database/seeders/ParticipanteSeeder.php` (ou estender o seeder existente)
- Modify: `docs/03-dados/modelo-conceitual.md`, `dicionario-de-dados.md`, `estados.md`, `CHANGELOG.md`
- Create: `docs/06-arquitetura/decisoes/ADR-0031-participantes-e-responsaveis.md`

**Interfaces:**
- Produces: casos-limite de idade no ambiente de dev; rastreabilidade atualizada.

- [ ] **Step 1: Escrever o seeder**

Cria hierarquia mínima (se necessário) + participantes com: 2 anos (bloqueado), véspera dos 12, dia dos 12 sem exceção, dia dos 12 com exceção vigente, exceção expirada, responsável sem conta (assistido). Dados fictícios.

- [ ] **Step 2: Rodar o seeder**

Run: `docker compose run --rm app php artisan db:seed --class=ParticipanteSeeder`
Expected: sem erro.

- [ ] **Step 3: Atualizar a documentação de dados e o CHANGELOG**

`modelo-conceitual.md` (as entidades já constam; conferir), `dicionario-de-dados.md` e `estados.md` ganham `participante`, `responsavel_participante`, `solicitacao_vinculo`, `excecao_etaria`, `arquivo`. `CHANGELOG.md` registra a Etapa 5. Novo ADR-0031 registra: duas tabelas de vínculo, `responsavel_concordante_id` → `responsavel_participante`, autorização por propriedade, escopo de mesclagem só por igreja de referência nesta etapa, e a dívida do ADR-0029.

- [ ] **Step 4: Rodar a bateria completa dos gates**

Run: `docker compose run --rm app composer test`; `vendor/bin/pint --test`; `vendor/bin/phpstan analyse`; `npm run build`; `npm run lint`; `check-endpoints.py`
Expected: tudo verde.

- [ ] **Step 5: Commit**

```bash
git add backend/database/seeders docs
git commit -m "chore(participantes): seeder com casos-limite, docs e ADR-0031"
```

---

## Self-Review

**1. Spec coverage:** cada objetivo da design §2 tem task — cadastro único (T1,T2,T3), matrícula estável (T2), responsáveis/vínculos (T4), cadastro assistido (T8), elegibilidade/exceção (T5), dedup/mesclagem (T6), storage (T7). Contrato/telas (T9,T10). Seeder/docs (T11).

**2. Step scan:** cada step é uma ação com resultado checável; passos de código trazem assinatura/valores, não corpos completos.

**3. Type consistency:** `GeradorMatricula::gerar/comDigito/valida`; `VerificadorPropriedadeParticipante::podeAcessar`; `AvaliarElegibilidade::avaliar`; `ArmazenarArquivo::enfileirar` — usados de forma consistente entre tasks.

**4. Review Focus:** as 5 linhas têm teste na task dona (T2, T5×2, T8, T4).

**5. Proportion:** o plano descreve decisões (nomes, assinaturas, valores), não transcreve corpos.

## Dependências entre tasks

T1 → T2 → T3 → {T4, T5}; T6 depende de T3; T7 independe (Support); T8 depende de T3, T7; T9 depende de todas as rotas; T10 depende do cliente gerado em T9; T11 fecha.