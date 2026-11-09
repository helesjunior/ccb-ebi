# RF-14 — Indicadores e relatórios

**Status:** proposto · **Épico:** [E06](../../07-produto/backlog-epicos.md) · **Fase:** 1 (básicos), 2 (ampliados), 4 (nacionais)

## Requisitos
1. Indicadores definidos em [métricas e indicadores](../../02-regras-de-negocio/metricas-e-indicadores.md), por nível (nacional, regional, setor, igreja, EBI).
2. **Não somar contagens locais de participantes distintos para obter o total nacional**: um participante pode frequentar várias igrejas.
3. **Pendente:** a taxa de comparecimento ([M-10](../../02-regras-de-negocio/metricas-e-indicadores.md)) não tem fórmula aprovada e não é publicada no MVP.
4. Filtros por período, Regional, Setor, Igreja e EBI; exportação CSV/XLSX/PDF conforme autorização e minimização. Acima da igreja, só dados agregados; exportação com nomes desligada no MVP ([ADR-0029](../../06-arquitetura/decisoes/ADR-0029-dados-identificaveis-acima-da-igreja.md), proposto).
5. Aplicar a supressão de contagens pequenas definida em [métricas](../../02-regras-de-negocio/metricas-e-indicadores.md#supressão-e-definição-de-ativo).
6. Todo relatório declara período, fuso, abrangência, filtros, critérios de exclusão e data de atualização.

## Relacionados
- Critérios de aceite: [CA-14](../criterios-de-aceite.md#ca-14)
- API: `GET /reports/attendance`, `GET /reports/participants`

## Pendências
- [D-10](../../06-arquitetura/decisoes/README.md): a regra do [ADR-0029](../../06-arquitetura/decisoes/ADR-0029-dados-identificaveis-acima-da-igreja.md) aguarda validação da CCB e do DPO.
