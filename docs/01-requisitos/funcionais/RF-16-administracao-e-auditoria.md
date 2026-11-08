# RF-16 — Administração, auditoria e suporte

**Status:** proposto · **Épico:** [E05](../../07-produto/backlog-epicos.md) · **Fase:** 1

## Requisitos
1. Gerir parâmetros, catálogos, perfis, convites, revogações e solicitações de suporte.
2. Registrar eventos: login, falha de autenticação, mudança de permissão, vínculo de responsável, consentimento, alteração cadastral, presença, feedback, publicação e reutilização.
3. Trilha de auditoria protegida contra adulteração, com ator, ação, recurso, data, origem e motivo quando aplicável.
4. Atender solicitações LGPD com protocolo, responsável e prazo, sem dar acesso desnecessário ao suporte.

## Relacionados
- Regras: [RN-10](../../02-regras-de-negocio/regras-de-negocio.md#rn-10), [RN-11](../../02-regras-de-negocio/regras-de-negocio.md#rn-11)
- Critérios de aceite: [CA-03](../criterios-de-aceite.md#ca-03), [CA-13](../criterios-de-aceite.md#ca-13)
- Dados: `evento_auditoria`, `solicitacao_titular`, `parametro`
- API: `/audit-events`, `POST /privacy/requests`
