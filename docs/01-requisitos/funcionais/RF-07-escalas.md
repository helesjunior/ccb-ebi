# RF-07 — Escalas

**Status:** implementado na Etapa 7 (E07), exceto a notificação (item 5) · **Épico:** [E07](../../07-produto/backlog-epicos.md) · **Fase:** 2

## Requisitos
1. Criar escalas de coordenadoras e auxiliares por sessão, com função, situação do membro (escalado, confirmado, troca solicitada, substituído) e a presença conferida ao fim da sessão. A situação da escala é `prevista` ou `aprovada`; alterar a equipe ou a estimativa depois de aprovada faz a escala voltar a `prevista` ([ADR-0033](../../06-arquitetura/decisoes/ADR-0033-coordenadora-chefe-e-regras-da-escala.md)).
2. Detectar conflitos de horário entre igrejas e alertar sobre lacunas de cobertura. O conflito é informativo e não bloqueia a escala.
3. Toda sessão exige a **equipe mínima** definida no [ADR-0010](../../06-arquitetura/decisoes/ADR-0010-equipe-minima-e-aprovacao-de-escala.md) (fórmula, estimativa digitada e aviso durante a sessão); a escala é **aprovada pela coordenadora chefe** do EBI — ou pelo `adm_igreja` quando não há chefe vigente —, que também decide as trocas ([ADR-0033](../../06-arquitetura/decisoes/ADR-0033-coordenadora-chefe-e-regras-da-escala.md)).
4. Permitir confirmação e pedido de troca pela equipe.
5. Notificar a equipe sobre novas escalas, alterações e lembretes, conforme preferências. *(pendente: não entra na E07)*
6. Manter histórico de escala planejada versus cumprida: o relatório compara a escala aprovada com a presença registrada e o M-06 mostra a cobertura.

## Relacionados
- Fluxos: [FL-04](../fluxos/FL-04-escala.md)
- Critérios de aceite: [CA-12](../criterios-de-aceite.md#ca-12), [CA-20](../criterios-de-aceite.md#ca-20)
- Métricas: [M-06](../../02-regras-de-negocio/metricas-e-indicadores.md)
- Dados: `escala`, `escala_membro`, `solicitacao_troca_escala`, `vinculo_acesso.chefe_de_ebi_id`
- API: `GET /ebis/{id}/schedules`, `GET /schedules/{id}`, `POST /sessions/{id}/schedules`, `POST /schedules/{id}/approve`, `/schedule-swaps`, `GET /me/schedules`
- Decisão: [ADR-0033](../../06-arquitetura/decisoes/ADR-0033-coordenadora-chefe-e-regras-da-escala.md)
