# Métricas e indicadores

Definições únicas para todos os relatórios. A regra mais importante: **participantes distintos e presenças são números diferentes** e não se somam entre unidades.

| ID | Indicador | Fórmula / critério |
|---|---|---|
| M-01 | Participantes cadastrados | Matrículas com status `ativo` na abrangência |
| M-02 | Participantes distintos atendidos | `COUNT(DISTINCT participante_id)` nas presenças válidas do período e escopo |
| M-03 | Total de presenças | Presenças com status `valida` no período |
| M-04 | Média por sessão | M-03 ÷ M-05 |
| M-05 | Sessões realizadas | Sessões `encerrada` com `realizada = sim` no período |
| M-06 | Cobertura da escala | Sessões com equipe mínima confirmada ÷ sessões previstas (equipe mínima em [D-09](../06-arquitetura/decisoes/README.md)) |
| M-07 | Utilizações de atividade | Pares únicos (sessão, versão da atividade) confirmados |
| M-08 | Avaliação média | Soma de estrelas válidas ÷ quantidade de avaliações válidas |
| M-09 | Igrejas com EBI ativo | Igrejas com ao menos um EBI com status `ativo` |
| M-10 | Taxa de comparecimento | Só com denominador válido (por exemplo, participantes com igreja de referência na unidade). Fórmula a definir; não publicar antes. |

## Regras de apresentação
- Todo relatório declara período, fuso, abrangência, filtros, critérios de exclusão e data de atualização.
- M-02 de uma unidade superior é recalculado sobre as presenças, nunca somado a partir das unidades inferiores ([CA-14](../01-requisitos/criterios-de-aceite.md#ca-14)).
- Sessões são atribuídas à igreja, ao setor e à regional gravados na própria sessão ([RN-09](regras-de-negocio.md#rn-09)).
- **Supressão:** contagens identificáveis abaixo de um limiar são exibidas como "< N". Ponto de partida sugerido: N = 5 ([D-15](../06-arquitetura/decisoes/README.md)).

## Definições pendentes ([D-15](../06-arquitetura/decisoes/README.md))
- **Participante ativo:** proposta — com ao menos uma presença válida nos últimos 12 meses ou cadastrado há menos de 90 dias.
- **EBI ativo:** proposta — com ao menos uma sessão realizada nos últimos 90 dias.
- Denominador da taxa de comparecimento (M-10).
