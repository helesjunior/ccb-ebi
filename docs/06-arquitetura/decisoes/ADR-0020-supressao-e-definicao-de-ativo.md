# ADR-0020 — Supressão em relatórios e definição de "ativo"

**Status:** aceito · **Data:** 08/11/2026 · **Revisado:** 19/11/2026 (Etapa 7) · **Aprovado por:** Heles Júnior · **Resolve:** D-15

## Decisão

| Definição | Regra |
|---|---|
| Fronteira da supressão | **A igreja.** Quem atua acima dela (Central, regional, setor e os ministérios desses níveis) recebe os valores suprimidos; quem atua na igreja ou no EBI vê os números reais. |
| Isenção exige papel e escopo | Só vê os números reais quem tem **papel local** (coordenadora, administração da igreja, ministério local) **e** escopo de igreja ou de EBI. Papel administrativo em escopo de igreja continua suprimido, e papel local em escopo acima da igreja também: um dos dois sozinho não abre a fronteira. |
| Limiar de supressão | Contagens de 1 a 4 de *pessoas* aparecem como "< 5" para quem atua acima da igreja. Zero aparece: não é suprimível. |
| Cascata | Se qualquer unidade abaixo for suprimida, os indicadores suprimíveis do pai aparecem como "Não divulgado" (`filho_suprimido`), até a Central. |
| Indicadores suprimíveis | M-02 (participantes distintos), M-03 (presenças), M-04 (média por sessão, herdando a supressão de M-03), M-01 (participantes cadastrados) e participantes ativos. |
| Indicadores nunca suprimidos | M-05 (sessões realizadas) e M-09 (igrejas com EBI ativo): não identificam pessoa e não entram no limiar nem na cascata, em nenhum nível. |
| M-01 × ativo | M-01 conta cadastros com status `ativo`; "participante ativo" é a definição temporal abaixo. São indicadores diferentes e aparecem separados. |
| Atribuição | M-02 e M-03 contam pelas **presenças** nas igrejas da unidade, independentemente da igreja de referência do participante. M-01, participantes ativos e M-09 contam pela **igreja de referência**, e só nas igrejas da unidade: quem não tem igreja de referência não entra em unidade nenhuma. |
| Nó do EBI | O EBI se mede pelas próprias sessões (M-02, M-03, M-05, M-04). M-01, participantes ativos e M-09 não existem nele e aparecem como `nao_se_aplica`. |
| Participante ativo | Pelo menos uma presença válida nos últimos 12 meses, ou cadastro feito nos últimos 90 dias. A janela termina na data de geração: evento futuro não conta. |
| EBI ativo | Pelo menos uma sessão realizada nos últimos 90 dias, também com limite superior na data de geração. |
| Fuso | A data de cada sessão usa o fuso da igreja. O relatório declara o fuso quando ele é único na unidade e "fuso de cada igreja" quando há mais de um. |

A mesma regra vale para os relatórios em tela, para a exportação CSV e para a busca de participantes acima da igreja (grupos de 1 a 4 ativos por igreja de referência saem da resposta). Na busca, o corte roda sobre os candidatos já autorizados pelo território do ator e a tela avisa que a lista não é divulgada, em vez de dizer "ninguém encontrado".

## Consequências
- M-01 (participantes cadastrados) e M-09 (igrejas com EBI ativo) usam essas definições.
- Os três valores ficam como parâmetros configuráveis, para ajuste sem mudança de código.
- Períodos sobrepostos, ou comparações do cadastro antes e depois de um período, ainda permitem deduzir diferenças com o total suprimido. O risco é aceito e registrado para o DPO junto de D-10.
- O denominador da taxa de comparecimento (M-10) continua a definir.
