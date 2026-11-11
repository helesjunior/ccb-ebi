# Fundação de UI: Tailwind CSS v4 + shadcn/ui — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Instalar a base de UI do frontend EBI (Tailwind CSS v4 + shadcn/ui configurado), com um componente-piloto (`Button`) provando o pipeline, sem regressão visual das 7 telas existentes.

**Architecture:** Tailwind v4 entra pelo plugin `@tailwindcss/vite`, mantendo o build multi-destino (`/spa/` × `/`) intacto. O tema do shadcn assume as variáveis CSS (`--primary` = roxo atual `#aa3bff`), com dark automático (`prefers-color-scheme`) espelhado na classe `.dark`. Um `@layer base` re-expressa a tipografia/moldura atuais para as telas não migradas não mudarem. O shadcn é configurado por `components.json` + `cn()`.

**Tech Stack:** Vite 8 · React 19 · TypeScript 7 · Tailwind CSS v4 (`@tailwindcss/vite`) · shadcn/ui (CLI `shadcn@latest`) · oxlint. Node do host em `frontend/` (não Docker).

**Spec:** `docs/superpowers/specs/2026-11-11-fundacao-ui-tailwind-shadcn-design.md`

## Global Constraints

- **Trabalhar em `frontend/`** com o Node do host (`npm`, `npx`) — nunca Docker para o frontend.
- **Repo principal:** `C:/Users/arl0001/Documents/Projetos/ccb-ebi`, branch nova a partir de `main`.
- **Não quebrar o build multi-destino:** `vite.config.ts` mantém `base` (`/spa/` vs `/`) e `outDir` (`VITE_OUT_DIR` → `../backend/public/spa`).
- **Tema:** variáveis no padrão shadcn; `--primary` (e `--ring`) = roxo atual `#aa3bff`. Dark **automático** por `prefers-color-scheme`, **sem toggle**; espelhar em `.dark`.
- **Sem regressão visual** das 7 telas não migradas (a camada base preserva tipografia, moldura `#root` e dark).
- **Estilo shadcn:** `new-york`; `baseColor` neutro; `cssVariables: true`; `rsc: false`; `tsx: true`; `tailwind.config: ""` (v4).
- **Alias `@/*` → `./src/*`** em `tsconfig.json` e `tsconfig.app.json` (`baseUrl: "."`), e em `vite.config.ts` (`resolve.alias`, via `node:path`). `@types/node` já está em devDeps.
- **Texto em português**; commits pequenos, um por task (`feat:`/`chore:`/`docs:`).
- **Gate por task:** `npm run build` (tsc -b + vite) e `npm run lint` (oxlint) verdes.
- **Backend intocado**; contrato público inalterado.

## Review Focus

Cinco condições que a spec implica e que um construtor pode facilmente quebrar; cada uma ganha verificação na task dona:

1. **Preflight apaga o estilo global** — as telas usam só tags semânticas (`h1`/`h2`/`p`/`button`), estilizadas por seletores globais no `index.css`. O Tailwind resetaria isso. Deve haver `@layer base` que preserve o visual. → Task 2.
2. **Build multi-destino** — mexer em `vite.config.ts` pode perder o `base` condicional (`/spa/` vs `/`) ou o `outDir`. → Task 1.
3. **Alias `@/` não resolve no editor** — se só um dos tsconfigs receber `paths`, o `tsc -b` (build) quebra mesmo com o Vite resolvendo. → Task 1.
4. **`erasableSyntaxOnly`/`verbatimModuleSyntax`** — arquivos gerados pelo shadcn podem usar imports/`enum` que o tsconfig rejeita. → Task 3 e 4.
5. **oxlint `react/only-export-components`** — `button.tsx` exporta `buttonVariants` junto do componente; a regra (warn) precisa de escopo justificado, sem silenciar o resto. → Task 4.

---

### Task 1: Tailwind v4 + alias `@/` (build sem componentes)

**Files:**
- Modify: `frontend/package.json` (adicionar deps via npm)
- Modify: `frontend/vite.config.ts`
- Modify: `frontend/tsconfig.json`
- Modify: `frontend/tsconfig.app.json`
- Modify: `frontend/src/index.css` (apenas o `@import` do Tailwind, por ora)

**Interfaces:**
- Consumes: nada.
- Produces:
  - Tailwind v4 ativo no build (`@tailwindcss/vite` no array `plugins`).
  - Alias `@/*` resolvido em `vite.config.ts` **e** nos dois tsconfigs (`baseUrl: "."`, `paths`).
  - `frontend/src/index.css` começando com `@import "tailwindcss";`.

- [ ] **Step 1: Instalar as dependências do Tailwind v4**

Run (em `frontend/`):
```bash
npm install -D tailwindcss @tailwindcss/vite
```
Expected: `package.json` com `tailwindcss` e `@tailwindcss/vite` em `devDependencies`; `package-lock.json` atualizado.

- [ ] **Step 2: Substituir o conteúdo de `frontend/src/index.css` pelo import do Tailwind**

Conteúdo (temporário — o tema vem na Task 2):
```css
@import "tailwindcss";
```

- [ ] **Step 3: Ligar o plugin do Tailwind e o alias no `frontend/vite.config.ts`**

Reescreva o arquivo preservando o comentário existente e a lógica de `base`/`outDir`, acrescentando `tailwindcss()` aos plugins e o `resolve.alias`:
```ts
import path from 'node:path'

import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// (manter o comentário original sobre VITE_OUT_DIR / base)
const outDir = process.env.VITE_OUT_DIR ?? '../backend/public/spa'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  base: outDir.endsWith('dist') ? '/' : '/spa/',
  build: {
    outDir,
    emptyOutDir: true,
  },
})
```
Nota: em ESM, `__dirname` pode não existir; se o build reclamar, use `path.resolve(import.meta.dirname, './src')` (Node ≥ 20) ou `fileURLToPath(new URL('./src', import.meta.url))`.

- [ ] **Step 4: Adicionar o alias ao `frontend/tsconfig.json` e `frontend/tsconfig.app.json`**

Em `tsconfig.json`, dentro de `compilerOptions` (criar o bloco `compilerOptions` ao lado de `files`/`references`):
```json
"compilerOptions": {
  "baseUrl": ".",
  "paths": { "@/*": ["./src/*"] }
}
```
Em `tsconfig.app.json`, acrescente ao `compilerOptions` existente:
```json
"baseUrl": ".",
"paths": { "@/*": ["./src/*"] }
```

- [ ] **Step 5: Verificar build e lint (sem componentes ainda)**

Run (em `frontend/`):
```bash
npm run build
npm run lint
```
Expected: build conclui (tsc -b + vite) e gera a saída; lint sem erros. Se o CSS ainda estiver cru, é esperado — o tema vem na Task 2.

- [ ] **Step 6: Commit**

```bash
git add frontend/package.json frontend/package-lock.json frontend/vite.config.ts frontend/tsconfig.json frontend/tsconfig.app.json frontend/src/index.css
git commit -m "feat(frontend): habilita Tailwind v4 e alias @/ no build"
```

---

### Task 2: Tema shadcn com roxo atual + camada base (sem regressão visual)

**Files:**
- Modify: `frontend/src/index.css` (tema completo + `@layer base`)
- Delete: `frontend/src/App.css`
- Modify: `frontend/src/App.tsx` (remover `import './App.css'`)

**Interfaces:**
- Consumes: Tailwind v4 ativo (Task 1).
- Produces:
  - `frontend/src/index.css` no formato shadcn: `@import "tailwindcss";` + `@custom-variant dark` + `@theme inline` (tokens) + `:root` (tema claro) + dark automático (`@media (prefers-color-scheme: dark)`) e `.dark` + `@layer base` com a preservação do visual atual.
  - Tokens expostos: `--primary`/`--ring` = roxo `#aa3bff`; `--background`, `--foreground`, `--border`, `--radius`, `--muted-foreground` etc. no padrão shadcn.
  - `App.css` removido; `App.tsx` sem o import.

- [ ] **Step 1: Reescrever `frontend/src/index.css` com o tema**

Estrutura (valores do preset shadcn `neutral` + roxo do projeto no `--primary`/`--ring`):
```css
@import "tailwindcss";

@custom-variant dark (&:is(.dark *));

@theme inline {
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-primary: var(--primary);
  --color-primary-foreground: var(--primary-foreground);
  --color-secondary: var(--secondary);
  --color-secondary-foreground: var(--secondary-foreground);
  --color-muted: var(--muted);
  --color-muted-foreground: var(--muted-foreground);
  --color-accent: var(--accent);
  --color-accent-foreground: var(--accent-foreground);
  --color-destructive: var(--destructive);
  --color-border: var(--border);
  --color-input: var(--input);
  --color-ring: var(--ring);
  --radius-sm: calc(var(--radius) * 0.6);
  --radius-md: calc(var(--radius) * 0.8);
  --radius-lg: var(--radius);
  --radius-xl: calc(var(--radius) * 1.4);
}

:root {
  --radius: 0.625rem;
  --background: #ffffff;
  --foreground: #08060d;
  --primary: #aa3bff;
  --primary-foreground: #ffffff;
  --secondary: #f4f3ec;
  --secondary-foreground: #08060d;
  --muted: #f4f3ec;
  --muted-foreground: #6b6375;
  --accent: #f4f3ec;
  --accent-foreground: #08060d;
  --destructive: #d4183d;
  --border: #e5e4e7;
  --input: #e5e4e7;
  --ring: #aa3bff;
}

/* Dark automático (comportamento atual do app, sem toggle) + classe .dark do shadcn. */
@media (prefers-color-scheme: dark) {
  :root {
    --background: #16171d;
    --foreground: #f3f4f6;
    --primary: #c084fc;
    --primary-foreground: #16171d;
    --secondary: #1f2028;
    --secondary-foreground: #f3f4f6;
    --muted: #1f2028;
    --muted-foreground: #9ca3af;
    --accent: #1f2028;
    --accent-foreground: #f3f4f6;
    --destructive: #ff6467;
    --border: #2e303a;
    --input: #2e303a;
    --ring: #c084fc;
  }
}

.dark {
  --background: #16171d;
  --foreground: #f3f4f6;
  --primary: #c084fc;
  --primary-foreground: #16171d;
  --secondary: #1f2028;
  --secondary-foreground: #f3f4f6;
  --muted: #1f2028;
  --muted-foreground: #9ca3af;
  --accent: #1f2028;
  --accent-foreground: #f3f4f6;
  --destructive: #ff6467;
  --border: #2e303a;
  --input: #2e303a;
  --ring: #c084fc;
}

/* Preservação das telas ainda não migradas (o Preflight do Tailwind zera o estilo global). */
@layer base {
  * {
    border-color: var(--border);
  }

  body {
    margin: 0;
    font-family: system-ui, 'Segoe UI', Roboto, sans-serif;
    font-size: 18px;
    line-height: 145%;
    letter-spacing: 0.18px;
    color: var(--foreground);
    background: var(--background);
  }

  #root {
    width: 1126px;
    max-width: 100%;
    margin: 0 auto;
    text-align: center;
    border-inline: 1px solid var(--border);
    min-height: 100svh;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
  }

  h1 {
    font-size: 56px;
    letter-spacing: -1.68px;
    margin: 32px 0;
    color: var(--foreground);
  }

  h2 {
    font-size: 24px;
    line-height: 118%;
    letter-spacing: -0.24px;
    margin: 0 0 8px;
    color: var(--foreground);
  }
}
```
> Os valores de tipografia/moldura acima vêm do `index.css` atual do projeto — não invente; confira o arquivo antes de escrever.

- [ ] **Step 2: Remover o CSS morto**

- Apague `frontend/src/App.css` (nenhuma classe é usada em `.tsx`).
- Em `frontend/src/App.tsx`, remova a linha `import './App.css'`.

- [ ] **Step 3: Verificar build e lint**

Run (em `frontend/`):
```bash
npm run build
npm run lint
```
Expected: ambos verdes. (Verificação manual de aparência: subir `npm run dev` e conferir que as telas seguem legíveis — ver Task 5.)

- [ ] **Step 4: Commit**

```bash
git add frontend/src/index.css frontend/src/App.tsx
git rm frontend/src/App.css
git commit -m "feat(frontend): tema shadcn (roxo atual) com dark automatico e base preservada"
```

---

### Task 3: Configuração do shadcn (`components.json` + `cn()`)

**Files:**
- Create: `frontend/components.json`
- Create: `frontend/src/lib/utils.ts`
- Modify: `frontend/package.json` (deps do `cn()`)
- Modify: `frontend/tsconfig.app.json` (se o oxlint/ts exigir ajuste — ver Step 4)

**Interfaces:**
- Consumes: alias `@/*` (Task 1); `index.css` (Task 2).
- Produces:
  - `frontend/components.json` no formato shadcn v4.
  - `frontend/src/lib/utils.ts` exportando `cn(...inputs: ClassValue[]): string`.
  - Deps `clsx` e `tailwind-merge` instaladas.

- [ ] **Step 1: Criar `frontend/components.json`**

```json
{
  "$schema": "https://ui.shadcn.com/schema.json",
  "style": "new-york",
  "rsc": false,
  "tsx": true,
  "tailwind": {
    "config": "",
    "css": "src/index.css",
    "baseColor": "neutral",
    "cssVariables": true,
    "prefix": ""
  },
  "aliases": {
    "components": "@/components",
    "ui": "@/components/ui",
    "lib": "@/lib",
    "hooks": "@/hooks",
    "utils": "@/lib/utils"
  },
  "iconLibrary": "lucide"
}
```

- [ ] **Step 2: Criar `frontend/src/lib/utils.ts`**

```ts
import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
```

- [ ] **Step 3: Instalar as deps do `cn()`**

Run (em `frontend/`):
```bash
npm install clsx tailwind-merge
```
Expected: ambas aparecem em `dependencies`.

- [ ] **Step 4: Verificar que o tipo compila sob o tsconfig atual**

Run (em `frontend/`):
```bash
npm run build
```
Expected: verde. Se `verbatimModuleSyntax` exigir, ajuste o import de tipo (`import { clsx } from 'clsx'; import type { ClassValue } from 'clsx'`) — não afrouxe o tsconfig do projeto.

- [ ] **Step 5: Commit**

```bash
git add frontend/components.json frontend/src/lib/utils.ts frontend/package.json frontend/package-lock.json
git commit -m "feat(frontend): configura shadcn (components.json) e utilitario cn()"
```

---

### Task 4: Componente-piloto `Button` na casca + oxlint

**Files:**
- Create: `frontend/src/components/ui/button.tsx` (gerado pelo CLI do shadcn, ou escrito à mão se o CLI não rodar)
- Modify: `frontend/src/App.tsx` (o botão "Sair" vira `<Button variant="outline">`)
- Modify: `frontend/.oxlintrc.json` (se necessário — escopo para `src/components/ui/**`)

**Interfaces:**
- Consumes: `components.json` + `cn()` (Task 3).
- Produces:
  - `frontend/src/components/ui/button.tsx` exportando `Button` (e `buttonVariants`) importável de `@/components/ui/button`.
  - `App.tsx` renderizando `<Button>` no ponto do "Sair".

- [ ] **Step 1: Gerar o componente `button` pelo CLI do shadcn**

Run (em `frontend/`):
```bash
npx --yes shadcn@latest add button
```
Expected: cria `frontend/src/components/ui/button.tsx` e instala as deps que ele pedir (ex.: `@radix-ui/react-slot`, `class-variance-authority`, `lucide-react`).
Se o CLI falhar/não-interativo neste ambiente: **escreva à mão** o `button.tsx` no formato shadcn v4 (usa `cn`, `cva`, `Slot`, `type VariantProps`), mantendo a assinatura de `Button` e `buttonVariants`.

- [ ] **Step 2: Usar o `Button` no ponto do piloto (casca, sem migrar a casca)**

Em `frontend/src/App.tsx`: importar `import { Button } from '@/components/ui/button'` e trocar **somente** o botão "Sair":
```tsx
<Button variant="outline" type="button" onClick={sair}>
  Sair
</Button>
```
Não mexer nos demais botões da nav nem no seletor de escopo.

- [ ] **Step 3: Ajustar o oxlint, se a regra disparar**

Run: `npm run lint`
Expected: se aparecer aviso `react/only-export-components`, adicione em `frontend/.oxlintrc.json` um override **restrito** ao código gerado, por exemplo:
```json
"overrides": [
  { "files": ["src/components/ui/**"], "rules": { "react/only-export-components": "off" } }
]
```
Só faça isso se o warning realmente aparecer; não silencie o resto do projeto.

- [ ] **Step 4: Verificar build e lint finais**

Run (em `frontend/`):
```bash
npm run build
npm run lint
```
Expected: ambos verdes; `button.tsx` compilando sob TS7 (`erasableSyntaxOnly`/`verbatimModuleSyntax`) — se preciso, use `import type` para tipos (ex.: `VariantProps`).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/components/ui/button.tsx frontend/src/App.tsx frontend/.oxlintrc.json frontend/package.json frontend/package-lock.json
git commit -m "feat(frontend): componente-piloto Button (shadcn) na casca"
```

---

### Task 5: Docs + verificação manual

**Files:**
- Modify: `docs/CHANGELOG.md` (nota do frontend)
- Modify: `frontend/README.md` (como adicionar componentes shadcn)

**Interfaces:**
- Consumes: tudo acima.
- Produces: documentação do uso do shadcn e registro no CHANGELOG.

- [ ] **Step 1: Atualizar o `frontend/README.md`**

Acrescente uma seção curta, em português:
- O frontend usa **Tailwind v4 + shadcn/ui**.
- Para adicionar um componente: `npx shadcn@latest add <nome>` (gera em `src/components/ui/`).
- Tema em `src/index.css` (`--primary` = roxo da marca); dark automático (`prefers-color-scheme`).
- Alias de import: `@/...` → `src/...`.

- [ ] **Step 2: Registrar no `docs/CHANGELOG.md`**

Nova entrada no topo (versão 2.15 — 11/11/2026), título "Frontend — fundação de UI (Tailwind + shadcn)":
- Tailwind CSS v4 no build; tema shadcn com o roxo atual como `--primary`; dark automático.
- shadcn/ui configurado (`components.json`, `cn()`); componente-piloto `Button` na casca.
- Remoção do CSS morto (`App.css`).
- Dívidas: migrar casca e as telas para componentes; runner de teste de frontend.

- [ ] **Step 3: Verificação manual da aparência (sem regressão)**

Run (em `frontend/`): `npm run dev` e abrir o app; conferir:
- A casca (nav, seletor de escopo) e as telas (`Login`, `MeuPerfil`, `Equipe`, `Privacidade`, `Auditoria`, `Dispositivos`) seguem legíveis e sem colapso de layout.
- O botão "Sair" aparece como `Button` (variante outline) e funciona.
- Alternar o tema do SO (claro/escuro) reflete no app.
Registre o resultado (print ou descrição) no relatório da task.

- [ ] **Step 4: Commit**

```bash
git add docs/CHANGELOG.md frontend/README.md
git commit -m "docs(frontend): uso do shadcn e nota da fundacao de UI no changelog"
```

---

## Self-Review

**1. Spec coverage:** objetivos da spec → T1 (Tailwind + alias), T2 (tema + base, sem regressão), T3 (`components.json` + `cn()`), T4 (piloto `Button`), T5 (docs). Não-objetivos (migrar telas/casca, runner de teste, mais componentes) permanecem fora. Limpeza do `App.css` → T2.

**2. Step scan:** cada step é uma ação com resultado checável (comando + resultado esperado); os corpos aparecem só onde a assinatura/teste não determinam (config do tema, `vite.config.ts`, `cn()`).

**3. Type consistency:** `cn(...inputs: ClassValue[]): string` (T3) é o que `button.tsx` (T4) importa de `@/lib/utils`; `Button`/`buttonVariants` de T4 usam esse `cn`. Alias `@/` definido em T1 é o usado em T3/T4.

**4. Review Focus:** (1) base preservada → T2 Step 1; (2) build multi-destino → T1 Step 3; (3) alias nos dois tsconfigs → T1 Step 4; (4) TS7 estrito → T3 Step 4 / T4 Step 4; (5) oxlint → T4 Step 3.

**5. Proportion:** plano ≈ spec em tamanho; sem transcrição do `button.tsx` gerado (o CLI produz).

## Execution Handoff

Método já definido pelo produto (usado na A2): **subagentes por task** + review fresco por task + review whole-branch no fim. Seguir `superpowers:subagent-driven-development`.