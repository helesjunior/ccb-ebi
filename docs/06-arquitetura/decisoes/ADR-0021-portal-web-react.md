# ADR-0021 — Portal Web em React com TypeScript

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Relaciona:** ADR-0004, ADR-0009

## Contexto
A arquitetura deixava o framework do portal Web "a definir", e ele é necessário para as telas de todos os épicos do MVP. No MVP o registro de presença é feito pelo portal Web responsivo no celular; o app único (ADR-0009) chega na fase 3 e vai consumir a mesma API.

## Opções consideradas
1. **React + TypeScript (SPA).** Consome a API `/api/v1`, mantém a imagem `web` separada do ADR-0004 e combina com React Native, se ele for escolhido para o app.
2. **Vue 3 + TypeScript (SPA).** Também consome a API; muito usado com Laravel e com curva de aprendizado menor.
3. **Inertia dentro do Laravel.** Menos peças no início, mas o portal não usaria a API pública, que teria de ser feita à parte para o app.

## Decisão
**Opção 1.** O portal administrativo e o portal dos responsáveis são uma SPA em React com TypeScript, build com Vite, servida como arquivos estáticos pelo nginx na imagem `web`.

## Consequências
- Portal e app usam o mesmo contrato da API; todo endpoint do portal passa pelo OpenAPI.
- O cliente da API pode ser gerado a partir do OpenAPI, o que mantém o contrato verificado no build.
- A escolha do app (Flutter ou React Native) continua aberta, mas React Native passa a ter a vantagem de compartilhar tipos e parte da lógica com o portal.
