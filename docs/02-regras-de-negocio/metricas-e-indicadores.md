# Métricas e indicadores

Definições únicas para todos os relatórios. A regra mais importante: **participantes distintos e presenças são números diferentes** e não se somam entre unidades.

| ID | Indicador | Fórmula / critério |
|---|---|---|
| M-01 | Participantes cadastrados | Matrículas com status `ativo` das igrejas da abrangência, atribuídas pela igreja de referência do participante. Quem não tem igreja de referência não entra em nenhuma unidade |
| M-02 | Participantes distintos atendidos | `COUNT(DISTINCT participante_id)` nas presenças válidas do período, somando as igrejas da unidade. Conta quem tem presença na unidade, mesmo que a igreja de referência do participante seja outra |
| M-03 | Total de presenças | Presenças com status `valida` no período |
| M-04 | Média por sessão | M-03 ÷ M-05 |
| M-05 | Sessões realizadas | Sessões `encerrada` com `realizada = sim` no período |
| M-06 | Cobertura da escala | Percentual de sessões cobertas. Sessão coberta = escala `aprovada` cuja equipe **já confirmada** (e com vínculo vigente na data da sessão) atinge o mínimo `1 coordenadora + ⌈estimativa ÷ divisor⌉ auxiliares`, com o divisor e a estimativa registrados **na própria escala** (mudar o divisor do EBI depois não muda a cobertura). Denominador: sessões previstas até a data de geração. Publicado como percentual com 1 decimal; `null` ("—") quando o denominador é zero. Não entra na supressão — é contagem de sessões, não de crianças ([ADR-0033](../06-arquitetura/decisoes/ADR-0033-coordenadora-chefe-e-regras-da-escala.md)) |
| M-07 | Utilizações de atividade | Pares únicos (sessão, versão da atividade) confirmados |
| M-08 | Avaliação média | Soma de estrelas válidas ÷ quantidade de avaliações válidas |
| M-09 | Igrejas com EBI ativo | Igrejas da abrangência com ao menos um EBI com sessão realizada nos últimos 90 dias ([ADR-0020](../06-arquitetura/decisoes/ADR-0020-supressao-e-definicao-de-ativo.md)) |
| M-10 | Taxa de comparecimento | Só com denominador válido (por exemplo, participantes com igreja de referência na unidade). Fórmula a definir; não publicar antes. |

## Regras de apresentação
- Todo relatório declara período, fuso, abrangência, filtros, critérios de exclusão e data de atualização.
- M-02 de uma unidade superior é recalculado sobre as presenças, nunca somado a partir das unidades inferiores ([CA-14](../01-requisitos/criterios-de-aceite.md#ca-14)).
- Sessões são atribuídas à igreja da sessão e ao setor e à regional vigentes dessa igreja ([RN-09](regras-de-negocio.md#rn-09)).
- **Data das sessões:** a sessão conta no período pela data no fuso da igreja. Cadastrados (M-01), participantes ativos e igrejas com EBI ativo (M-09) contam pela igreja de referência, e a janela deles tem limite superior na data de geração: sessão futura já encerrada não torna ninguém ativo. Esses três são da data de geração do relatório, não do período, e aparecem à parte na tela.
- **Nó do EBI:** o EBI mede as sessões dele (M-02, M-03, M-05, M-04 e M-06). M-01, participantes ativos e M-09 não existem nele: aparecem como `nao_se_aplica` ("—").
- **Status considerados:** presença `valida`, sessão `encerrada` com `realizada` e participante `ativo`. Sessão realizada sem nenhuma presença continua contando em M-05.
- **Supressão:** ver a seção abaixo.

## Supressão e definição de "ativo"

Três regras deixam os relatórios corretos e seguros ([ADR-0020](../06-arquitetura/decisoes/ADR-0020-supressao-e-definicao-de-ativo.md)).

| Definição | Para que serve | Valor | Exemplo |
|---|---|---|---|
| Limiar de supressão | Evitar que um número pequeno identifique um participante | Contagens de 1 a 4 aparecem como "< 5" acima da igreja; a igreja e tudo abaixo dela veem os números reais | Uma igreja com 2 participantes com exceção inclusiva mostraria "2"; quem atua acima da igreja vê "< 5" |
| Cascata da supressão | Impedir que o total do pai revele o filho escondido | Se qualquer unidade abaixo for suprimida, os indicadores de pessoas do pai aparecem como "Não divulgado", até a Central | Uma igreja com 3 participantes esconde os totais da regional, do setor e da Central também |
| Participante ativo | Separar quem frequenta de cadastros antigos | Pelo menos uma presença nos últimos 12 meses (em qualquer igreja, que qualifica sem mudar a igreja de referência), ou cadastro feito nos últimos 90 dias | Criança que veio em março e não voltou continua ativa até março do ano seguinte |
| EBI ativo | Saber quantos EBIs estão funcionando | Pelo menos uma sessão realizada nos últimos 90 dias | EBI parado desde julho fica inativo em outubro |

A fronteira da supressão é a igreja: **quem atua acima da igreja** (Central, regional, setor e os ministérios que atuam nesses níveis) recebe os valores suprimidos, e a supressão vale para relatórios agregados e exportações. A igreja e o EBI, para quem atua neles, veem os números reais. A isenção exige as duas coisas: **papel local** (coordenadora, administração da igreja ou ministério local) **e** escopo de igreja ou de EBI. Papel administrativo em escopo de igreja continua suprimido; papel local em escopo acima da igreja também. Zero aparece sempre: não é valor suprimível. Na lista de participantes, o **auxiliar** em escopo de igreja ou EBI também vê os números reais: tem `participante.ler` e atua na própria igreja.

**Fuso declarado:** o relatório mostra o fuso único quando todas as igrejas da unidade compartilham o mesmo; se houver mais de um, declara "fuso de cada igreja". O nó do EBI usa o fuso da igreja dele e mostra o nome do EBI.

**Indicadores nunca suprimidos:** a quantidade de sessões realizadas (M-05), a cobertura da escala (M-06, percentual de sessões) e as igrejas com EBI ativo (M-09) não identificam pessoa e aparecem em todos os níveis, sem entrar na cascata. Os indicadores que entram no limiar e na cascata são participantes distintos (M-02), presenças (M-03), média por sessão (M-04, que herda a supressão de M-03), participantes cadastrados (M-01) e participantes ativos.

**Busca de participantes acima da igreja:** um grupo de 1 a 4 candidatos por igreja de referência sai da resposta e a tela avisa que a lista não é divulgada, em vez de dizer "ninguém encontrado". O corte roda sobre os candidatos já autorizados pelo território do ator, para não sinalizar cadastros de fora do escopo.

O denominador da taxa de comparecimento (M-10) continua a definir.
