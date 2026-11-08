# Métricas e indicadores

Definições únicas para todos os relatórios. A regra mais importante: **participantes distintos e presenças são números diferentes** e não se somam entre unidades.

| ID | Indicador | Fórmula / critério |
|---|---|---|
| M-01 | Participantes cadastrados | Matrículas com status `ativo` na abrangência |
| M-02 | Participantes distintos atendidos | `COUNT(DISTINCT participante_id)` nas presenças válidas do período e escopo |
| M-03 | Total de presenças | Presenças com status `valida` no período |
| M-04 | Média por sessão | M-03 ÷ M-05 |
| M-05 | Sessões realizadas | Sessões `encerrada` com `realizada = sim` no período |
| M-06 | Cobertura da escala | Sessões com equipe mínima confirmada ÷ sessões previstas. Equipe mínima conforme a fórmula e o parâmetro do [ADR-0010](../06-arquitetura/decisoes/ADR-0010-equipe-minima-e-aprovacao-de-escala.md), com o valor do parâmetro vigente na data da sessão |
| M-07 | Utilizações de atividade | Pares únicos (sessão, versão da atividade) confirmados |
| M-08 | Avaliação média | Soma de estrelas válidas ÷ quantidade de avaliações válidas |
| M-09 | Igrejas com EBI ativo | Igrejas com ao menos um EBI com status `ativo` |
| M-10 | Taxa de comparecimento | Só com denominador válido (por exemplo, participantes com igreja de referência na unidade). Fórmula a definir; não publicar antes. |

## Regras de apresentação
- Todo relatório declara período, fuso, abrangência, filtros, critérios de exclusão e data de atualização.
- M-02 de uma unidade superior é recalculado sobre as presenças, nunca somado a partir das unidades inferiores ([CA-14](../01-requisitos/criterios-de-aceite.md#ca-14)).
- Sessões são atribuídas à igreja, ao setor e à regional gravados na própria sessão ([RN-09](regras-de-negocio.md#rn-09)).
- **Supressão:** ver a seção abaixo.

## Supressão e definição de "ativo"

Três regras deixam os relatórios corretos e seguros ([ADR-0020](../06-arquitetura/decisoes/ADR-0020-supressao-e-definicao-de-ativo.md)).

| Definição | Para que serve | Valor | Exemplo |
|---|---|---|---|
| Limiar de supressão | Evitar que um número pequeno identifique um participante | Contagens de 1 a 4 aparecem como "< 5" em relatórios de quem não é da equipe do EBI | Uma igreja com 2 participantes com exceção inclusiva mostraria "2"; quem conhece a igreja saberia quem são. O relatório mostra "< 5" |
| Participante ativo | Separar quem frequenta de cadastros antigos | Pelo menos uma presença nos últimos 12 meses, ou cadastro feito nos últimos 90 dias | Criança que veio em março e não voltou continua ativa até março do ano seguinte |
| EBI ativo | Saber quantos EBIs estão funcionando | Pelo menos uma sessão realizada nos últimos 90 dias | EBI parado desde julho fica inativo em outubro |

A equipe do próprio EBI vê os números reais; a supressão vale para relatórios agregados e exportações em níveis acima.

O denominador da taxa de comparecimento (M-10) continua a definir.
