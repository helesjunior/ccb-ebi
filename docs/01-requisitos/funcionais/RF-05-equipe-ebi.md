# RF-05 — Equipe do EBI

**Status:** proposto · **Épico:** [E01](../../07-produto/backlog-epicos.md) · **Fase:** 1

## Requisitos
1. Cadastrar coordenadoras e auxiliares, vinculando usuários a uma ou várias igrejas/EBIs.
2. Registrar nome, e-mail, contato funcional (opcional), papel, situação e períodos de atuação.
3. Vínculo aprovado por autoridade administrativa competente, com histórico de nomeações, afastamentos e desligamentos.
4. Usuário desligado não registra sessões nem consulta dados após a revogação.

## Relacionados
- Regras: [RN-11](../../02-regras-de-negocio/regras-de-negocio.md#rn-11)
- Critérios de aceite: [CA-03](../criterios-de-aceite.md#ca-03)
- Dados: `usuario`, `papel`, `vinculo_acesso`, `convite`
- API: `/users`, `/invitations`, `/access-grants`
