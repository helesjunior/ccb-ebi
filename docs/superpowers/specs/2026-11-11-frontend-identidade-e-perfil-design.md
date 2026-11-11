# Design — Frontend: identidade visual, tema claro/escuro, casca de aplicação e perfil do usuário

**Data:** 2026-11-11 · **Status:** pronto para aprovação (revisão técnica concluída; dúvidas bloqueantes resolvidas) · **Entrega:** dívida de frontend herdada (ADR-0022 / CHANGELOG) + pedido novo

## 1. Intenção e por quê

O portal hoje é uma casca crua: `App.tsx` monta um `<nav>` com botões soltos, cada tela é um
`<main>` sem layout, a tipografia é um `<h1>` de 56px, e o "tema" é o roxo `#aa3bff` sobre fundo
escuro automático. Não dá para "ver o sistema ficando pronto" nem demonstrar a entrega com
dignidade.

O objetivo é dar ao portal **identidade visual própria**, **tema claro e escuro com botão**,
**responsividade** e **facilidade de uso**, e entregar uma **tela de perfil** onde o usuário logado
edita o que interessa: **nome, e-mail, senha** e **habilitar/renovar o MFA**.

Sucesso = abrir o portal em telas pequenas e grandes, alternar claro/escuro, navegar por todas as
telas sem perder o fio, e editar o perfil sem sair do fluxo.

## 2. Metas, não-metas e anti-metas

**Metas**
- Casca de aplicação (barra lateral + cabeçalho) reutilizada por todas as telas.
- Destaque do tema claro/escuro com botão, persistido, sem "flash" de tema errado no load.
- Layout responsivo (desktop → gaveta no celular) e acessível (WCAG 2.2 AA de base: contraste,
  foco visível, teclado, alvos de toque).
- Tela de perfil: dados (nome/e-mail), senha e segurança (MFA + códigos de recuperação).
- Migração das telas existentes para os componentes shadcn (paga dívida registrada).

**Não-metas**
- Não criar telas de negócio novas (atividades, presença, escalas) — entram nas etapas próprias.
- Não mexer em regra de autorização/território além do necessário para o perfil.
- Não adotar template de admin pronto (Opção C, descartada).

**Anti-metas** (falhou mesmo se "funciona")
- Continuar parecendo projeto em construção.
- Tema escuro que pisca no carregamento, ou toggle que não persiste.
- Perfil que parece bonito mas não salva de verdade / não dá feedback de erro.
- Quebrar o contrato OpenAPI↔portal (o cliente é gerado do spec).

## 3. Restrições

- **ADR-0021**: SPA React + TypeScript, build Vite, cliente gerado do OpenAPI. Não mudar.
- **RNF-05**: responsivo; presença registrada no celular — a casca precisa funcionar em celular.
- **Alvo WCAG 2.2 AA** (fundação, §5.6).
- Sem runner de teste de frontend (ADR-0022): validado por `lint` + `build` (+ verificação manual na
  interface).
- O portal é servido em `/spa/` pelo Laravel (base `/spa/`) e na raiz pelo nginx (base `/`).

## 4. Abordagens consideradas

1. **Refino do que existe (A)** — manter estrutura e só arrumar com shadcn. Rápido, mas não resolve
   "bonito com identidade". **Descartada.**
2. **Casca de aplicação + biblioteca shadcn (B)** — layout de aplicação de verdade, identidade
   própria, tema com toggle, perfil completo, migração das telas. **Escolhida pelo usuário.**
3. **Template de admin pronto (C)** — visual rico, mas bagagem e estilo de terceiros. **Descartada.**

## 5. Decisões (partes fixadas com o usuário)

- **Perfil** edita **apenas**: nome, e-mail, senha e MFA (habilitar/renovar). Sem foto/avatar/outros.
- **Backend novo é necessário** (ver §6): hoje não há endpoint para o usuário editar o próprio
  perfil, nem troca de senha autenticada.
- **Biblioteca de UI**: shadcn/ui (já configurado, estilo `new-york`, ícones lucide).
- **Estrutura de navegação**: barra lateral no desktop, gaveta no celular.
- **Estado do MFA no contrato**: `GET /me` passa a expor **`mfa_ativo`** (booleano), para a UI
  distinguir "nunca inscrevi" de "já tenho fator" e rotular o botão corretamente (§6.3).
- **Renovar MFA** (sobrescreve/revoga/invalida) — semântica fixada em §6.4, com confirmação na UI.
- **Troca de senha** mantém a **sessão atual viva** e derruba **apenas as outras** sessões (§6.2) —
  o usuário não é expulso no meio do SPA.

## 6. O que muda no backend (TDD, contrato primeiro)

Ordem obrigatória: **OpenAPI → catálogo → código** (o `check-endpoints.py` precisa seguir verde;
o catálogo é `docs/04-api/endpoints.md`).

1. `PATCH /api/v1/me` — atualiza **nome** e **e-mail** do próprio usuário. E-mail em uso por outro
   usuário → 422. Se o e-mail mudar, `email_verificado` volta a falso (reverificação, conforme o
   fluxo de registro existente).
2. `POST /api/v1/me/password` — troca de senha autenticada: exige a **senha atual** + nova
   (mesma regra de força do `reset`: mínimo 12). Senha atual errada → **422** com `detail`
   acionável (o portal exibe a mensagem). Sucesso **preserva a sessão atual** (o token Sanctum
   usado na requisição) e **revoga todas as demais** sessões do usuário. Resposta: 204.
3. `GET /me` (já existe) passa a alimentar o formulário e ganha o campo **`mfa_ativo`** (booleano):
   - `true` quando existe fator TOTP confirmado; `false` caso contrário.
   - Muda o contrato (`UsuarioResource` + OpenAPI + `schema.d.ts` regenerado).
   - MFA escreve com endpoints **já existentes** (`/me/mfa/totp`, `/confirm`, `/recovery-codes`).
4. **Semântica de "renovar MFA"** (sem endpoint novo — reusa `/me/mfa/totp`):
   - Iniciar a inscrição **sobrescreve** o segredo TOTP pendente/ativo do usuário;
   - Ao **confirmar** um novo TOTP, o **fator ativo anterior é revogado** (o app autenticador
     antigo para de funcionar) — o usuário só mantém o novo;
   - Os **códigos de recuperação anteriores são invalidados**; o usuário precisa gerar novos
     (`/me/mfa/recovery-codes`) depois de renovar.
   - A UI mostra **confirmação explícita** antes de renovar ("isso invalida seu app atual e os
     códigos de recuperação antigos").

Rotas sob `auth:sanctum` (sessão plena), sem Gate territorial — é o próprio usuário. Auditoria:
registrar a troca de dados pessoais, de senha e a renovação de MFA (aditivo aos eventos existentes).

## 7. O que muda no frontend

- **Tema**: `.dark` no `<html>` é a **única** fonte de verdade do tema (o `@custom-variant dark`
  atual passa a ser exercitado por classe, não só por `prefers-color-scheme`). Preferência em
  `localStorage` (`light`/`dark`/`system`), aplicada por **script inline no `index.html`** — sem
  depender de `sessao.ts` e **antes do render** (sem flash). Botão de alternância no cabeçalho.
- **Casca de aplicação**: componente de layout com barra lateral (desktop) / gaveta (celular),
  cabeçalho com título da página, seletor de contexto de atuação, alternador de tema e menu do
  usuário (com "Editar perfil" e "Sair").
- **Componentes shadcn** necessários (adicionados via CLI): card, input, label, dialog, dropdown-menu,
  sheet (gaveta), table, badge, alert, tabs, sonner (toasts), separator, tooltip, select, form.
- **Tela de perfil**: três blocos —
  - *Dados*: nome e e-mail (formulário com feedback por toast + erro acionável 422);
  - *Senha*: atual + nova + confirmar (mín. 12; senha atual errada = erro no campo);
  - *Segurança*: sem fator → botão **"Habilitar MFA"**; com fator (`mfa_ativo`) → **"Renovar MFA"**
    com diálogo de confirmação explícito, exibição do novo segredo/QR e dos códigos de recuperação.
- **Migração das telas** para a casca e componentes (mesma lógica/estado atuais; muda a apresentação).
- **Login** redesenhado (mesmo fluxo de dois passos, incluindo inscrição de TOTP quando pendente).

## 8. O que fica para o construtor decidir

- Paleta exata (matiz do roxo, neutros claro/escuro) dentro do shadcn — minha escolha: manter o
  `#aa3bff` como `--primary` da marca e derivar neutros coerentes.
- Estrutura de arquivos do layout (`components/layout/`) e se o menu do usuário é dropdown ou sheet.
- Uso de `react-router` vs. estado atual de `Tela` — **minha escolha**: manter o estado atual
  (sem adicionar roteador agora), para não inflar o escopo; a casca apenas recebe a tela como filho.
- Migração das demais telas: **minha escolha**: nesta leva, migrar **casca + login + perfil** e
  adaptar as demais telas ao layout novo (sem redesenhar cada formulário a fundo). O redesenho
  fino das 8 telas restantes fica como continuação.
- Contrato em ordem **OpenAPI → catálogo (`docs/04-api/endpoints.md`) → código**, com
  `scripts/check-endpoints.py` verde e `schema.d.ts` gerado.

### Minhas escolhas (o usuário pode corrigir)

1. **Paleta**: `#aa3bff` continua como `--primary`; neutros derivados coerentes em claro e escuro.
2. **Navegação**: mantém o estado `Tela` (sem `react-router` nesta leva).
3. **Escopo desta leva**: migrar **casca + login + perfil**; as outras 8–10 telas herdam o layout
   novo, com redesenho fino numa continuação.
4. **MFA**: expor `mfa_ativo` no `/me` (opção a); renovar sobrescreve/revoga/invalida códigos.
5. **Senha**: mantém a sessão atual e derruba as demais (opção a).

## 9. Riscos

- **Flash de tema**: mitigado pelo script inline no `index.html`.
- **Escopo inflando**: o redesenho fino das demais telas é a maior tentação — limitado por §8.
- **Acessibilidade não é "grátis"**: contraste e foco entram como critério de aceite, não como
  acabamento.
- **Sem teste de frontend**: mitigado por `lint`+`build` e verificação manual na interface.