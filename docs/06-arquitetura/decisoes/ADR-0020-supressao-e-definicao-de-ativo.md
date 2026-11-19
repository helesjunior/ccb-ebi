# ADR-0020 — Supressão em relatórios e definição de "ativo"

**Status:** aceito · **Data:** 08/11/2026 · **Revisado:** 19/11/2026 (Etapa 7) · **Aprovado por:** Heles Júnior · **Resolve:** D-15

## Decisão

| Definição | Regra |
|---|---|
| Fronteira da supressão | **A igreja.** Quem atua acima dela (Central, regional, setor e os ministérios desses níveis) recebe os valores suprimidos; quem atua na igreja ou no EBI vê os números reais. |
| Limiar de supressão | Contagens de 1 a 4 de *pessoas* aparecem como "< 5" para quem atua acima da igreja. Zero aparece: não é suprimível. |
| Cascata | Se qualquer unidade abaixo for suprimida, os indicadores suprimíveis do pai aparecem como "Não divulgado" (`filho_suprimido`), até a Central. |
| Indicadores suprimíveis | M-02 (participantes distintos), M-03 (presenças), M-04 (média por sessão, herdando a supressão de M-03), M-01 (participantes cadastrados) e participantes ativos. |
| Indicadores nunca suprimidos | M-05 (sessões realizadas) e M-09 (igrejas com EBI ativo): não identificam pessoa e não entram no limiar nem na cascata, em nenhum nível. |
| M-01 × ativo | M-01 conta cadastros com status `ativo`; "participante ativo" é a definição temporal abaixo. São indicadores diferentes e aparecem separados. A atribuição é pela igreja de referência; quem não tem igreja de referência não entra em unidade nenhuma. |
| Participante ativo | Pelo menos uma presença válida nos últimos 12 meses, ou cadastro feito nos últimos 90 dias. |
| EBI ativo | Pelo menos uma sessão realizada nos últimos 90 dias. |

A mesma regra vale para os relatórios em tela, para a exportação CSV e para a busca de participantes acima da igreja (grupos de 1 a 4 ativos por igreja de referência saem da resposta).

## Consequências
- M-01 (participantes cadastrados) e M-09 (igrejas com EBI ativo) usam essas definições.
- Os três valores ficam como parâmetros configuráveis, para ajuste sem mudança de código.
- Períodos sobrepostos, ou comparações do cadastro antes e depois de um período, ainda permitem deduzir diferenças com o total suprimido. O risco é aceito e registrado para o DPO junto de D-10.
- O denominador da taxa de comparecimento (M-10) continua a definir.
