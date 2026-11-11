# Frontend: identidade visual, tema claro/escuro e perfil do usuário — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar ao portal identidade visual própria (casca de aplicação + shadcn), tema claro/escuro com botão persistido e uma tela de perfil onde o usuário logado edita nome, e-mail, senha e (re)configura o MFA.

**Architecture:** Backend Laravel primeiro (contrato OpenAPI → catálogo → código, com TDD em Pest): `PATCH /me` (nome/e-mail), `POST /me/password` (senha autenticada) e `mfa_ativo` no `GET /me`, mais o serviço de MFA que sustenta "renovar". Depois o frontend React: tema por classe `.dark` com script inline anti-flash, layout de aplicação (sidebar/gaveta) e as telas de login e perfil migradas para shadcn.

**Tech Stack:** PHP 8.5 / Laravel (Octane) · Pest · Sanctum · React 19 + TypeScript + Vite · Tailwind CSS v4 · shadcn/ui (`new-york`, lucide) · cliente `openapi-fetch` gerado do OpenAPI.

**Spec:** `docs/superpowers/specs/2026-11-11-frontend-identidade-e-perfil-design.md`

## Global Constraints

- Idioma: todo texto de UI, comentários e mensagens de erro em **português**.
- Backend roda por `docker compose run --rm app <cmd>`; frontend sempre com Node do host em `frontend/` (nunca Docker).
- Ordem de contrato obrigatória: **`docs/04-api/openapi.yaml` → `docs/04-api/endpoints.md` → código**; a conferência `scripts/check-endpoints.py` precisa terminar com `OK:` e não aumentar o número de divergências. Como Python não está no PATH do host, rodar via Docker (o mesmo do CI/CONTRIBUTING):
  `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"`
- O cliente do frontend é **gerado**: nunca editar `frontend/src/api/schema.d.ts` à mão; rodar `npm run gen:api` em `frontend/`.
- Erros da API no formato **RFC 9457** (problem+json) via `App\Support\ProblemException`; o `detail` é a mensagem exibida ao usuário.
- Senha: mínimo **12** caracteres (mesma regra de `auth/password/reset`).
- Sem runner de teste no frontend (ADR-0022): frontend valida por `npm run lint` + `npm run build` (+ verificação manual na interface).
- Alvo WCAG 2.2 AA: contraste, foco visível, navegação por teclado, alvos de toque (RNF-05).
- `--primary` da marca continua `#aa3bff` (claro) / `#c084fc` (escuro).
- Containers `app`/`horizon`/`scheduler` rodam Octane e **não recarregam** código; reiniciar após mudanças de PHP (o plano indica onde).

## Review Focus

- **E-mail já usado por outro usuário** em `PATCH /me`: precisa ser **422** (não 500 nem 409), com `detail` de "e-mail em uso"; o formulário mostra o erro no campo.
- **Senha atual incorreta** em `POST /me/password`: precisa ser **422** (não 403/500) com `detail` acionável; a sessão atual **continua válida** (não desloga o próprio usuário).
- **Renovar MFA com fator já confirmado**: reinscrever não pode estourar a `unique index` parcial `fator_mfa_confirmado_unique` (usuário, tipo, `confirmado_em IS NOT NULL`); confirmar o novo precisa revogar o antigo e invalidar os códigos de recuperação antigos.
- **`/me` para quem não tem e-mail verificado / não tem papel**: `mfa_ativo` precisa vir `false` (nunca ausente ou erro), e `GET /me` continua respondendo o mesmo payload + o campo novo.
- **`PATCH /me` reenviando o próprio e-mail** (sem alteração): não pode dar 422 por "e-mail em uso" (o próprio usuário não conflita consigo mesmo).

---

### Task 1: `mfa_ativo` no contrato e no `GET /me`

**Files:**
- Modify: `docs/04-api/openapi.yaml` (schema `Usuario`, ~linha 1344)
- Modify: `docs/04-api/endpoints.md` (linha do `GET /me`)
- Modify: `backend/app/Http/Resources/UsuarioResource.php`
- Modify: `backend/app/Http/Controllers/Api/V1/MeController.php`
- Test: `backend/tests/Feature/Identidade/MePerfilTest.php` (criar)

**Interfaces:**
- Consumes: `App\Domain\Identidade\Contracts\ExigirMfa::temFatorValidado(Usuario): bool` (já existe; implementado por `GerenciadorMfa`).
- Produces: `GET /api/v1/me` devolve `mfa_ativo: boolean` além dos campos atuais; tipo `components['schemas']['Usuario']['mfa_ativo']` no `schema.d.ts` regenerado.

- [ ] **Step 1: Write the failing test**

Em `backend/tests/Feature/Identidade/MePerfilTest.php`:

```php
<?php

use App\Domain\Identidade\Models\FatorMfa;

use function Pest\Laravel\getJson;

it('expoe mfa_ativo falso quando nao ha fator confirmado', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    getJson('/api/v1/me', $auth)
        ->assertOk()
        ->assertJsonPath('mfa_ativo', false);
});

it('expoe mfa_ativo verdadeiro com fator confirmado', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    FatorMfa::query()->create([
        'usuario_id' => $usuario->id,
        'tipo' => FatorMfa::TIPO_TOTP,
        'segredo_cifrado' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
        'confirmado_em' => now(),
    ]);
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    getJson('/api/v1/me', $auth)
        ->assertOk()
        ->assertJsonPath('mfa_ativo', true);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app php artisan test --filter=MePerfilTest`
Expected: FAIL — a resposta não tem a chave `mfa_ativo`.

- [ ] **Step 3: Atualizar o contrato OpenAPI**

Em `docs/04-api/openapi.yaml`, no schema `Usuario` (bloco `components.schemas.Usuario`), adicionar a propriedade depois de `papeis`:

```yaml
        mfa_ativo:
          type: boolean
          description: Indica se o usuario tem fator TOTP confirmado.
```

Em `docs/04-api/endpoints.md`, na linha do `GET /me` da seção de identidade, manter a marca `(no OpenAPI)` (não muda método/rota; só o schema). Confirmar que a linha continua presente.

- [ ] **Step 4: Implementar `mfa_ativo` no recurso**

Em `backend/app/Http/Resources/UsuarioResource.php`, adicionar o campo ao `toArray()` (a partir de `mfa_ativo` passado no resource, para não importar o domínio do módulo — respeita a fronteira modular):

```php
            'mfa_ativo' => (bool) ($this->resource->mfa_ativo ?? false),
```

Em `backend/app/Http/Controllers/Api/V1/MeController.php`, injetar `ExigirMfa` e setar o atributo antes de montar o resource:

```php
use App\Domain\Identidade\Contracts\ExigirMfa;
```

construtor:

```php
    public function __construct(
        private readonly ConsultarVinculos $vinculos,
        private readonly ExigirMfa $mfa,
    ) {}
```

`me()`:

```php
    public function me(Request $request): UsuarioResource
    {
        $usuario = $this->usuario($request);
        $usuario->loadMissing('vinculosVigentes.papel');
        $usuario->setAttribute('mfa_ativo', $this->mfa->temFatorValidado($usuario));

        return UsuarioResource::make($usuario);
    }
```

- [ ] **Step 5: Rodar o check-endpoints e o teste**

Run: `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"`
Expected: `OK: <n> operações alinhadas.` (mesmo `n` de antes — só o schema mudou).

Run: `docker compose run --rm app php artisan test --filter=MePerfilTest`
Expected: PASS (2 testes).

- [ ] **Step 6: Regenerar o cliente e validar**

No host, em `frontend/`:

```bash
npm run gen:api
npm run lint
```

Expected: `gerado src/api/schema.d.ts`; lint sem erros. Conferir que `Usuario` em `src/api/schema.d.ts` agora tem `mfa_ativo?: boolean`.

- [ ] **Step 7: Commit**

```bash
git add docs/04-api/openapi.yaml docs/04-api/endpoints.md backend/app/Http/Resources/UsuarioResource.php backend/app/Http/Controllers/Api/V1/MeController.php backend/tests/Feature/Identidade/MePerfilTest.php frontend/src/api/schema.d.ts
git commit -m "feat(identidade): expõe mfa_ativo no GET /me (E03)"
```

---

### Task 2: `PATCH /me` — editar nome e e-mail do próprio usuário

**Files:**
- Modify: `docs/04-api/openapi.yaml` (novo path `/me` com `patch` + schema `AtualizarPerfil`)
- Modify: `docs/04-api/endpoints.md`
- Create: `backend/app/Http/Requests/Identidade/PerfilAtualizarRequest.php`
- Modify: `backend/app/Http/Controllers/Api/V1/MeController.php`
- Create: `backend/app/Domain/Identidade/Contracts/GerirPerfil.php`
- Create: `backend/app/Domain/Identidade/Services/GerenciadorPerfil.php`
- Modify: `backend/app/Providers/` (bind do contrato — ver Step 5)
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Identidade/MePerfilTest.php` (append)

**Interfaces:**
- Consumes: `RegistrarEvento::registrar(array): EventoAuditoria`; `Usuario::emailNormalizado(string): string`.
- Produces: `GerirPerfil::atualizar(Authenticatable $usuario, array $dados): object` — grava nome/e-mail normalizado, reverifica e-mail alterado (`email_verificado_em = null`) e audita; lança `ProblemException('email_em_uso', ..., 422)` em conflito. Rota `PATCH /api/v1/me`.

- [ ] **Step 1: Write the failing test**

Append em `backend/tests/Feature/Identidade/MePerfilTest.php`:

```php
use App\Domain\Auditoria\Models\EventoAuditoria;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\patchJson;

it('atualiza nome e e-mail do proprio usuario', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    patchJson('/api/v1/me', ['nome' => 'Maria Silva', 'email' => 'Maria@Exemplo.test'], $auth)
        ->assertOk()
        ->assertJsonPath('nome', 'Maria Silva')
        ->assertJsonPath('email', 'maria@exemplo.test');

    $usuario->refresh();
    expect($usuario->nome)->toBe('Maria Silva')
        ->and($usuario->email_normalizado)->toBe('maria@exemplo.test')
        ->and($usuario->email_verificado_em)->toBeNull(); // e-mail mudou: reverifica
});

it('mantem o e-mail verificado quando o e-mail nao muda', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    patchJson('/api/v1/me', ['nome' => 'Novo Nome', 'email' => $usuario->email_normalizado], $auth)
        ->assertOk();

    expect($usuario->refresh()->email_verificado_em)->not->toBeNull();
});

it('recusa e-mail ja usado por outro usuario com 422', function () {
    $ocupado = Usuario::factory()->create(['email_normalizado' => 'tomado@exemplo.test']);
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    patchJson('/api/v1/me', ['email' => 'tomado@exemplo.test'], $auth)
        ->assertStatus(422)
        ->assertJsonPath('code', 'email_em_uso');

    expect($usuario->refresh()->email_normalizado)->not->toBe('tomado@exemplo.test');
});

it('audita a alteracao de dados pessoais', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    patchJson('/api/v1/me', ['nome' => 'Nome Auditado'], $auth)->assertOk();

    expect(EventoAuditoria::query()->where('acao', 'alterar_perfil')->where('ator_id', $usuario->id)->exists())
        ->toBeTrue();
});

it('nega PATCH /me sem token', function () {
    patchJson('/api/v1/me', ['nome' => 'X'])->assertStatus(401);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app php artisan test --filter=MePerfilTest`
Expected: FAIL — 404/405 em `PATCH /api/v1/me` (rota inexistente).

- [ ] **Step 3: Atualizar o contrato OpenAPI e o catálogo**

Em `docs/04-api/openapi.yaml`, dentro do path `/me` (que hoje só tem `get`), adicionar `patch`:

```yaml
    patch:
      tags: [identity]
      summary: Atualizar meu perfil
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                nome: { type: string, maxLength: 255 }
                email: { type: string, format: email }
      responses:
        '200':
          description: Perfil atualizado
          content:
            application/json:
              schema: { $ref: '#/components/schemas/Usuario' }
        '401': { $ref: '#/components/responses/Problem' }
        '422': { $ref: '#/components/responses/Problem' }
```

Em `docs/04-api/endpoints.md`, adicionar (ou ajustar) a linha do `PATCH /me` na seção de identidade, no formato da tabela, com a marca `(no OpenAPI)`:

```markdown
| PATCH | `/me` | Atualizar meu perfil (`no OpenAPI`) | RF-05 |
```

- [ ] **Step 4: Implementar request, contrato e serviço**

`backend/app/Http/Requests/Identidade/PerfilAtualizarRequest.php`:

```php
<?php

namespace App\Http\Requests\Identidade;

use Illuminate\Foundation\Http\FormRequest;

class PerfilAtualizarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255'],
        ];
    }
}
```

`backend/app/Domain/Identidade/Contracts/GerirPerfil.php`:

```php
<?php

namespace App\Domain\Identidade\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Auto-gestao do proprio perfil (nome/e-mail) — RF-05. Auditoria obrigatoria
 * (RN-10). Diferente de GerirUsuario, nao usa contexto territorial: o alvo e
 * sempre o proprio ator autenticado.
 */
interface GerirPerfil
{
    /**
     * Atualiza nome e/ou e-mail do proprio usuario. E-mail duplicado lanca
     * ProblemException 422 (code `email_em_uso`). Devolve o usuario atualizado.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(Authenticatable $usuario, array $dados): object;
}
```

`backend/app/Domain/Identidade/Services/GerenciadorPerfil.php` — implementa o contrato. Regras: nome direto; e-mail normalizado; se o normalizado já pertence a **outro** usuário → `ProblemException('email_em_uso', 'E-mail em uso', 422, 'Este e-mail já está em uso.')`; se o e-mail normalizado mudou, zera `email_verificado_em`; audita `acao => 'alterar_perfil'`, `recurso => 'usuario'`, `ator_id => $usuario->id`, `origem => 'api'`, `escopo => null`, `metadados_minimizados` com a lista de campos alterados (nomes dos campos, **não** os valores de e-mail antigos/novos em claro). Envolver em `DB::transaction`.

- [ ] **Step 5: Bind do contrato e rota**

Localizar o arquivo de provider onde os contracts de identidade são amarrados hoje (ex.: `backend/app/Providers/AppServiceProvider.php` ou `backend/app/Domain/Identidade/` provider). Grep por `ConsultarVinculos::class` para achar o `bind`. Adicionar o par:

```php
$this->app->bind(GerirPerfil::class, GerenciadorPerfil::class);
```

Em `backend/routes/api.php`, dentro do grupo `auth:sanctum` (junto de `Route::get('me', ...)`, linha ~57), adicionar:

```php
Route::patch('me', [MeController::class, 'atualizarPerfil'])->name('me.update');
```

- [ ] **Step 6: Implementar a action no controller**

Em `MeController`: injetar `GerirPerfil` no construtor e adicionar:

```php
    public function atualizarPerfil(PerfilAtualizarRequest $request): UsuarioResource
    {
        $usuario = $this->gerirPerfil->atualizar(
            $this->usuario($request),
            $request->validated(),
        );

        return UsuarioResource::make($usuario);
    }
```

(o `UsuarioResource` lê `mfa_ativo`; no retorno do perfil ela pode vir `false`/ausente — aceitável, o cliente recarrega `/me`. Se preferir consistência, setar `mfa_ativo` como na Task 1.)

- [ ] **Step 7: Rodar testes e gates**

Run: `docker compose run --rm app php artisan test --filter=MePerfilTest`
Expected: PASS (7 testes).

Run: `docker compose run --rm app ./vendor/bin/phpstan analyse`
Expected: `[OK] No errors`.

Run: `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"`
Expected: `OK:` e a contagem sobe em 1.

- [ ] **Step 8: Regenerar o cliente e commit**

Em `frontend/`: `npm run gen:api` (agora `paths['/me']['patch']` existe).

```bash
git add docs/04-api/openapi.yaml docs/04-api/endpoints.md backend/app backend/routes/api.php backend/tests/Feature/Identidade/MePerfilTest.php frontend/src/api/schema.d.ts
git commit -m "feat(identidade): PATCH /me para nome e e-mail do próprio usuário (E03)"
```

---

### Task 3: `POST /me/password` — troca de senha autenticada

**Files:**
- Modify: `docs/04-api/openapi.yaml` (+ path `/me/password`)
- Modify: `docs/04-api/endpoints.md`
- Create: `backend/app/Http/Requests/Identidade/PerfilSenhaRequest.php`
- Modify: `backend/app/Domain/Identidade/Contracts/GerirPerfil.php`
- Modify: `backend/app/Domain/Identidade/Services/GerenciadorPerfil.php`
- Modify: `backend/app/Http/Controllers/Api/V1/MeController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Identidade/MePerfilTest.php` (append)

**Interfaces:**
- Consumes: `Authenticatable::currentAccessToken()` (Sanctum) para saber o token atual; `Hash::check/make`.
- Produces: `GerirPerfil::alterarSenha(Authenticatable $usuario, string $senhaAtual, string $novaSenha): void` — valida a senha atual (422 `senha_atual_invalida`), troca o hash, revoga **todos os tokens exceto o atual** e marca `sessao_login.revogada_em` das demais.

- [ ] **Step 1: Write the failing test**

Append em `backend/tests/Feature/Identidade/MePerfilTest.php`:

```php
use App\Domain\Identidade\Contracts\GerenciarSessao;

use function Pest\Laravel\postJson;

it('troca a senha mantendo a sessao atual viva', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $atual = $usuario->createToken('atual')->plainTextToken;
    $outraToken = $usuario->createToken('outra')->plainTextToken;
    $auth = ['Authorization' => 'Bearer '.$atual];

    postJson('/api/v1/me/password', [
        'senha_atual' => 'senha-bem-longa-123',
        'nova_senha' => 'outra-senha-longa-456',
        'nova_senha_confirmation' => 'outra-senha-longa-456',
    ], $auth)->assertNoContent();

    expect(Hash::check('outra-senha-longa-456', $usuario->refresh()->senha))->toBeTrue();

    // Sessão atual continua válida; a outra foi derrubada.
    getJson('/api/v1/me', $auth)->assertOk();
    $this->app['auth']->forgetGuards();
    getJson('/api/v1/me', ['Authorization' => 'Bearer '.$outraToken])->assertStatus(401);
});

it('recusa senha atual incorreta com 422 e nao troca nada', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    postJson('/api/v1/me/password', [
        'senha_atual' => 'errada-mas-longa-123',
        'nova_senha' => 'outra-senha-longa-456',
        'nova_senha_confirmation' => 'outra-senha-longa-456',
    ], $auth)->assertStatus(422)->assertJsonPath('code', 'senha_atual_invalida');

    expect(Hash::check('senha-bem-longa-123', $usuario->refresh()->senha))->toBeTrue();
});

it('exige nova senha com no minimo 12 caracteres', function () {
    $usuario = criarUsuarioComPapel('coordenadora');
    $auth = ['Authorization' => 'Bearer '.$usuario->createToken('t')->plainTextToken];

    postJson('/api/v1/me/password', [
        'senha_atual' => 'senha-bem-longa-123',
        'nova_senha' => 'curta',
        'nova_senha_confirmation' => 'curta',
    ], $auth)->assertStatus(422);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm app php artisan test --filter=MePerfilTest`
Expected: FAIL — rota `POST /api/v1/me/password` inexistente.

- [ ] **Step 3: Atualizar o contrato OpenAPI e o catálogo**

Em `docs/04-api/openapi.yaml`, adicionar o path:

```yaml
  /me/password:
    post:
      tags: [identity]
      summary: Trocar minha senha
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [senha_atual, nova_senha, nova_senha_confirmation]
              properties:
                senha_atual: { type: string, format: password }
                nova_senha: { type: string, format: password, minLength: 12 }
                nova_senha_confirmation: { type: string, format: password, minLength: 12 }
      responses:
        '204': { description: Senha alterada; demais sessões revogadas }
        '401': { $ref: '#/components/responses/Problem' }
        '422': { $ref: '#/components/responses/Problem' }
```

Em `docs/04-api/endpoints.md`:

```markdown
| POST | `/me/password` | Trocar minha senha (`no OpenAPI`) | RF-05 |
```

- [ ] **Step 4: Implementar request e serviço**

`backend/app/Http/Requests/Identidade/PerfilSenhaRequest.php`:

```php
<?php

namespace App\Http\Requests\Identidade;

use Illuminate\Foundation\Http\FormRequest;

class PerfilSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'senha_atual' => ['required', 'string'],
            'nova_senha' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }
}
```

(`confirmed` espera o campo `nova_senha_confirmation`, conforme o contrato.)

Adicionar ao contrato `GerirPerfil`:

```php
    /**
     * Troca a senha do proprio usuario. Senha atual incorreta lanca
     * ProblemException 422 (code `senha_atual_invalida`). Preserva o token
     * atual e revoga os demais (RF-05).
     */
    public function alterarSenha(Authenticatable $usuario, string $senhaAtual, string $novaSenha): void;
```

Implementar em `GerenciadorPerfil`: confere `Hash::check($senhaAtual, $usuario->getAuthPassword())` → se falhar, `ProblemException('senha_atual_invalida', 'Senha inválida', 422, 'A senha atual não confere.')`. Em transação: `forceFill(['senha' => Hash::make($novaSenha)])->save()`. Para revogar as outras sessões, obter `$atual = $usuario->currentAccessToken()?->getKey()`, então `$usuario->tokens()->when($atual !== null, fn ($q) => $q->whereKeyNot($atual))->delete()` e marcar `SessaoLogin::query()->where('usuario_id', $usuario->id)->whereNull('revogada_em')->when($atual !== null, fn ($q) => $q->where('token_id', '!=', $atual))->update(['revogada_em' => now()])`. Auditar `acao => 'alterar_senha'` (metadados sem valores de senha).

- [ ] **Step 5: Rota e action**

Em `backend/routes/api.php` (grupo `auth:sanctum`):

```php
Route::post('me/password', [MeController::class, 'alterarSenha'])->name('me.password');
```

Em `MeController`:

```php
    public function alterarSenha(PerfilSenhaRequest $request): Response
    {
        $this->gerirPerfil->alterarSenha(
            $this->usuario($request),
            $request->string('senha_atual')->toString(),
            $request->string('nova_senha')->toString(),
        );

        return response()->noContent();
    }
```

- [ ] **Step 6: Rodar testes e gates**

Run: `docker compose run --rm app php artisan test --filter=MePerfilTest`
Expected: PASS (10 testes).

Run: `docker compose run --rm app ./vendor/bin/phpstan analyse` → `[OK] No errors`.
Run: `docker compose run --rm app ./vendor/bin/pint --test` → sem alterações pendentes.
Run: `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"` → `OK:`.

- [ ] **Step 7: Regenerar o cliente e commit**

Em `frontend/`: `npm run gen:api`.

```bash
git add docs/04-api docs/04-api/endpoints.md backend/app backend/routes/api.php backend/tests/Feature/Identidade/MePerfilTest.php frontend/src/api/schema.d.ts
git commit -m "feat(identidade): POST /me/password com revogação das demais sessões (E03)"
```

---

### Task 4: Contexto de tema (claro/escuro) com anti-flash

**Files:**
- Create: `frontend/src/lib/tema.ts`
- Create: `frontend/src/components/TemaToggle.tsx`
- Modify: `frontend/index.html`
- Modify: `frontend/src/index.css`

**Interfaces:**
- Produces: `type Tema = 'light' | 'dark' | 'system'`; `lerTema(): Tema`; `aplicarTema(t: Tema): void`; `alternarTema(): Tema` (persiste em `localStorage['ebi.tema']` e retorna o novo). `<TemaToggle />` sem props.

- [ ] **Step 1: Implementar `frontend/src/lib/tema.ts`**

```ts
export type Tema = 'light' | 'dark' | 'system'

const CHAVE = 'ebi.tema'

export function lerTema(): Tema {
  const bruto = localStorage.getItem(CHAVE)
  return bruto === 'light' || bruto === 'dark' || bruto === 'system' ? bruto : 'system'
}

export function aplicarTema(tema: Tema): void {
  const escuro =
    tema === 'dark' ||
    (tema === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
  document.documentElement.classList.toggle('dark', escuro)
}

export function alternarTema(): Tema {
  const atual = lerTema()
  const proximo: Tema =
    atual === 'dark'
      ? 'light'
      : atual === 'light'
        ? 'system'
        : window.matchMedia('(prefers-color-scheme: dark)').matches
          ? 'light'
          : 'dark'
  localStorage.setItem(CHAVE, proximo)
  aplicarTema(proximo)
  return proximo
}
```

- [ ] **Step 2: Script anti-flash no `index.html`**

Dentro de `<head>`, **antes** do `<script type="module" src="/src/main.tsx">` (o script roda síncrono no head):

```html
    <script>
      (function () {
        try {
          var t = localStorage.getItem('ebi.tema') || 'system';
          var escuro =
            t === 'dark' ||
            (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
          document.documentElement.classList.toggle('dark', escuro);
        } catch (e) {}
      })();
    </script>
```

- [ ] **Step 3: Tornar `.dark` a única fonte de tema em `index.css`**

Remover o bloco `@media (prefers-color-scheme: dark) { :root { ... } }` (o script/`tema.ts` decidem). Manter o bloco `.dark { ... }` como está. Trocar `color-scheme: light dark` por `color-scheme: light` no `:root` e adicionar `.dark { color-scheme: dark; }` (junto ao bloco `.dark` de variáveis) para barra de rolagem/inputs nativos acompanharem.

- [ ] **Step 4: Implementar `frontend/src/components/TemaToggle.tsx`**

Botão que lê o tema na montagem e chama `alternarTema()`. Mostrar ícone conforme o tema efetivo (`Sun`/`Moon`/`Monitor`, de `lucide-react`, que a Task 6 instala — nesta task usar texto/emoji se `lucide-react` ainda não está instalado; ver Step 5). Incluir `aria-label` ("Alternar tema") e `title`. Sem contexto/provider — estado local + `localStorage`.

- [ ] **Step 5: Verificar deps e gates**

Conferir se `lucide-react` está em `frontend/package.json`; se não, instalar na Task 6 (que roda o `shadcn add`). Para esta task, se `lucide-react` ainda não existe, usar um `<button>` com texto curto para evitar dependência.

Em `frontend/`: `npm run lint && npm run build`.
Expected: ambos passam. Verificação manual: alternar o tema pisca a tela **sem** flash ao recarregar.

- [ ] **Step 6: Commit**

```bash
git add frontend/index.html frontend/src/index.css frontend/src/lib/tema.ts frontend/src/components/TemaToggle.tsx
git commit -m "feat(frontend): tema claro/escuro com alternância persistida e anti-flash"
```

---

### Task 5: Casca de aplicação (sidebar + cabeçalho)

**Files:**
- Create: `frontend/src/components/layout/AppShell.tsx`
- Create: `frontend/src/components/layout/ListaNav.tsx`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/index.css` (remover a "preservação" legada: `#root` largura fixa/text-align center, `h1` 56px)

**Interfaces:**
- Consumes: `TemaToggle` (Task 4); `Button`, `cn`; `Usuario`, `ContextoAtuacao`.
- Produces: `AppShell({ titulo, usuario, escopos, escopo, onTrocarEscopo, onIrPerfil, onSair, children })`, renderizado pelo `App`. `ListaNav({ ativo, onSelecionar })` com os 12 itens e as telas filtradas por papel (`inicio`, `perfil` sempre; `equipe`/`vinculos`/`excecao`/`auditoria` conforme `papeis`).

- [ ] **Step 1: Implementar `AppShell`**

Layout: `<div class="min-h-svh bg-background text-foreground">` com barra lateral fixa em `md:` (largura ~16rem) e, em telas pequenas, um cabeçalho com botão de gaveta (usar `Sheet` quando disponível — Task 6 adiciona; nesta task, um `<details>`/toggle simples, substituído na Task 7 pelos componentes shadcn). Cabeçalho com `titulo`, `<TemaToggle />`, `Select` de contexto (`escopo`, `onTrocarEscopo`, mesma codificação `papel@tipo:id` atual), menu do usuário com "Editar perfil" (`onIrPerfil`) e "Sair" (`onSair`). Conteúdo em `<main class="p-4 md:p-8">{children}</main>`.

- [ ] **Step 2: Migrar `App.tsx` para o shell**

Manter toda a lógica (convite, sessão, escopos, `Tela`). Substituir o `<nav>`/`<select>`/`<h1>` atuais por:

```tsx
<AppShell
  titulo={ROTULOS[tela]}
  usuario={usuario}
  escopos={escopos}
  escopo={escopo}
  onTrocarEscopo={trocarEscopo}
  onIrPerfil={() => setTela('perfil')}
  onSair={sair}
>
  {/* mesmos {tela === 'x' && <Componente/>} de hoje */}
</AppShell>
```

Criar `const ROTULOS: Record<Tela, string>` com o título humano de cada tela. A tela `inicio` vira um componente enxuto (título + nome/e-mail + atalhos) com classes Tailwind.

- [ ] **Step 3: Limpar a "preservação" legada em `index.css`**

Remover `#root { width: 1126px; text-align: center; border-inline: ... }` e o `h1 { font-size: 56px; }` (a tipografia passa a vir do Tailwind/shadcn). Manter `font-family` e o `color-scheme`.

- [ ] **Step 4: Gates**

Em `frontend/`: `npm run lint && npm run build`.
Expected: passam. Verificação manual: em ~375px de largura a navegação é acessível pela gaveta; em desktop a sidebar aparece; teclado navega (Tab/foco visível).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/App.tsx frontend/src/components/layout frontend/src/index.css
git commit -m "feat(frontend): casca de aplicação com sidebar, cabeçalho e tema"
```

---

### Task 6: Adicionar os componentes shadcn

**Files:**
- Modify: `frontend/package.json`, `frontend/package-lock.json` (via CLI)
- Create: `frontend/src/components/ui/*` (gerados)

**Interfaces:**
- Produces: componentes `card`, `input`, `label`, `dialog`, `dropdown-menu`, `sheet`, `separator`, `tooltip`, `badge`, `alert`, `tabs`, `sonner`, `select`, `table` disponíveis em `@/components/ui/*`; dependências Radix + `lucide-react` + `sonner` instaladas.

- [ ] **Step 1: Gerar os componentes**

No host, em `frontend/`:

```bash
npx shadcn@latest add card input label dialog dropdown-menu sheet separator tooltip badge alert tabs sonner select table
```

Expected: arquivos criados em `src/components/ui/`; `package.json` ganha as deps.

- [ ] **Step 2: Ligar o `sonner` Toaster no shell**

Em `AppShell` (ou `main.tsx`), incluir `<Toaster />` de `@/components/ui/sonner` uma vez, com tema sincronizado à classe `.dark` (o `sonner` aceita `theme`).

- [ ] **Step 3: Substituir os placeholders da Task 5 pelos componentes shadcn**

Gaveta do celular → `Sheet`; menu do usuário → `DropdownMenu`; seletor de contexto → `Select`; itens de nav → `Button variant="ghost"` com estado ativo; títulos → `Card`/tipografia.

- [ ] **Step 4: Gates**

Em `frontend/`: `npm run lint && npm run build`.
Expected: passam. Verificar manualmente claro/escuro e foco em cada componente novo.

- [ ] **Step 5: Commit**

```bash
git add frontend/package.json frontend/package-lock.json frontend/src/components/ui frontend/src/components/layout
git commit -m "chore(frontend): adiciona biblioteca de componentes shadcn"
```

---

### Task 7: Tela de perfil completa (dados, senha e segurança)

**Files:**
- Modify: `frontend/src/pages/MeuPerfil.tsx`
- Create: `frontend/src/pages/perfil/DadosForm.tsx`
- Create: `frontend/src/pages/perfil/SenhaForm.tsx`
- Create: `frontend/src/pages/perfil/SegurancaMfa.tsx`
- Modify: `frontend/src/App.tsx` (passar `onAtualizado` para refletir nome/e-mail no shell)

**Interfaces:**
- Consumes: `PATCH /me`, `POST /me/password`, `POST /me/mfa/totp`, `/me/mfa/totp/confirm`, `/me/mfa/recovery-codes`; `Usuario['mfa_ativo']` (Task 1); `cabecalhos`, `lerErro`; `sonner` (`toast`); componentes shadcn.
- Produces: `MeuPerfil({ usuario, onAtualizado }: { usuario: Usuario; onAtualizado: (u: Usuario) => void })`.

- [ ] **Step 1: `DadosForm`**

`Card` "Dados" com `Input` nome/e-mail, `Label` acessível, botão "Salvar". `PATCH /me`; sucesso → `toast.success('Perfil atualizado.')` e `onAtualizado(resposta)`; 422 `email_em_uso` → mensagem no campo de e-mail (via `lerErro(...).detail`). Se o e-mail mudou, mostrar aviso "Enviamos um link de verificação" (o e-mail passa a não verificado).

- [ ] **Step 2: `SenhaForm`**

`Card` "Senha": senha atual, nova, confirmação (todas `type="password"`, `autoComplete="current-password"`/`"new-password"`). `POST /me/password` com `{ senha_atual, nova_senha, nova_senha_confirmation }`. Sucesso → 204 + `toast.success('Senha alterada.')` + limpar campos. Erro 422 (`senha_atual_invalida`) → erro no campo "senha atual". Exibir dica "mínimo 12 caracteres".

- [ ] **Step 3: `SegurancaMfa`**

`Card` "Segurança". Se `usuario.mfa_ativo` é `false`: `Button` "Habilitar MFA" → `POST /me/mfa/totp` (mostra `segredo`/`uri`) → campo de código → `POST /me/mfa/totp/confirm` → `POST /me/mfa/recovery-codes` (mostrar uma vez). Se `true`: `Button` "Renovar MFA" que **abre `Dialog` de confirmação** ("Isto invalida seu aplicativo atual e os códigos de recuperação antigos. Continuar?") e só então reinicia o fluxo. Após habilitar/renovar, `onAtualizado`/recarregar para refletir `mfa_ativo`.

- [ ] **Step 4: Montar os três cartões em `MeuPerfil`**

`<div class="grid gap-6 md:grid-cols-2">` com os três cartões; cabeçalho com nome, e-mail e `Badge` dos papéis; `Tabs` opcional para telas pequenas.

- [ ] **Step 5: Ligar `onAtualizado` no `App.tsx`**

`{tela === 'perfil' && <MeuPerfil usuario={usuario} onAtualizado={setUsuario} />}`.

- [ ] **Step 6: Gates + verificação manual**

Em `frontend/`: `npm run lint && npm run build`.
Manual (com a coordenadora de demo `coordenadora@ebi.test` / senha `senha`): abrir perfil → salvar nome → **sem alterar e-mail** (não deve virar não verificado) → trocar senha com a atual correta (sessão continua) e com a errada (erro no campo) → tentar e-mail de outro usuário (erro no campo) → "Renovar MFA" com confirmação → ver códigos de recuperação.

- [ ] **Step 7: Commit**

```bash
git add frontend/src/pages frontend/src/App.tsx
git commit -m "feat(frontend): tela de perfil com dados, senha e segurança (MFA)"
```

---

### Task 8: Redesenho do login

**Files:**
- Modify: `frontend/src/pages/Login.tsx`

**Interfaces:**
- Consumes: as mesmas rotas atuais (`/auth/login`, `/me/mfa/totp`, `/me/mfa/totp/confirm`, `/auth/mfa/verify`); componentes shadcn.
- Produces: `Login({ onEntrar })` com layout centrado em `Card`, os três estados (credenciais, inscrição pendente, segundo fator) preservados.

- [ ] **Step 1: Redesenhar preservando o fluxo**

Mesma máquina de estados (email/senha → 200 desafio / 201 sessão; `mfa_inscricao_pendente` → inscrição; senão verificação). Trocar HTML puro por `Card`/`Input`/`Label`/`Button`/`Alert`, com foco inicial no e-mail, `aria-live` para erros e layout responsivo.

- [ ] **Step 2: Gates + verificação manual**

Em `frontend/`: `npm run lint && npm run build`.
Manual: login com a coordenadora → inscrição pendente (se aplicável) → segundo fator → entra; erro de credenciais aparece em `Alert`.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/pages/Login.tsx
git commit -m "feat(frontend): redesenha a tela de login"
```

---

### Task 9: Adaptar as demais telas ao novo layout

**Files:**
- Modify: `frontend/src/pages/{Dispositivos,Equipe,Privacidade,Participantes,Dependentes,CadastroAssistido,Vinculos,ExcecaoInclusiva,Mesclagem,Auditoria}.tsx`
- Modify: `frontend/src/components/FormularioParticipante.tsx`

**Interfaces:**
- Consumes: componentes shadcn; `AppShell` já envolve todas as telas.
- Produces: as mesmas telas com apresentação shadcn (lógica/estado/props **inalterados**).

- [ ] **Step 1: Trocar a marcação crua por componentes**

Para cada tela: `<main><h1>` → cabeçalho dentro de `Card`; `<ul>/<li>` → `Table`/lista com classes; `<input>` → `Input`+`Label`; `<button>` → `Button`; `<p role="alert">` → `Alert`; `<select>` → `Select`. **Não** alterar chamadas de API, props, `data-testid` (usados para verificação) nem nomes de estado.

- [ ] **Step 2: Gates**

Em `frontend/`: `npm run lint && npm run build`.
Expected: passam.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/pages frontend/src/components/FormularioParticipante.tsx
git commit -m "refactor(frontend): adapta as telas ao layout e componentes novos"
```

---

### Task 10: Documentação e gates finais

**Files:**
- Modify: `frontend/README.md`
- Modify: `frontend/src/index.css` (se sobrou algo da "preservação" legada)
- Modify: `docs/04-api/endpoints.md` (conferir as linhas novas)

**Interfaces:**
- Consumes: tudo o que as tasks anteriores produziram.
- Produces: README atualizado (tema com toggle, casca, perfil) e branch com todos os gates verdes.

- [ ] **Step 1: Atualizar o `frontend/README.md`**

Trocar a linha "tema escuro é automático, sem toggle" por: tema claro/escuro **com botão**, persistido em `localStorage['ebi.tema']`, aplicado por script inline (sem flash). Documentar a casca (`components/layout/`) e a tela de perfil.

- [ ] **Step 2: Rodar a suíte completa do backend**

Run: `docker compose run --rm app php artisan test`
Expected: todas passam (as 224 anteriores + as novas).

- [ ] **Step 3: Gates finais**

Run: `docker compose run --rm app ./vendor/bin/phpstan analyse` → `[OK] No errors`.
Run: `docker compose run --rm app ./vendor/bin/pint --test` → verde.
Run: `docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"` → `OK:`.
Em `frontend/`: `npm run lint && npm run build` → passam.
Reiniciar os containers Octane (`docker compose restart app horizon scheduler`) antes da verificação manual.

- [ ] **Step 4: Verificação manual ponta a ponta (registrar o resultado)**

Com o ambiente de demo: login → alternar tema e recarregar (sem flash) → perfil: editar nome, trocar senha, renovar MFA, ver recuperação → navegar pelas telas na gaveta (celular) e na sidebar (desktop).

- [ ] **Step 5: Commit**

```bash
git add frontend/README.md docs/04-api/endpoints.md frontend/src/index.css
git commit -m "docs(frontend): documenta casca, tema e perfil; fecha os gates"
```

---

## Self-Review

**Spec coverage:** §6.1 → Task 2; §6.2 → Task 3; §6.3 (`mfa_ativo`) → Task 1; §6.4 (renovar MFA) → Task 7 Step 3; §7 tema → Task 4; §7 casca → Tasks 5–6; §7 perfil → Task 7; §7 login → Task 8; §7 migração das telas → Task 9; §8 escolhas → Tasks 5 (sem router), 4/6 (paleta/tema), 9 (migração das demais); §9 riscos → Task 10 (gates + manual).

**Review Focus → test owner:** e-mail em uso → Task 2 ("recusa e-mail já usado… 422"); senha atual errada + sessão viva → Task 3 (dois testes); renovar MFA/índice único → Task 7 (fluxo manual + reuso de `GerenciadorMfa` que já usa `updateOrCreate(confirmado_em null)`); `mfa_ativo` false sem fator → Task 1; e-mail igual (sem conflito consigo) → Task 2 ("mantem o e-mail verificado…").

**Type consistency:** `GerirPerfil::atualizar/alterarSenha` (Task 2/3) usadas só no `MeController`; `Usuario['mfa_ativo']` (Task 1) consumido na Task 7; `lerTema/aplicarTema/alternarTema` (Task 4) usados na Task 5/7; `AppShell` props (Task 5) casam com a chamada no `App.tsx`.

**Nota de dependência:** Task 4 instala nada; se `lucide-react` faltar, a Task 6 o traz e a Task 5 usa fallback textual até lá (a ordem 4→5→6 é segura porque a Task 5 usa só `Button` + classes).