# RF-07 — Escalas

**Status:** proposto · **Épico:** [E07](../../07-produto/backlog-epicos.md) · **Fase:** 2

## Requisitos
1. Criar escalas de coordenadoras e auxiliares por sessão, com função, horário e situação (prevista, confirmada, substituída, ausência).
2. Detectar conflitos de horário entre igrejas e alertar sobre lacunas de cobertura.
3. Permitir confirmação, pedido de troca e aprovação de substituição, respeitando a hierarquia.
4. Notificar a equipe sobre novas escalas, alterações e lembretes, conforme preferências.
5. Manter histórico de escala planejada versus cumprida.

## Relacionados
- Fluxos: [FL-04](../fluxos/FL-04-escala.md)
- Critérios de aceite: [CA-12](../criterios-de-aceite.md#ca-12)
- Métricas: [M-06](../../02-regras-de-negocio/metricas-e-indicadores.md)
- Dados: `escala`, `solicitacao_troca_escala`
- API: `GET /ebis/{id}/schedules`, `POST /sessions/{id}/schedules`, `/schedule-swaps`

## Pendências
- [D-09](../../06-arquitetura/decisoes/README.md): equipe mínima e aprovação de escalas.
