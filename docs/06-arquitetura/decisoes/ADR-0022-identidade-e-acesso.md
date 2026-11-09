# ADR-0022 — Identidade e acesso (Etapa 3): escopo e dívidas

**Status:** aceito · **Data:** 09/11/2026 · **Aprovado por:** Heles Júnior · **Relaciona:** ADR-0002, ADR-0017

## Contexto
A Etapa 3 (E01) substituiu o andaime de identidade (tabela `atores`, autenticador fictício e o gate único `gerir-organizacao`) por identidade e autorização reais: `usuarios` com ULID, RBAC+ABAC conforme a [matriz de autorização](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md), MFA TOTP obrigatório, sessões por dispositivo, convites e concessões de acesso. Para manter a etapa entregável e revisável, parte do desenho ficou **explicitamente fora** desta etapa.

## Decisão
Aplicar a identidade e a autorização da Etapa 3 com as seguintes exclusões, registradas como dívidas:

1. **Finalidade registrada (`*` da matriz).** Operações marcadas `*` não capturem nem exigem finalidade neste passo. A captura de finalidade é de **E05** (privacidade) / decisão **D-10**. Até lá, as operações `*` se comportam como as homônimas sem `*`.
2. **Passkey/WebAuthn.** Apenas TOTP + códigos de recuperação. Autenticação sem senha fica para depois.
3. **Federação de identidade real.** O campo `identidade_federada` existe no modelo, mas não há integração com provedor externo.
4. **WhatsApp.** Canal de notificação não implementado; convites e recuperação usam e-mail.
5. **Trilha de auditoria consultável e retenção.** A Etapa 3 grava eventos encadeados (RN-10) para concessões de acesso e mudança de status de usuário, mas a **consulta** e a **retenção** da trilha são de **E05**.
6. **Regras de Participantes e Presença.** Não fazem parte de E01 (E03/E04).
7. **Testes de frontend.** O portal não tem runner de testes (Vitest/Testing Library); os testes de fluxo previstos no plano do portal ficam como dívida, validando o portal por `lint` + `build` por ora.
8. **Filtragem por subárvore nas listagens de Organização.** As listagens de organização são apenas por papel; a filtragem por subárvore está feita só em `/users`. Dívida registrada.
9. **`ator_id` de auditoria** ainda é `null` nos serviços de gestão de usuário/vínculo (não há fiação do usuário atuante nesses serviços). Dívida registrada.
10. **Expiração de token vs. limpeza de sessões.** O token Sanctum de sessão recebe `expires_at` conforme o perfil (12 h responsável; 8 h demais) e a revogação de dispositivo **deleta** o token. A remoção periódica de tokens expirados de `personal_access_tokens` e a marcação de `sessao_login` expiradas ficam para operação (agendamento/cron), não fazem parte desta etapa.

## Consequências
- A matriz de autorização passa a ser a **fonte da verdade** papel × operação; cada linha vira teste pode/não-pode. Quando o plano e a matriz divergirem, a matriz vence.
- `super_admin` ignora o escopo territorial (bypass explícito), o que mantém a etiqueta de identidade operável desde o primeiro dia. O mesmo bypass vale para a concessão/revogação de vínculo e a gestão de usuário (núcleo `AutoridadeDePapel`).
- As dívidas acima precisam de dono na fase em que cada uma é devida (E05 para privacidade/auditoria; follow-up para frontend e listagens).

## Correções pós-revisão fresca
Uma revisão fresca da etapa (commits `8620652..32b50ee`) corrigiu, com testes de regressão: o fluxo de MFA ponta a ponta (alias `abilities` ausente e `ResolveActingScope` bloqueando o token de desafio; `Autenticador` invertia a regra do segundo fator); a escalada vertical em `/users` e `/access-grants` (núcleo `AutoridadeDePapel`); a ligação das sessões por dispositivo ao token Sanctum; a validação da assinatura de verificação de e-mail; e o alinhamento de `X-Acting-Scope` e do campo `uri` do TOTP com o contrato OpenAPI.

Gotcha de teste documentado: o guard Sanctum **cacheia** o último token/usuário entre requisições **no mesmo teste**; asserts que trocam de token exigem `$this->app['auth']->forgetGuards()` antes.