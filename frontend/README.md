# React + TypeScript + Vite

## Fundação de UI (Tailwind CSS v4 + shadcn/ui)

- O frontend usa **Tailwind CSS v4** (plugin `@tailwindcss/vite`) e **shadcn/ui**.
- Adicionar um componente: `npx shadcn@latest add <nome>` — gera o código em `src/components/ui/`.
- Tema em `src/index.css` (variáveis do shadcn; `--primary` = roxo da marca `#aa3bff`). O **tema escuro é automático** (`prefers-color-scheme`), sem toggle.
- Import por alias: `@/...` → `src/...` (ex.: `import { Button } from '@/components/ui/button'`).
- Build e lint: `npm run build` (tsc -b + vite) e `npm run lint` (oxlint).

## React + TypeScript + Vite (template)

This template provides a minimal setup to get React working in Vite with HMR and some Oxlint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Oxc](https://oxc.rs)
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/)

## React Compiler

The React Compiler is not enabled on this template because of its impact on dev & build performances. To add it, see [this documentation](https://react.dev/learn/react-compiler/installation).

## Expanding the Oxlint configuration

If you are developing a production application, we recommend enabling type-aware lint rules by installing `oxlint-tsgolint` and editing `.oxlintrc.json`:

```json
{
  "$schema": "./node_modules/oxlint/configuration_schema.json",
  "plugins": ["react", "typescript", "oxc"],
  "options": {
    "typeAware": true
  },
  "rules": {
    "react/rules-of-hooks": "error",
    "react/only-export-components": ["warn", { "allowConstantExport": true }]
  }
}
```

See the [Oxlint rules documentation](https://oxc.rs/docs/guide/usage/linter/rules) for the full list of rules and categories.
