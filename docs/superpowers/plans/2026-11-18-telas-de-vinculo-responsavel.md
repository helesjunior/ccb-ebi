# Telas de vínculo do responsável — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fechar o FL-01 no app: a coordenadora lista e decide pedidos de vínculo do seu território, e a tela de Vínculos deixa de exigir ULIDs.

**Architecture:** Uma operação nova de leitura (`GET /guardian-requests`) com recorte duplo — território para quem tem `vinculo.gerenciar`, propriedade (`usuario_id`) para os demais — servida por `GerirVinculoResponsavel::listar`. O `decidir` passa a exigir território (404 fora). O recurso do vínculo ganha `participante: {id, nome, matricula}`. No front, `Vinculos.tsx` vira duas abas (Pedidos / Solicitar) sem campos de ULID.

**Tech Stack:** Laravel 11 + Pest + PHPStan + Pint (backend em contêiner Docker); React 19 + Vite + TanStack-free `api` gerado por `openapi-typescript` + shadcn/ui.

**Spec:** `docs/superpowers/specs/2026-11-18-telas-de-vinculo-responsavel-design.md`

## Global Constraints

- PHP roda **sempre** no contêiner: `docker compose run --rm -T app <cmd>`. `vendor/` não existe no host.
- Nunca use `Set-Content`/`Out-File` para editar PHP/TS/YAML — corrompe encoding. Use a ferramenta de edição ou `[System.IO.File]::WriteAllText(..., UTF8Encoding($false))`.
- `docs/04-api/openapi.yaml` está em **CRLF**. Preserve.
- Contract-first: OpenAPI antes do código; `frontend/src/api/schema.d.ts` é **gerado** (`npm run gen:api`), nunca editado à mão.
- Fronteira modular (ADR-0002): controllers e resources não tocam models de Participantes; use os contratos (`GerirVinculoResponsavel`, `GerirParticipante`, `ParticipanteResumo`).
- Coleções da API novas seguem `{data: [...]}`.
- Gate final de cada task de backend: `docker compose run --rm -T app php artisan test`, `vendor/bin/pint --test`, `composer phpstan`. No front: `npm run lint` e `npm run build`.
- Mensagens e rótulos de UI em português do Brasil.
- Não commite nada além dos arquivos da task; mensagem de commit no estilo do repo (`feat(...)`, `fix(...)`).

## Review Focus

1. **Coordenadora de outra EBI tentando decidir vínculo alheio** — deve receber 404, nunca 200 e nunca 403 (403 revelaria existência).
2. **Responsável abrindo a lista** — deve ver apenas vínculos com `usuario_id` dele; nunca vínculo de terceiro nem de outro território.
3. **Cadastro assistido no território da coordenadora** — vínculo com `usuario_id = null` aparece pelo recorte territorial; é a única forma de vê-lo.
4. **Lista vazia / erro de rede** — a tela mostra o estado vazio "Nenhum pedido" e o erro sem quebrar; nunca um spinner infinito.
5. **Dupla contagem** — um vínculo que cai nos dois recortes (coordenadora que também é responsável) aparece uma vez só.

---

### Task 1: Contrato OpenAPI da listagem de vínculos

**Files:**
- Modify: `docs/04-api/openapi.yaml` (região de `/guardian-requests`, ~linha 1635; schema `ResponsavelParticipante` ~linha 2244; `components/parameters`)

**Interfaces:**
- Produces: operação `GET /guardian-requests` com query `validacao` e `finalidade`, resposta `{data: [ResponsavelParticipante]}`; `ResponsavelParticipante.participante` do tipo `VinculoParticipanteResumo {id, nome, matricula}`.

- [ ] **Step 1: Adicionar o schema `VinculoParticipanteResumo`**

Em `components.schemas`, logo antes de `ResponsavelParticipante`:

```yaml
    VinculoParticipanteResumo:
      type: object
      properties:
        id: { type: string }
        nome: { type: string }
        matricula: { type: string }
```

- [ ] **Step 2: Adicionar o campo `participante` em `ResponsavelParticipante`**

No schema existente, depois de `participante_id`, adicionar:

```yaml
        participante:
          $ref: '#/components/schemas/VinculoParticipanteResumo'
```

- [ ] **Step 3: Adicionar a operação `get` em `/guardian-requests/{id}`? NÃO — em um path novo `/guardian-requests`**

Antes do path `/guardian-requests/{id}`, inserir:

```yaml
  /guardian-requests:
    get:
      tags: [participants]
      summary: Listar pedidos de vínculo (RF-03)
      description: >-
        Lista vínculos visíveis ao ator: os do seu território, para quem tem
        `vinculo.gerenciar`, e os que ele mesmo abriu, por propriedade. Exige a
        finalidade declarada, registrada na auditoria.
      security:
        - bearerAuth: []
      parameters:
        - $ref: '#/components/parameters/XActingScope'
        - name: validacao
          in: query
          required: false
          schema:
            type: string
            enum: [pendente, aprovada, aguardando_documento, recusada, todos]
            default: pendente
        - $ref: '#/components/parameters/FinalidadeLeitura'
      responses:
        '200':
          description: Pedidos visíveis ao ator
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items: { $ref: '#/components/schemas/ResponsavelParticipante' }
        '401': { $ref: '#/components/responses/Problem' }
        '403': { $ref: '#/components/responses/Problem' }
        '422': { $ref: '#/components/responses/Problem' }
```

- [ ] **Step 4: Validar o contrato**

Run: `npx --yes @stoplight/spectral-cli lint docs/04-api/openapi.yaml --ruleset scripts/.spectral.yaml`
Expected: sem erros (avisos pré-existentes são aceitáveis).
Run: `docker run --rm -v "${PWD}:/w" -w /w python:3.12-slim sh -c "pip install -q pyyaml && python scripts/check-endpoints.py"`
Expected: `OK: <n> operações alinhadas` com `n` = valor anterior + 1.

- [ ] **Step 5: Commit**

```bash
git add docs/04-api/openapi.yaml
git commit -m "feat(api): contrato da listagem de pedidos de vinculo (RF-03)"
```

---

### Task 2: `listar` no domínio com recorte duplo

**Files:**
- Modify: `backend/app/Domain/Participantes/Contracts/GerirVinculoResponsavel.php`
- Modify: `backend/app/Domain/Participantes/Services/GerenciadorVinculoResponsavel.php`
- Create: `backend/app/Support/Participantes/` (nada) — usa `AutorizacaoParticipante` existente
- Test: `backend/tests/Feature/Participantes/ListarVinculosTest.php` (novo)

**Interfaces:**
- Consumes: `App\Support\Participantes\AutorizacaoParticipante::pode(?ContextoAtuacao, string $usuarioId, Participante $participante, string $operacaoTerritorial): bool`; `App\Domain\Identidade\Services\ContextoAtuacao`.
- Produces: `GerirVinculoResponsavel::listar(string $atorId, ?ContextoAtuacao $contexto, string $validacao): array` — `array<int, ResponsavelParticipante>` com `participante` carregado.

- [ ] **Step 1: Escrever os testes**

Criar `backend/tests/Feature/Participantes/ListarVinculosTest.php`:

```php
<?php

use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Participantes\Contracts\GerirVinculoResponsavel;
use App\Domain\Participantes\Models\ResponsavelParticipante;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    ['igreja' => $this->outraIgreja, 'ebi' => $this->outraEbi] = igrejaComEbi();

    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->contexto = new ContextoAtuacao('coordenadora', 'ebi', $this->ebi->id, $this->coordenadora->id);
    $this->servico = app(GerirVinculoResponsavel::class);
});

it('lista apenas vinculos do territorio da coordenadora', function () {
    $meu = participanteNaIgreja($this->igreja, ['nome' => 'Ana do Territorio']);
    $alheio = participanteNaIgreja($this->outraIgreja, ['nome' => 'Beto de Fora']);
    $vinculoMeu = ResponsavelParticipante::factory()->create(['participante_id' => $meu->id, 'validacao' => 'pendente']);
    $vinculoAlheio = ResponsavelParticipante::factory()->create(['participante_id' => $alheio->id, 'validacao' => 'pendente']);

    $lista = $this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente');

    expect(collect($lista)->pluck('id')->all())->toBe([$vinculoMeu->id]);
});

it('inclui o vinculo do proprio ator mesmo fora do territorio que gerencia', function () {
    $fora = participanteNaIgreja($this->outraIgreja);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $fora->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => 'pendente',
    ]);

    $lista = $this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente');

    expect(collect($lista)->pluck('id')->all())->toBe([$vinculo->id]);
});

it('nao duplica o vinculo que cai nos dois recortes', function () {
    $p = participanteNaIgreja($this->igreja);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => 'pendente',
    ]);

    $lista = $this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente');

    expect(collect($lista)->pluck('id')->all())->toBe([$vinculo->id]);
});

it('mostra vinculo sem dono apenas pelo territorio', function () {
    $p = participanteNaIgreja($this->igreja);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id,
        'usuario_id' => null,
        'validacao' => 'pendente',
    ]);

    $lista = $this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente');

    expect(collect($lista)->pluck('id')->all())->toBe([$vinculo->id]);
});

it('filtra por validacao e aceita todos', function () {
    $p = participanteNaIgreja($this->igreja);
    ResponsavelParticipante::factory()->create(['participante_id' => $p->id, 'validacao' => 'pendente']);
    ResponsavelParticipante::factory()->aprovado()->create(['participante_id' => $p->id]);

    expect($this->servico->listar($this->coordenadora->id, $this->contexto, 'pendente'))->toHaveCount(1)
        ->and($this->servico->listar($this->coordenadora->id, $this->contexto, 'aprovada'))->toHaveCount(1)
        ->and($this->servico->listar($this->coordenadora->id, $this->contexto, 'todos'))->toHaveCount(2);
});

it('sem contexto territorial lista apenas os vinculos proprios', function () {
    $p = participanteNaIgreja($this->igreja);
    $meu = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id, 'usuario_id' => $this->coordenadora->id,
    ]);
    ResponsavelParticipante::factory()->create(['participante_id' => $p->id, 'usuario_id' => null]);

    $lista = $this->servico->listar($this->coordenadora->id, null, 'todos');

    expect(collect($lista)->pluck('id')->all())->toBe([$meu->id]);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm -T app php artisan test --filter=ListarVinculosTest`
Expected: FAIL — `Call to undefined method ...::listar()`.

- [ ] **Step 3: Declarar no contrato**

Em `GerirVinculoResponsavel.php`, adicionar:

```php
    /**
     * Vinculos visiveis ao ator: os do seu territorio (com `vinculo.gerenciar`)
     * unidos aos que ele mesmo abriu. Deduplicado por id.
     *
     * @return array<int, ResponsavelParticipante>
     */
    public function listar(string $atorId, ?ContextoAtuacao $contexto, string $validacao): array;
```

Importar `App\Domain\Identidade\Services\ContextoAtuacao` e `App\Domain\Participantes\Models\ResponsavelParticipante`.

- [ ] **Step 4: Implementar em `GerenciadorVinculoResponsavel`**

Injetar `AutorizacaoParticipante $autorizacao` no construtor (segundo parâmetro, depois de `RegistrarEvento`). Implementar:

```php
    public function listar(string $atorId, ?ContextoAtuacao $contexto, string $validacao): array
    {
        $query = ResponsavelParticipante::query()->with('participante');

        if ($validacao !== 'todos') {
            $query->where('validacao', $validacao);
        }

        $candidatos = $query->orderByDesc('created_at')->orderByDesc('id')->get();

        $visiveis = $candidatos->filter(function (ResponsavelParticipante $v) use ($atorId, $contexto): bool {
            if ($atorId !== '' && $v->usuario_id === $atorId) {
                return true;
            }

            return $v->participante !== null
                && $this->autorizacao->pode($contexto, $atorId, $v->participante, 'vinculo.gerenciar');
        });

        return $visiveis->unique('id')->values()->all();
    }
```

Nota: `pode()` já retorna `false` sem `ContextoAtuacao`, então o recorte de propriedade sobrevive sozinho. Um vínculo nunca aparece sem que o ator o possa ler.

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose run --rm -T app php artisan test --filter=ListarVinculosTest`
Expected: PASS (6 testes).

- [ ] **Step 6: Gates**

Run: `docker compose run --rm -T app vendor/bin/pint app/Domain/Participantes tests/Feature/Participantes/ListarVinculosTest.php && docker compose run --rm -T app composer phpstan`
Expected: Pint `PASS`, PHPStan `No errors`.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Domain/Participantes backend/tests/Feature/Participantes/ListarVinculosTest.php
git commit -m "feat(participantes): listagem de vinculos com recorte territorial e por propriedade (RF-03)"
```

---

### Task 3: Rota e controller `GET /guardian-requests`

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/GuardianController.php`
- Modify: `backend/routes/api.php` (bloco de vínculos, ~linha 184)
- Create: `backend/app/Http/Requests/Participantes/ListarVinculosRequest.php`
- Modify: `backend/app/Http/Resources/ResponsavelParticipanteResource.php`
- Test: `backend/tests/Feature/Participantes/ListarVinculosHttpTest.php` (novo)

**Interfaces:**
- Consumes: `GerirVinculoResponsavel::listar(string, ?ContextoAtuacao, string): array`; `App\Domain\Participantes\Contracts\GerirParticipante::encontrar(string $id): ?ParticipanteResumo`.
- Produces: `ResponsavelParticipanteResource` com chave `participante` (`id`, `nome`, `matricula`) e a rota nomeada `guardian-requests.index`.

- [ ] **Step 1: Escrever os testes HTTP**

Criar `backend/tests/Feature/Participantes/ListarVinculosHttpTest.php`:

```php
<?php

use App\Domain\Participantes\Models\ResponsavelParticipante;

beforeEach(function () {
    ['igreja' => $this->igreja, 'ebi' => $this->ebi] = igrejaComEbi();
    $this->coordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $this->auth = [
        'Authorization' => 'Bearer '.$this->coordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ];
    $this->responsavel = criarUsuarioComPapel('responsavel', 'central', 'c1');
    $this->authResp = ['Authorization' => 'Bearer '.$this->responsavel->createToken('t')->plainTextToken];
});

it('devolve os pedidos do territorio com nome e matricula do participante', function () {
    $p = participanteNaIgreja($this->igreja, ['nome' => 'Ana Clara']);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id, 'nome' => 'Mae da Ana', 'validacao' => 'pendente',
    ]);

    $this->getJson('/api/v1/guardian-requests?finalidade=triage', $this->auth)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $vinculo->id)
        ->assertJsonPath('data.0.participante.nome', 'Ana Clara')
        ->assertJsonPath('data.0.participante.matricula', $p->matricula);
});

it('exige finalidade declarada', function () {
    $this->getJson('/api/v1/guardian-requests', $this->auth)->assertStatus(422);
});

it('responsavel ve apenas os proprios pedidos', function () {
    $p = participanteNaIgreja($this->igreja);
    $meu = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id, 'usuario_id' => $this->responsavel->id,
    ]);
    ResponsavelParticipante::factory()->create(['participante_id' => $p->id]);

    $this->getJson('/api/v1/guardian-requests?validacao=todos&finalidade=acompanhar', $this->authResp)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $meu->id);
});

it('aceita o filtro de validacao', function () {
    $p = participanteNaIgreja($this->igreja);
    ResponsavelParticipante::factory()->create(['participante_id' => $p->id, 'validacao' => 'pendente']);
    $aprovado = ResponsavelParticipante::factory()->aprovado()->create(['participante_id' => $p->id]);

    $this->getJson('/api/v1/guardian-requests?validacao=aprovada&finalidade=triage', $this->auth)
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $aprovado->id);
});

it('rejeita validacao desconhecida', function () {
    $this->getJson('/api/v1/guardian-requests?validacao=xpto&finalidade=triage', $this->auth)
        ->assertStatus(422);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm -T app php artisan test --filter=ListarVinculosHttpTest`
Expected: FAIL — 404.

- [ ] **Step 3: Criar o FormRequest**

`backend/app/Http/Requests/Participantes/ListarVinculosRequest.php`:

```php
<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarVinculosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'validacao' => ['sometimes', Rule::in([
                'pendente', 'aprovada', 'aguardando_documento', 'recusada', 'todos',
            ])],
            'finalidade' => ['required', 'string', 'max:120'],
        ];
    }
}
```

- [ ] **Step 4: Enriquecer o resource**

Em `ResponsavelParticipanteResource`, adicionar depois de `'tipo_relacao'`:

```php
            'participante' => $this->resource->relationLoaded('participante') && $this->resource->participante !== null
                ? [
                    'id' => (string) $this->resource->participante->id,
                    'nome' => (string) $this->resource->participante->nome,
                    'matricula' => (string) $this->resource->participante->matricula,
                ]
                : null,
```

- [ ] **Step 5: Implementar a rota e a action**

Em `routes/api.php`, antes da rota `PATCH guardian-requests/{id}`:

```php
        Route::get('guardian-requests', [GuardianController::class, 'pedidos'])
            ->name('guardian-requests.index');
```

Em `GuardianController`, adicionar:

```php
    public function pedidos(ListarVinculosRequest $request): JsonResponse
    {
        $validacao = (string) ($request->validated('validacao') ?? 'pendente');
        $vinculos = $this->vinculos->listar(
            (string) $request->user()?->getAuthIdentifier(),
            $this->contexto($request),
            $validacao,
        );

        return response()->json([
            'data' => ResponsavelParticipanteResource::collection($vinculos)->resolve($request),
        ]);
    }
```

O controller precisa de `GerirVinculoResponsavel` no construtor; se já não houver, injetá-lo como primeiro parâmetro.

- [ ] **Step 6: Rodar e ver passar**

Run: `docker compose run --rm -T app php artisan test --filter=ListarVinculos`
Expected: PASS (todos, inclusive os 6 da Task 2).

- [ ] **Step 7: Gates**

Run: `docker compose run --rm -T app vendor/bin/pint app/Http app/Domain/Participantes tests/Feature/Participantes && docker compose run --rm -T app composer phpstan`
Expected: PASS / `No errors`.
Run: `docker compose run --rm -T app php artisan test`
Expected: todos verdes.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Http backend/routes/api.php backend/tests/Feature/Participantes/ListarVinculosHttpTest.php
git commit -m "feat(api): GET /guardian-requests com finalidade e filtro de validacao (RF-03)"
```

---

### Task 4: Escopo territorial no `decidir`

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/GuardianRequestController.php`
- Modify: `backend/tests/Feature/Participantes/VinculoResponsavelTest.php`

**Interfaces:**
- Consumes: `AutorizacaoParticipante::pode(?ContextoAtuacao, string, Participante, string)`; `GerirParticipante::encontrar(string)`.
- Produces: `PATCH /guardian-requests/{id}` responde 404 quando o participante do vínculo está fora do território do ator.

- [ ] **Step 1: Escrever os testes**

Anexar a `VinculoResponsavelTest.php`:

```php
it('coordenadora nao decide vinculo de participante fora do seu territorio', function () {
    ['igreja' => $outraIgreja] = igrejaComEbi();
    $alheio = participanteNaIgreja($outraIgreja);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $alheio->id, 'validacao' => 'pendente',
    ]);

    ['igreja' => $minhaIgreja, 'ebi' => $minhaEbi] = igrejaComEbi();
    $coord = criarUsuarioComPapel('coordenadora', 'ebi', $minhaEbi->id);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], [
        'Authorization' => 'Bearer '.$coord->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$minhaEbi->id}",
    ])->assertStatus(404);

    expect($vinculo->fresh()->validacao)->toBe('pendente');
});

it('coordenadora decide vinculo do seu territorio', function () {
    $p = participanteNaIgreja($this->igreja ?? igrejaComEbi()['igreja']);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => $p->id, 'validacao' => 'pendente',
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], $this->authCoord)
        ->assertOk()->assertJsonPath('validacao', 'aprovada');
});
```

Ajuste o `beforeEach` do arquivo para criar `$this->igreja`/`$this->ebi` via `igrejaComEbi()` e a coordenadora com esse `ebi->id`, mantendo os testes existentes verdes (eles usam `$this->authCoord` e um vínculo de participante sem território).

**Atenção:** os testes antigos criam `ResponsavelParticipante::factory()` sem participante territorial. Se o novo gate barrá-los, ajuste-os para criar o participante com `participanteNaIgreja($this->igreja)` e o vínculo apontando para ele. Não relaxe o gate.

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose run --rm -T app php artisan test --filter=VinculoResponsavelTest`
Expected: FAIL no teste "nao decide vinculo ... fora do seu territorio" (hoje retorna 200).

- [ ] **Step 3: Aplicar o gate no controller**

`GuardianRequestController` passa a receber `GerirParticipante $participantes` e `AutorizacaoParticipante $autorizacao` no construtor. Em `update`, antes de `decidir`:

```php
        $vinculo = $this->vinculos->encontrar($id);
        $participante = $vinculo !== null
            ? $this->participantes->encontrar((string) $vinculo->participante_id)
            : null;

        if ($vinculo === null || $participante === null
            || ! $this->autorizacao->pode($this->contexto($request), (string) $request->user()?->getAuthIdentifier(), $participante, 'vinculo.gerenciar')) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vínculo não encontrado.');
        }
```

`pode()` exige o model `Participante`, e `GerirParticipante::encontrar(string): ?Participante` já devolve exatamente isso. Não é preciso criar `GerirVinculoResponsavel::encontrar` — busque o vínculo com `ResponsavelParticipante::query()->find()`? **Não**: controllers não tocam models (ADR-0002). Adicione ao contrato:

`GerirVinculoResponsavel::encontrar(string $id): ?ResponsavelParticipante`

e implemente em `GerenciadorVinculoResponsavel`:

```php
    public function encontrar(string $id): ?ResponsavelParticipante
    {
        return ResponsavelParticipante::query()->find($id);
    }
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose run --rm -T app php artisan test --filter=VinculoResponsavelTest`
Expected: PASS.

- [ ] **Step 5: Gates**

Run: `docker compose run --rm -T app php artisan test && docker compose run --rm -T app vendor/bin/pint --test && docker compose run --rm -T app composer phpstan`
Expected: tudo verde.

- [ ] **Step 6: Commit**

```bash
git add backend/app backend/tests/Feature/Participantes/VinculoResponsavelTest.php
git commit -m "fix(participantes): decidir vinculo exige territorio do participante (RF-03)"
```

---

### Task 5: Regenerar o cliente e reescrever `Vinculos.tsx`

**Files:**
- Modify: `frontend/src/api/schema.d.ts` (gerado)
- Modify: `frontend/src/pages/Vinculos.tsx`
- Modify: `frontend/src/components/layout/ListaNav.tsx` (papéis do item `vinculos`)

**Interfaces:**
- Consumes: `api.GET('/guardian-requests', { params: { query: { validacao, finalidade } }, headers: cabecalhos() })`; `api.PATCH('/guardian-requests/{id}', ...)`; `api.GET('/participants', { params: { query: { q, finalidade } } })`; `api.POST('/participants/{id}/guardians', ...)`.
- Produces: tela `Vinculos` com duas abas; aba *Solicitar* só para quem tem `participante.ler`.

O projeto **não tem** setup de teste de componente (nenhum `*.test.tsx`, sem vitest/jest no `package.json`). A verificação desta task é lint + build + o roteiro manual do Step 6; a lógica de recorte já está coberta pelos testes HTTP das Tasks 3 e 4.

- [ ] **Step 1: Regenerar o cliente**

Run (em `frontend/`): `npm run gen:api`
Expected: `src/api/schema.d.ts` contém `VinculoParticipanteResumo` e a operação `/guardian-requests`.

- [ ] **Step 2: Conferir o tipo do payload**

Run: `npx tsc --noEmit` (em `frontend/`)
Expected: sem erros.

- [ ] **Step 3: Escrever a tela**

Substituir `frontend/src/pages/Vinculos.tsx` por uma tela com `Tabs` (shadcn) e:

- Estado `aba: 'pedidos' | 'solicitar'`.
- `carregar()` chama `GET /guardian-requests` com `validacao` do seletor (default `pendente`) e `finalidade` fixa `'triage_vinculo'`; guarda em `pedidos`; tolera array puro ou `{data}`.
- Tabela de pedidos com colunas: Criança (`pedido.participante?.nome` + `matricula`), Responsável (`pedido.nome`), Relação (`rotuloStatus(pedido.tipo_relacao)`), Situação (`rotuloStatus(pedido.validacao)`), Solicitado em (`formatarData(pedido.inicio)`), Ações.
- Ações: botões *Aprovar*, *Recusar*, *Pedir documento*, *Revogar*, cada um chamando `decidir(id, decisao)` e recarregando a lista; desabilitados quando `ocupado`.
- A aba *Solicitar* aparece apenas quando o usuário tem `participante.ler` (derive dos escopos já carregados em `App`/`ListaNav`; exporte o helper se necessário). Contém: campo de busca → `GET /participants?q=&finalidade=cadastro_vinculo`, lista de resultados clicáveis, campos relação e telefone, botão *Solicitar vínculo* chamando `POST /participants/{id}/guardians`.
- Nenhum `Input` de ULID permanece.
- Estado vazio com `EstadoTabela` (`mensagemVazio="Nenhum pedido de vínculo."`).
- Erros com `Alert destructive` + `lerErro`.

- [ ] **Step 4: Ajustar a navegação**

Em `ListaNav.tsx`, o item `vinculos` passa a ter `papeis: []` (todos veem a tela; a aba Solicitar se esconde sozinha e o servidor recusa o que for indevido). Mantenha `responsavel` no item `dependentes`.

- [ ] **Step 5: Gates**

Run (em `frontend/`): `npm run lint && npm run build`
Expected: lint sem erros (avisos pré-existentes ok) e build OK, gerando `backend/public/spa`.

- [ ] **Step 6: Verificação manual com dados reais**

Com o contêiner no ar, autentique como `coordenadora@ebi.test` (TOTP demo) e:
1. Abra *Vínculos* → aba *Pedidos* deve listar os pedidos pendentes do território com nome/matrícula da criança (não ULID).
2. Clique *Aprovar* num pedido; a linha sai da lista `pendente`.
3. Abra *Meus dependentes*; o participante aprovado aparece.
4. Aba *Solicitar*: busque "Patota", selecione, envie; o pedido novo aparece em *Pedidos*.

- [ ] **Step 7: Commit**

```bash
git add frontend/src/api/schema.d.ts frontend/src/pages/Vinculos.tsx frontend/src/components/layout/ListaNav.tsx backend/public/spa
git commit -m "feat(web): tela de vinculos com pedidos e solicitacao por nome (RF-03)"
```

---

## Self-Review

- **Spec coverage:** listagem dupla (Tasks 2–3), `404` no `decidir` (Task 4), `participante` no recurso (Tasks 1, 3), tela em duas abas sem ULID (Task 5), aba Solicitar só com `participante.ler` (Task 5), filtro `validacao` + `finalidade` (Tasks 1, 3). Coberto.
- **Step scan:** cada step tem um resultado verificável (teste falha/passa, comando com saída esperada).
- **Type consistency:** `listar(string, ?ContextoAtuacao, string): array` é usado no contrato (Task 2), no controller (Task 3) e nos testes; `validacao` é o nome em contrato, domínio, HTTP e UI.
- **Review Focus:** os cinco pontos têm teste — #2 e #5 na Task 2; #1 na Task 4; #3 na Task 2; #4 na Task 5 (estado vazio).
- **Proportion:** o plano descreve assinaturas e asserts; os corpos de implementação que exigem decisão (o filtro duplo) aparecem completos porque são o coração da correção.
