# ADR-0020 — Supressão em relatórios e definição de "ativo"

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-15

## Decisão

| Definição | Regra |
|---|---|
| Limiar de supressão | Contagens de 1 a 4 aparecem como "< 5" em relatórios agregados e exportações acima do EBI. A equipe do próprio EBI vê os números reais. |
| Participante ativo | Pelo menos uma presença válida nos últimos 12 meses, ou cadastro feito nos últimos 90 dias. |
| EBI ativo | Pelo menos uma sessão realizada nos últimos 90 dias. |

## Consequências
- M-01 (participantes cadastrados) e M-09 (igrejas com EBI ativo) usam essas definições.
- Os três valores ficam como parâmetros configuráveis, para ajuste sem mudança de código.
- O denominador da taxa de comparecimento (M-10) continua a definir.
