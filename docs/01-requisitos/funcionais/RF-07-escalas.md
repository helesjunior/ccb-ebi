# RF-07 — Escalas

**Status:** proposto · **Épico:** [E07](../../07-produto/backlog-epicos.md) · **Fase:** 2

## Requisitos
1. Criar escalas de coordenadoras e auxiliares por sessão, com função, horário e situação (prevista, confirmada, substituída, ausência).
2. Detectar conflitos de horário entre igrejas e alertar sobre lacunas de cobertura.
3. Toda sessão exige **equipe mínima**; a escala é **aprovada pela coordenadora chefe** do EBI, que também aprova substituições ([ADR-0010](../../06-arquitetura/decisoes/ADR-0010-equipe-minima-e-aprovacao-de-escala.md)).
4. Permitir confirmação e pedido de troca pela equipe.
5. Notificar a equipe sobre novas escalas, alterações e lembretes, conforme preferências.
6. Manter histórico de escala planejada versus cumprida.

## Relacionados
- Fluxos: [FL-04](../fluxos/FL-04-escala.md)
- Critérios de aceite: [CA-12](../criterios-de-aceite.md#ca-12), [CA-20](../criterios-de-aceite.md#ca-20)
- Métricas: [M-06](../../02-regras-de-negocio/metricas-e-indicadores.md)
- Dados: `escala`, `solicitacao_troca_escala`
- API: `GET /ebis/{id}/schedules`, `POST /sessions/{id}/schedules`, `/schedule-swaps`

## Pendências
- Tamanho da equipe mínima (parte restante de [D-09](../../06-arquitetura/decisoes/README.md)); até a definição, parâmetro configurável por EBI.
