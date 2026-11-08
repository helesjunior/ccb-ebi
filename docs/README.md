# Documentação do EBI

Especificação da Plataforma de Gestão do Espaço Bíblico Infantil (EBI) da CCB, dividida por assunto para que cada área (produto, jurídico/DPO, segurança, desenvolvimento, institucional) revise a parte que lhe cabe.

**Versão:** 2.0 — proposta para validação · **Data:** 08/11/2026 · **Abrangência prevista:** nacional, com piloto na Regional de Brasília

> Documento de trabalho. Os requisitos dependem de aprovação institucional e não substituem validação jurídica, pastoral/institucional, de segurança ou de proteção de dados.

## Mapa

| Pasta | Conteúdo | Revisores principais |
|---|---|---|
| [00-visao](00-visao/) | [Visão geral](00-visao/visao-geral.md), [perfis e atores](00-visao/perfis-e-atores.md), [glossário](00-visao/glossario.md) | Produto, institucional |
| [01-requisitos](01-requisitos/) | [Funcionais (RF)](01-requisitos/funcionais/README.md), [não funcionais (RNF)](01-requisitos/nao-funcionais.md), [fluxos (FL)](01-requisitos/fluxos/README.md), [critérios de aceite (CA)](01-requisitos/criterios-de-aceite.md), [rastreabilidade](01-requisitos/rastreabilidade.md) | Produto, desenvolvimento, QA |
| [02-regras-de-negocio](02-regras-de-negocio/) | [Regras (RN)](02-regras-de-negocio/regras-de-negocio.md), [elegibilidade etária](02-regras-de-negocio/elegibilidade-etaria.md), [métricas](02-regras-de-negocio/metricas-e-indicadores.md) | Produto, institucional |
| [03-dados](03-dados/) | [Modelo conceitual](03-dados/modelo-conceitual.md), [dicionário de dados](03-dados/dicionario-de-dados.md), [estados](03-dados/estados.md) | Desenvolvimento, DPO |
| [04-api](04-api/) | [Convenções](04-api/convencoes.md), [endpoints](04-api/endpoints.md), [OpenAPI](04-api/openapi.yaml) | Desenvolvimento |
| [05-seguranca-e-privacidade](05-seguranca-e-privacidade/) | [Autenticação](05-seguranca-e-privacidade/autenticacao.md), [matriz de autorização](05-seguranca-e-privacidade/matriz-de-autorizacao.md), [LGPD](05-seguranca-e-privacidade/lgpd.md), [modelo de ameaças](05-seguranca-e-privacidade/modelo-de-ameacas.md) | Segurança, jurídico/DPO |
| [06-arquitetura](06-arquitetura/) | [Arquitetura de referência](06-arquitetura/arquitetura-de-referencia.md), [decisões e ADRs](06-arquitetura/decisoes/README.md) | Desenvolvimento, TI da CCB |
| [07-produto](07-produto/) | [Fases](07-produto/fases-e-roadmap.md), [backlog](07-produto/backlog-epicos.md), [riscos](07-produto/riscos.md), [entregáveis](07-produto/entregaveis.md) | Produto, gestão |
| [arquivo](arquivo/) | [Especificação v2 original](arquivo/especificacao-v2-original.md), mantida só para consulta | — |

## Convenções

### Identificadores

Cada item tem um ID estável. **Um ID nunca é reaproveitado nem renumerado.** Item retirado fica marcado como `descontinuado`, com o motivo.

| Prefixo | Significado | Onde mora |
|---|---|---|
| `RF-nn` | Requisito funcional | `01-requisitos/funcionais/` |
| `RNF-nn` | Requisito não funcional | `01-requisitos/nao-funcionais.md` |
| `RN-nn` | Regra de negócio | `02-regras-de-negocio/` |
| `FL-nn` | Fluxo de usuário | `01-requisitos/fluxos/` |
| `CA-nn` | Critério de aceite | `01-requisitos/criterios-de-aceite.md` |
| `M-nn` | Métrica/indicador | `02-regras-de-negocio/metricas-e-indicadores.md` |
| `D-nn` | Decisão em aberto, pendente de aprovação | `06-arquitetura/decisoes/README.md` |
| `ADR-nnnn` | Registro de decisão de arquitetura | `06-arquitetura/decisoes/` |
| `E-nn` | Épico do backlog | `07-produto/backlog-epicos.md` |
| `R-nn` | Risco | `07-produto/riscos.md` |

### Status

Todo requisito, regra e decisão tem um status:

| Status | Significado |
|---|---|
| `proposto` | Redigido, ainda não revisado |
| `em validação` | Em revisão pela CCB, jurídico ou segurança |
| `aprovado` | Aprovado; a origem da aprovação fica registrada |
| `descontinuado` | Retirado; o ID não é reutilizado |

Nesta versão, todos os itens estão `proposto`, salvo indicação.

### Uma regra, um lugar

Uma regra é escrita em um único arquivo. Os demais documentos apenas apontam para ela. Exemplo: a regra de idade vive em [elegibilidade-etaria.md](02-regras-de-negocio/elegibilidade-etaria.md); RF-02, RF-08 e os critérios de aceite só fazem referência.

### Terminologia

- **Participante** é a pessoa atendida pelo EBI, de qualquer idade. É o termo padrão em telas, dados e API.
- **Criança** é usado só quando a regra depende de idade (participação regular de 3 a 11 anos).
- **Responsável** é o adulto que acompanha o participante. Para participante adulto, ver [D-13](06-arquitetura/decisoes/README.md).
- Domínio e documentação em português; rotas da API em inglês. Ver [ADR-0003](06-arquitetura/decisoes/ADR-0003-entidade-participante.md) e [convenções da API](04-api/convencoes.md).

### Como contribuir

1. Abra um branch e altere o arquivo do assunto.
2. Ao criar um item, use o próximo ID livre do prefixo.
3. Atualize a [rastreabilidade](01-requisitos/rastreabilidade.md) se o item se liga a RF, RN, FL ou CA.
4. Registre a mudança no [CHANGELOG](CHANGELOG.md).
5. Decisão nova ou alterada vira ADR.

Diagramas em [Mermaid](https://mermaid.js.org/), que o GitHub renderiza.
