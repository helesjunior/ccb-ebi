# React + TypeScript + Vite

## Fundação de UI (Tailwind CSS v4 + shadcn/ui)

- O frontend usa **Tailwind CSS v4** (plugin `@tailwindcss/vite`) e **shadcn/ui**.
- Adicionar um componente: `npx shadcn@latest add <nome>` — gera o código em `src/components/ui/`.
- Tema em `src/index.css` (variáveis do shadcn; `--primary` = roxo da marca `#aa3bff`). O tema claro/escuro tem **botão** (`TemaToggle`): alterna claro → escuro → automático (sistema), persiste em `localStorage['ebi.tema']` e é aplicado pela classe `.dark` no `<html>`. Um script inline no `index.html` aplica a preferência antes do primeiro paint (sem flash).
- Import por alias: `@/...` → `src/...` (ex.: `import { Button } from '@/components/ui/button'`).
- Build e lint: `npm run build` (tsc -b + vite) e `npm run lint` (oxlint).

## Casca de aplicação

- `src/components/layout/AppShell.tsx` é a casca das telas autenticadas: sidebar no desktop, gaveta (`Sheet`) no celular, cabeçalho com título, `TemaToggle`, seletor de contexto de atuação (`X-Acting-Scope`) e menu do usuário (nome, e-mail, "Editar perfil", "Sair").
- `src/components/layout/ListaNav.tsx` monta a navegação a partir dos papéis do usuário (`itensVisiveis`).
- Não há roteador: a tela ativa é um estado (`Tela`) em `src/App.tsx`. As demais telas herdam o layout sem alterar lógica, props nem `data-testid`.

## Tela de perfil

- `src/pages/MeuPerfil.tsx` reúne três cartões: **Dados** (`PATCH /me`, nome e e-mail), **Senha** (`POST /me/password`; mantém a sessão atual, revoga as demais) e **Segurança** (`/me/mfa/*`: habilitar ou renovar o TOTP e ver os códigos de recuperação).
- Renovar o MFA substitui o fator confirmado e invalida os códigos de recuperação antigos — a UI exige confirmação explícita (`Dialog`).

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
