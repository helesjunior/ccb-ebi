# Design — Fundação de UI: Tailwind CSS v4 + shadcn/ui (frontend EBI)

**Data:** 11/11/2026 · **Base:** `main` @ `6ed962a` (A2 mergeada, PR #27)
**Escopo:** sub-etapa de fundação (não é épico). Backend intocado.
**Método de execução:** subagentes por task + review fresco por task + review whole-branch no fim (mesmo da A2).

## 1. Intenção e por quê

O frontend é deliberadamente enxuto (Vite 8 + React 19 + TS 7, sem lib de UI, sem Tailwind, sem
runner de teste) e tem **7 telas reais** (`Login`, `AceitarConvite`, `MeuPerfil`, `Dispositivos`,
`Equipe`, `Privacidade`, `Auditoria`) mais a casca em `App.tsx` (nav por estado + seletor de escopo).
Todo o estilo vem de `src/index.css` + `src/App.css`.

O produto quer usar componentes **shadcn/ui**. O shadcn não é um pacote npm: é um gerador que copia
o código dos componentes para `src/components/ui/`, e **exige Tailwind CSS**. Hoje o projeto não tem
Tailwind, nem alias `@/`, nem `components.json` — então nada de shadcn funciona até a fundação existir.

Esta sub-etapa entrega **a base** (Tailwind v4 + shadcn configurado) e um **componente-piloto**, para
que os próximos épicos com frontend já nasçam usando componentes. Não migra telas.

## 2. Objetivos

1. **Tailwind CSS v4** integrado ao build (plugin `@tailwindcss/vite`), preservando o build
   multi-destino existente (`/spa/` para o Laravel, `/` para o nginx).
2. **shadcn/ui configurado** (`components.json`, utilitário `cn()`, dependências base), de modo que
   `npx shadcn add <componente>` gere componentes funcionais.
3. **Tema único** no estilo shadcn (variáveis `--background/--foreground/--primary/...` + tema escuro
   por classe `.dark`), com o **roxo atual (`#aa3bff`) como `--primary`** (decisão A1).
4. **Componente-piloto** (`button`) gerado, renderizado em algum ponto real da casca, provando que o
   pipeline funciona (build verde + `lint` verde).
5. **Limpeza:** remover o CSS morto (`App.css` legado do template; regras de hero/template não usadas).

## 3. Não-objetivos

- **Migrar as telas** (`Login`, `Equipe`, etc.) para componentes shadcn — fica para os épicos de
  frontend. Aqui só a fundação + piloto.
- **Migrar a casca** (`App.tsx` nav/seletor) para componentes shadcn — decisão B1: não entra.
- **Adicionar runner de teste de frontend** (não há um; fora de escopo).
- **Biblioteca de componentes ampla** (`card`, `table`, ...): só `button` agora.
- **Backend/API:** nada muda; sem contrato novo.

## 4. Anti-objetivos

- **Não** quebrar o build multi-destino (`base` `/spa/` vs `/`, `VITE_OUT_DIR`).
- **Não** introduzir um segundo sistema de tema coexistindo com o do shadcn (A1 = o shadcn assume).
- **Não** mexer no `oxlint`/regras a ponto de silenciar problemas reais — no máximo escopo adequado
  para `src/components/ui/**` (código gerado, não nosso).
- **Não** deixar CSS morto para trás.

## 5. Abordagens consideradas

- **A (escolhida): fundação Tailwind v4 + shadcn, tema do shadcn assume (A1), piloto `button` (B1).**
  Caminho oficial, revisável, deixa os épicos seguintes com base pronta. Recomendada e aceita.
- **B: só `button` portado à mão sem Tailwind.** Não é shadcn de verdade (componentes dependem de
  classes Tailwind); vira código "shadcn-like" mantido à mão. Rejeitada.
- **C: fundação + migrar a casca e uma tela inteira.** Prova mais, mas reescreve telas que vão mudar
  nos épicos; escopo grande para uma fundação. Rejeitada (B1).

### Decisões do construtor ("meu call", confirmáveis na revisão)
- **Tailwind v4** (CSS-first, sem `tailwind.config.js`; `@import "tailwindcss"` + `@theme`/`:root`).
- **Tema:** variáveis no padrão shadcn (oklch ou hex do preset oficial) em `index.css`; `--primary`
  = roxo atual. Tema escuro por **classe `.dark`** no `<html>` (padrão shadcn), preservando o
  comportamento atual via um pequeno script/`prefers-color-scheme` decidido na implementação.
- **Aliases:** `@/*` → `./src/*` em `tsconfig.json` **e** `tsconfig.app.json` (`baseUrl: "."`), e
  `resolve.alias` em `vite.config.ts` (via `node:path` + `@types/node`, já presente em devDeps).
- **`components.json`:** estilo novo do shadcn (`new-york` ou o default atual), `baseColor` neutro,
  `css` apontando para `src/index.css`, aliases `@/components`, `@/lib/utils`, `@/components/ui`,
  `@/lib`, `@/hooks`. `rsc: false`, `tsx: true`.
- **Local dos componentes:** `frontend/src/components/ui/`; utilitário em `frontend/src/lib/utils.ts`.
- **`cn()`:** `clsx` + `tailwind-merge` (deps que o shadcn instala).
- **`App.css`:** remover (CSS morto) e remover seu `import` — nenhuma classe é usada em `.tsx`.
- **TS7/`erasableSyntaxOnly`/`verbatimModuleSyntax`:** ajustar os arquivos gerados se necessário
  (ex.: `import type`), sem afrouxar o tsconfig do projeto.

## 6. Desenho

### 6.1 Build
`vite.config.ts` recebe `tailwindcss()` **junto** de `react()`, mantendo `base` (`/spa/` vs `/`) e
`outDir` (`VITE_OUT_DIR`) intactos. `@types/node` já está em devDeps.

### 6.2 Estilos
- `src/index.css` reescrito: `@import "tailwindcss";` + bloco de tema shadcn (`:root`, `.dark`,
  `@theme inline`), com `--primary`/`--ring` no roxo atual e `--radius`.
- **Camada base para as telas não migradas (risco do builder check):** o Preflight do Tailwind zera
  margens/tamanhos (predefine `h1/h2` herdando o corpo, `button` sem fundo/borda, `ul` sem marcadores).
  Como as 7 telas hoje dependem só dos seletores globais, o `index.css` inclui um `@layer base` que
  **re-expressa o essencial atual** (tipografia de `h1/h2/p/code`, corpo, e a moldura/`#root`), para
  que as telas não migradas sigam visualmente intactas. Meta: **sem regressão visual**.
- **Dark mode (decisão):** manter o comportamento atual **automático por `prefers-color-scheme`**,
  **sem toggle** (não existe hoje). Para os utilitários `dark:` do shadcn funcionarem, as variáveis do
  tema escuro são definidas **tanto** em `@media (prefers-color-scheme: dark)` **quanto** em `.dark`
  (o shadcn assume `.dark`; cobrimos os dois).
- `src/App.css` removido; `import './App.css'` removido de `App.tsx`.
- A moldura `#root { width: 1126px; }` é re-expressa no `@layer base` (mantém o visual atual).

### 6.3 Estrutura
```
frontend/
  components.json
  src/
    lib/utils.ts            # cn()
    components/ui/button.tsx
    index.css               # tema shadcn
```

### 6.4 Toolchain
- `oxlint`: se `button.tsx` exportar `buttonVariants` junto do componente, a regra
  `react/only-export-components` (warn) dispara. Tratar com override de escopo em
  `src/components/ui/**` (código gerado) no `.oxlintrc.json`, ou `allowExportNames` — decisão na task.
- Build de verificação: `npm run build` (tsc -b + vite build). `npm run lint` (oxlint).

### 6.5 Ponto do piloto (decisão)
O `Button` piloto substitui o botão **"Sair"** da casca (`App.tsx`) por `<Button variant="outline">`.
É um ponto real de renderização sem migrar a casca (nav e seletor de escopo permanecem como estão).

## 7. Restrições
- Node do host em `frontend/` (não Docker) — como o projeto já faz.
- Não alterar `nginx.conf`, `Dockerfile` do frontend nem o back.
- Sem dados reais: nada aqui toca isso (segue o portão da etapa 8).

## 8. Dívidas registradas (desta fundação)
1. Migrar a casca (`App.tsx`) para componentes shadcn (B1 deferido).
2. Migrar as 7 telas — épicos de frontend.
3. Runner/cobertura de teste de frontend (inexistente hoje).
4. Biblioteca de componentes além do `button` — conforme a demanda.

## 9. Critérios de aceite
- `npm run build` verde (tsc -b + vite) com Tailwind e o alias `@/` funcionando.
- `npx shadcn add button` (ou o `button.tsx` equivalente) gera `src/components/ui/button.tsx`
  importável de `@/components/ui/button`.
- O `Button` renderiza no botão "Sair" da casca, sem quebrar as telas existentes.
- **Sem regressão visual:** as telas não migradas seguem legíveis (a camada base preserva tipografia,
  moldura e o tema escuro automático); verificação manual do app subindo, além de build/lint.
- `npm run lint` verde (ou escopo do oxlint ajustado de forma justificada).
- `App.css` e seu import removidos; nenhuma classe morta restante.
- Backend e contrato público inalterados.

## 10. Ordem de construção (detalhada no plano)
1. Tailwind v4 + alias `@/` + `vite.config.ts` + `tsconfig` (build ainda sem componentes).
2. Tema no `index.css` (shadcn + camada base de preservação + dark automático) + remoção do
   `App.css`/import.
3. `components.json` + `lib/utils.ts` (`cn()`) + deps base; rodar o CLI do shadcn (`init`/`add button`).
4. Componente-piloto `Button` no "Sair" da casca + ajuste de oxlint se preciso.
5. Docs: CHANGELOG (+ nota de frontend) e débito registrado.