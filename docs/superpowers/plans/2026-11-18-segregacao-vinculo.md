# Segregação de funções no vínculo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Impedir que quem abriu um pedido de vínculo o aprove: o domínio recusa o autor com 403 em qualquer decisão, o gate da rota passa a exigir território (não propriedade) e a tela mostra "Aguardando outro operador" na linha dele.

**Architecture:** A regra vive em `GerenciadorVinculoResponsavel::decidir`, dentro da transação que carrega o vínculo, comparando `usuario_id` com o ator e lançando `ProblemException` 403 antes de qualquer mutação. O controller troca a checagem `pode()` (que libera por propriedade) por `cobreTerritorio()` estrita. No front, `Vinculos` recebe `usuarioId: string | null` e troca os botões por um texto quando a linha é de autoria do usuário.

**Tech Stack:** Laravel ^13 + Pest + PHPStan + Pint (contêiner Docker); React 19 + TS + shadcn/ui; contrato `docs/04-api/openapi.yaml` (CRLF) e `schema.d.ts` gerado. **Sem testes de frontend**: `frontend/package.json` não tem script de teste; a Task 3 termina em lint + build + verificação visual manual.

**Spec:** `docs/superpowers/specs/2026-11-18-segregacao-vinculo-design.md`

## Global Constraints

- PHP roda **sempre** no contêiner: `docker compose exec -T app <cmd>` (contêiner já no ar; `docker compose run --rm -T app` quando parado).
- Nunca use `Set-Content`/`Out-File` para editar PHP/TS/YAML — corrompe encoding. Use a ferramenta de edição.
- `docs/04-api/openapi.yaml` está em **CRLF**. Preserve.
- `frontend/src/api/schema.d.ts` é **gerado** (`npm run gen:api`), nunca editado à mão.
- Fronteira modular (ADR-0002): a regra fica no módulo `Participantes`, que já lança `ProblemException`.
- Mensagens e rótulos de UI em português do Brasil.
- Commits só com os arquivos da task, na convenção do repo (`feat(...)`, `fix(...)`).
- Helpers de teste já existentes: `igrejaComEbi()`, `participanteNaIgreja(Igreja)`, `criarUsuarioComPapel(papel, tipoEscopo, escopoId)` — em `backend/tests/Pest.php`.

## Review Focus

1. **Autor tentando decidir o próprio pedido** — 403 com `code = acesso_negado` e mensagem que explica o fluxo; nunca 200 e nunca 404 (o vínculo está na lista dele). Vale para `aprovar`, `recusar`, `pedir_documento`, `revogar` e `contestar`.
2. **Vínculo intocado após a recusa** — `validacao`, `conferido_por`, `conferido_em`, `fim` e `updated_at` inalterados; nenhuma linha nova em `SolicitacaoVinculo`; nenhum evento `vinculo.decidido` na auditoria.
3. **Território estrito** — decidir exige `cobreTerritorio` com `vinculo.gerenciar`. Usuário responsável pela criança mas cujo escopo de coordenação é de outra EBI recebe 404 (a checagem atual `pode()` deixaria passar).
4. **Outro operador do mesmo território** — aprova normalmente, sem regressão.
5. **Vínculo sem dono (`usuario_id = null`)** — continua aprovável por qualquer operador do território (cadastro assistido).
6. **Listagem sem regressão** — o autor continua vendo o próprio pedido; `ListarVinculosTest` e `ListarVinculosHttpTest` rodam no gate.
7. **Tela** — linha própria em andamento (`pendente`/`aguardando_documento`) mostra "Aguardando outro operador"; linha própria em estado final (`aprovada`/`recusada`/encerrada) fica sem ações; sem `usuarioId`, nenhuma linha é tratada como própria.

---

### Task 1: Recusa do autor em `decidir`

**Files:**
- Modify: `backend/app/Domain/Participantes/Services/GerenciadorVinculoResponsavel.php:75-79`
- Modify: `backend/app/Domain/Participantes/Contracts/GerirVinculoResponsavel.php` (docblock de `decidir`)
- Test: `backend/tests/Feature/Participantes/VinculoResponsavelTest.php`

**Interfaces:**
- Consumes: `ResponsavelParticipante::query()->find()`, `App\Support\ProblemException(string $codigo, string $titulo, int $status, string $detalhe)`.
- Produces: `PATCH /guardian-requests/{id}` responde **403** `acesso_negado` quando `vinculo.usuario_id` existe e é igual ao ator. Nenhuma assinatura muda.

- [ ] **Step 1: Escrever os testes do domínio**

Anexar a `VinculoResponsavelTest.php`. O teste de domínio chama o serviço direto (não passa pelo controller), para que mover a proteção para a camada HTTP quebre a suíte:

```php
use App\Domain\Participantes\Contracts\GerirVinculoResponsavel;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

it('o dominio recusa o autor decidindo o proprio pedido', function () {
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);
    $antes = $vinculo->fresh()->toArray();
    $logsAntes = SolicitacaoVinculo::query()->count();
    $auditoriaAntes = DB::table('eventos_auditoria')->count();

    $servico = app(GerirVinculoResponsavel::class);

    expect(fn () => $servico->decidir((string) $this->coordenadora->id, $vinculo->id, 'aprovar'))
        ->toThrow(function (ProblemException $e) {
            expect($e->status)->toBe(403)
                ->and($e->problemCode)->toBe('acesso_negado');
        });

    expect($vinculo->fresh()->toArray())->toBe($antes)
        ->and(SolicitacaoVinculo::query()->count())->toBe($logsAntes)
        ->and(DB::table('eventos_auditoria')->count())->toBe($auditoriaAntes);
});
```

> `ProblemException` expõe `problemCode` e `status` como propriedades públicas (`backend/app/Support/ProblemException.php:15-23`).

- [ ] **Step 2: Escrever os testes HTTP**

```php
it('autor recebe 403 com acesso_negado em qualquer decisao', function (string $decisao) {
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => $decisao], $this->authCoord)
        ->assertStatus(403)
        ->assertJsonPath('code', 'acesso_negado');

    $atual = $vinculo->fresh();
    expect($atual->validacao)->toBe(ResponsavelParticipante::VALIDACAO_PENDENTE)
        ->and($atual->conferido_por)->toBeNull()
        ->and($atual->conferido_em)->toBeNull()
        ->and($atual->fim)->toBeNull();
})->with(['aprovar', 'recusar', 'pedir_documento', 'revogar', 'contestar']);

it('autor nao revoga o proprio vinculo ja aprovado', function () {
    $vinculo = ResponsavelParticipante::factory()->aprovado()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => $this->coordenadora->id,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'revogar'], $this->authCoord)
        ->assertStatus(403);

    expect($vinculo->fresh()->fim)->toBeNull();
});

it('outro operador do territorio aprova o pedido do autor', function () {
    $outraCoordenadora = criarUsuarioComPapel('coordenadora', 'ebi', $this->ebi->id);
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => $this->coordenadora->id,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], [
        'Authorization' => 'Bearer '.$outraCoordenadora->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ])->assertOk()->assertJsonPath('validacao', 'aprovada');

    expect($vinculo->fresh()->conferido_por)->toBe($outraCoordenadora->id);
});

it('vinculo sem dono continua aprovavel por operador do territorio', function () {
    $vinculo = ResponsavelParticipante::factory()->create([
        'participante_id' => participanteNaIgreja($this->igreja)->id,
        'usuario_id' => null,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$vinculo->id}", ['decisao' => 'aprovar'], $this->authCoord)
        ->assertOk()->assertJsonPath('validacao', 'aprovada');
});
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=VinculoResponsavelTest`
Expected: FAIL nos testes do autor (200 recebido em vez de 403 / sem exceção). Os testes de "outro operador" e "sem dono" passam.

- [ ] **Step 4: Aplicar a regra em `decidir`**

Dentro do `DB::transaction`, imediatamente depois do `find` do vínculo (linha ~77) e **antes** do bloco `if ($encerraVinculo)`:

```php
            // Segregacao de funcoes: quem abriu o pedido nao o decide.
            if ($atorId !== '' && $vinculo->usuario_id !== null && $vinculo->usuario_id === $atorId) {
                throw new ProblemException(
                    'acesso_negado',
                    'Acesso negado',
                    403,
                    'A decisão deve ser feita por outro operador do seu território.',
                );
            }
```

Atualizar o docblock de `decidir` em `GerirVinculoResponsavel` com: "O autor do pedido não pode decidir o próprio vínculo, em nenhuma decisão (segregação de funções); nesse caso lança 403 `acesso_negado`."

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter='VinculoResponsavelTest|ListarVinculos'`
Expected: PASS em todos (incluindo o novo teste de domínio, os 6 casos de decisão e as duas suítes de listagem).

- [ ] **Step 6: Gates**

Run: `docker compose exec -T app vendor/bin/pint --test && docker compose exec -T app composer phpstan`
Expected: Pint `PASS`, PHPStan `[OK] No errors`.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Domain/Participantes backend/tests/Feature/Participantes/VinculoResponsavelTest.php
git commit -m "fix(participantes): autor nao decide o proprio pedido de vinculo (RF-03)"
```

---

### Task 2: Gate da rota passa a exigir território, não propriedade

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/GuardianRequestController.php:36-41`
- Test: `backend/tests/Feature/Participantes/VinculoResponsavelTest.php`

**Interfaces:**
- Consumes: `AutorizacaoParticipante::cobreTerritorio(?ContextoAtuacao, Participante, string $operacaoTerritorial): bool` (`backend/app/Support/Participantes/AutorizacaoParticipante.php:76`).
- Produces: quem decide precisa de `vinculo.gerenciar` sobre o território do participante; propriedade não basta. Fora do território → **404**.

- [ ] **Step 1: Escrever o teste que falha hoje**

```php
it('responsavel do participante nao decide vinculo so por propriedade', function () {
    ['igreja' => $igrejaB, 'ebi' => $ebiB] = igrejaComEbi();
    $crianca = participanteNaIgreja($igrejaB);

    // O mesmo usuario e responsavel (aprovado) pela crianca da EBI B e
    // coordenador da EBI A. Ele tem a operacao no escopo A, mas nao cobre B.
    $usuario = criarUsuarioComPapel('responsavel', 'central', 'c1');
    ResponsavelParticipante::factory()->aprovado()->create([
        'participante_id' => $crianca->id,
        'usuario_id' => $usuario->id,
    ]);
    VinculoAcesso::factory()->create([
        'usuario_id' => $usuario->id,
        'papel_id' => Papel::query()->where('codigo', 'coordenadora')->value('id'),
        'tipo_escopo' => 'ebi',
        'escopo_id' => $this->ebi->id,
    ]);

    $pedido = ResponsavelParticipante::factory()->create([
        'participante_id' => $crianca->id,
        'usuario_id' => null,
        'validacao' => ResponsavelParticipante::VALIDACAO_PENDENTE,
    ]);

    $this->patchJson("/api/v1/guardian-requests/{$pedido->id}", ['decisao' => 'aprovar'], [
        'Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken,
        'X-Acting-Scope' => "papel=coordenadora;escopo=ebi:{$this->ebi->id}",
    ])->assertStatus(404);

    expect($pedido->fresh()->validacao)->toBe(ResponsavelParticipante::VALIDACAO_PENDENTE);
});
```

Import necessário no topo do arquivo: `use App\Domain\Identidade\Models\Papel;` e `use App\Domain\Identidade\Models\VinculoAcesso;`.

> O middleware `ResolveActingScope` recusa escopo declarado que não corresponda a vínculo vigente (`ResolvedorContextoAtuacao.php:63-75`), por isso o vínculo de acesso **precisa existir**. Contra o código atual este teste deve falhar com **200** (liberação por propriedade em `AutorizacaoParticipante::pode`, :64-66); após a Task 2, 404.

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=VinculoResponsavelTest`
Expected: FAIL no teste novo (recebeu 200).

- [ ] **Step 3: Trocar a checagem**

Em `GuardianRequestController::update`:

```php
        if ($vinculo === null || $participante === null
            || ! $this->autorizacao->cobreTerritorio($this->contexto($request), $participante, Operacoes::VINCULO_GERENCIAR)) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vínculo não encontrado.');
        }
```

Atualizar o comentário acima do bloco: decidir é ato de coordenação sobre o território do participante; ser responsável pela criança não autoriza decidir pedido de outrem.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter='VinculoResponsavelTest|ListarVinculos'`
Expected: PASS. Se algum teste antigo (ex.: coordenadora decidindo sem escopo territorial configurado) quebrar, **investigar** — não reverter para `pode()`.

- [ ] **Step 5: Gates e commit**

```bash
docker compose exec -T app vendor/bin/pint --test && docker compose exec -T app composer phpstan
git add backend/app/Http/Controllers/Api/V1/GuardianRequestController.php backend/tests/Feature/Participantes/VinculoResponsavelTest.php
git commit -m "fix(participantes): decidir vinculo exige territorio, nao propriedade (RF-03)"
```

---

### Task 3: Contrato documenta o 403 e o gate territorial

**Files:**
- Modify: `docs/04-api/openapi.yaml` (operação `patch` de `/guardian-requests/{id}`)

- [ ] **Step 1: Documentar na operação**

No `patch:` de `/guardian-requests/{id}`, garantir (ou criar) o `description`:

```yaml
      description: >-
        Decide um pedido de vínculo. Exige `vinculo.gerenciar` sobre o
        território do participante; fora dele responde 404 (ser responsável
        pela criança não basta). O autor do pedido não pode decidir o próprio
        vínculo: nesse caso responde 403 `acesso_negado`, e a decisão deve
        ficar com outro operador do mesmo território.
```

- [ ] **Step 2: Validar**

Run (no host): `npx --yes @stoplight/spectral-cli lint docs/04-api/openapi.yaml --ruleset scripts/.spectral.yaml`
Expected: sem erros novos (avisos pré-existentes aceitáveis). Confirmar que o arquivo continua em CRLF (`git diff --stat` sem reescrever o arquivo inteiro).

- [ ] **Step 3: Commit**

```bash
git add docs/04-api/openapi.yaml
git commit -m "docs(api): PATCH guardian-requests recusa o autor com 403 (RF-03)"
```

---

### Task 4: Tela não oferece decisão na linha do autor

**Files:**
- Modify: `frontend/src/pages/Vinculos.tsx:110-118` (ações) e a célula de ações (~linha 254)
- Modify: `frontend/src/App.tsx:162`

**Interfaces:**
- Consumes: `pedido.usuario_id?: string | null` (`schema.d.ts:3966`), `pedido.fim`, `pedido.validacao`.
- Produces: `Vinculos({ escopos, usuarioId }: { escopos: ContextoAtuacao[]; usuarioId: string | null })`; `usuarioId === null` nunca marca linha como própria.

- [ ] **Step 1: Passar o id do usuário**

Em `App.tsx:162`:

```tsx
      {tela === 'vinculos' && <Vinculos escopos={escopos} usuarioId={usuario.id ?? null} />}
```

(`Usuario.id` é opcional em `schema.d.ts:3666`; o `?? null` mantém o tipo honesto.)

- [ ] **Step 2: Ajustar o componente**

Em `Vinculos.tsx`:

1. Assinatura: `export function Vinculos({ escopos, usuarioId }: { escopos: ContextoAtuacao[]; usuarioId: string | null })`.
2. Helper puro ao lado de `acoesDoPedido`:

```tsx
/**
 * Linha de autoria do usuário: ele vê o pedido, mas a decisão é de outro
 * operador. Só vale com identificadores presentes — `undefined === undefined`
 * marcaria linhas alheias como próprias.
 */
function ehDoUsuario(pedido: Vinculo, usuarioId: string | null): boolean {
  return usuarioId !== null && !!pedido.usuario_id && pedido.usuario_id === usuarioId
}

/** Pedido ainda admite decisão do autor? Estado final não pede espera. */
function aguardaOutroOperador(pedido: Vinculo): boolean {
  return !pedido.fim && (pedido.validacao === 'pendente' || pedido.validacao === 'aguardando_documento')
}
```

3. Na célula de ações, antes de `acoesDoPedido(pedido)`:

```tsx
{ehDoUsuario(pedido, usuarioId)
  ? aguardaOutroOperador(pedido) && (
      <span className="text-xs text-muted-foreground">Aguardando outro operador</span>
    )
  : acoesDoPedido(pedido).map(/* botões existentes */)}
```

Linha própria já aprovada/recusada/encerrada fica sem texto e sem botões.

- [ ] **Step 3: Gates**

Run (em `frontend/`): `npm run lint && npm run build`
Expected: lint sem erros novos (avisos pré-existentes ok), build OK.

- [ ] **Step 4: Verificação visual (manual, registrar no comentário do PR)**

Com a SPA rebuildada e `php artisan optimize:clear`:
1. Coordenadora abre um pedido dela mesma → linha mostra "Aguardando outro operador", sem botões.
2. Mesma tela, pedido de responsável → botões de decisão presentes e funcionando.
3. Pedido próprio já aprovado → célula vazia.
4. Responsável logado → lista só de leitura, como antes.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/App.tsx frontend/src/pages/Vinculos.tsx
git commit -m "feat(web): linha do proprio pedido aguarda outro operador (RF-03)"
```

---

## Self-Review

- **Spec coverage:** 403 em todas as decisões no domínio (Task 1), mensagem "outro operador" (Task 1), gate territorial estrito (Task 2), contrato (Task 3), UI com estados e `usuarioId` opcional (Task 4), `usuario_id = null` livre (Task 1), dívida do cadastro assistido corrigida no design (sem código).
- **Step scan:** cada step tem comando e resultado verificável; nenhum teste foi escrito de forma que passe sem a implementação (o de domínio chama o serviço; o de território falha com 200 antes da correção).
- **Type consistency:** `usuarioId: string | null` no front, `usuario_id !== null` no back; `decidir` mantém a assinatura.
- **Review Focus:** #1/#2/#5 na Task 1, #3 na Task 2, #5/#6 no gate da Task 1 (listagens rodam), #7 na Task 4.
- **Proportion:** 4 tasks, ~10 linhas de produção no backend e ~15 no front; o resto é teste e verificação.
