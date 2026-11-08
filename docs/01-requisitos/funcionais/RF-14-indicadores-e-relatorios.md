# RF-14 — Indicadores e relatórios

**Status:** proposto · **Épico:** [E06](../../07-produto/backlog-epicos.md) · **Fase:** 1 (básicos), 2 (ampliados), 4 (nacionais)

## Requisitos
1. Indicadores definidos em [métricas e indicadores](../../02-regras-de-negocio/metricas-e-indicadores.md), por nível (nacional, regional, setor, igreja, EBI).
2. **Não somar contagens locais de participantes distintos para obter o total nacional**: um participante pode frequentar várias igrejas.
3. Taxa de comparecimento só com denominador válido e definição documentada ([M-10](../../02-regras-de-negocio/metricas-e-indicadores.md)).
4. Filtros por período, Regional, Setor, Igreja e EBI; exportação CSV/XLSX/PDF conforme autorização e minimização.
5. Contagens de 1 a 4 aparecem como "< 5" em relatórios acima do EBI, para evitar reidentificação ([ADR-0020](../../06-arquitetura/decisoes/ADR-0020-supressao-e-definicao-de-ativo.md)).
6. Todo relatório declara período, fuso, abrangência, filtros, critérios de exclusão e data de atualização.

## Relacionados
- Critérios de aceite: [CA-14](../criterios-de-aceite.md#ca-14)
- API: `GET /reports/attendance`, `GET /reports/participants`

## Pendências
- [D-10](../../06-arquitetura/decisoes/README.md): exportação e acesso a dados identificáveis em níveis superiores.
