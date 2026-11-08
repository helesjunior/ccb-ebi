# RF-11 — Reutilização de atividade

**Status:** proposto · **Épico:** [E09](../../07-produto/backlog-epicos.md) · **Fase:** 2

## Requisitos
1. Ao usar material em outra igreja, selecionar a **atividade e a versão** e vinculá-las à sessão realizada.
2. Gravar adaptação local, se houver. Igreja/EBI, data e equipe vêm da sessão.
3. **Visualização ou download não contam como utilização**; só o vínculo confirmado com sessão válida.
4. Sem contagem duplicada para o mesmo par versão/sessão; várias atividades por sessão são permitidas.
5. Exibir total de sessões que usaram a atividade e o histórico territorial conforme permissão.

## Relacionados
- Regras: [RN-06](../../02-regras-de-negocio/regras-de-negocio.md#rn-06), [RN-07](../../02-regras-de-negocio/regras-de-negocio.md#rn-07)
- Fluxos: [FL-03](../fluxos/FL-03-publicacao-e-reutilizacao.md)
- Critérios de aceite: [CA-09](../criterios-de-aceite.md#ca-09)
- Métricas: [M-07](../../02-regras-de-negocio/metricas-e-indicadores.md)
- Dados: `utilizacao_atividade`
- API: `POST /sessions/{id}/activity-uses`
