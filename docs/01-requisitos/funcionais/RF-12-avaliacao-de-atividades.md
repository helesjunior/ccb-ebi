# RF-12 — Avaliação de atividades

**Status:** proposto · **Épico:** [E09](../../07-produto/backlog-epicos.md) · **Fase:** 2

## Requisitos
1. Avaliação de **1 a 5 estrelas** por usuário habilitado, após utilização confirmada em sessão.
2. Uma avaliação ativa **por usuário e utilização**; nova nota substitui a anterior, com auditoria ([ADR-0014](../../06-arquitetura/decisoes/ADR-0014-avaliacao-por-utilizacao.md)).
3. Exibir média aritmética e quantidade de avaliações, separadas do número de utilizações. Atividade com **menos de 5 avaliações** mostra "poucas avaliações" no lugar da média e não entra em ordenação por avaliação (proposto; parâmetro configurável).
4. Denúncia e moderação de conteúdo inadequado. Não existem avaliações de crianças ou pessoas.

## Relacionados
- Regras: [RN-08](../../02-regras-de-negocio/regras-de-negocio.md#rn-08)
- Critérios de aceite: [CA-10](../criterios-de-aceite.md#ca-10)
- Métricas: [M-08](../../02-regras-de-negocio/metricas-e-indicadores.md)
- Dados: `avaliacao_atividade`
- API: `POST /activity-uses/{id}/ratings`

