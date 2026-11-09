# Fases e roadmap

Cinco fases, cada uma com critério de passagem. As fases são recomendação preliminar, não cronograma aprovado.

```mermaid
flowchart LR
  F0[Fase 0<br/>Descoberta e governança] -->|aprovação institucional,<br/>jurídica e de segurança| F1[Fase 1<br/>MVP Brasília]
  F1 -->|piloto com controles<br/>de acesso aprovados| F2[Fase 2<br/>Operação EBI]
  F2 -->|aceitação e<br/>moderação concluídas| F3[Fase 3<br/>Aplicativos e escala]
  F3 -->|publicação nas lojas<br/>e suporte preparado| F4[Fase 4<br/>Expansão nacional]
```

| Fase | Entregáveis | Épicos | Critério de passagem |
|---|---|---|---|
| 0 — Descoberta e governança | Validação da hierarquia, política de dados, fluxos de legitimidade, perfis, arquitetura, protótipos; autorização da CCB, itens jurídicos do ADR-0019 (D-04), infraestrutura (D-11) e validação das propostas de D-06 e D-10 | — | Aprovação institucional, jurídica e de segurança |
| 1 — MVP Brasília | Estrutura, login/MFA, participantes/responsáveis, consentimento, elegibilidade etária, agenda, sessões, presença (Web responsivo), dashboards básicos | E01–E06 | Piloto com igrejas selecionadas e controles de acesso aprovados |
| 2 — Operação EBI | Escalas, feedback, biblioteca PDF, reutilização, estrelas, relatórios ampliados | E07–E10 | Testes de aceitação e moderação concluídos |
| 3 — Aplicativos e escala | App Android/iOS único, push, sugestão por GPS, presença offline, desempenho, implantação regional | E11–E13 | Publicação nas contas oficiais e suporte preparado |
| 4 — Expansão nacional | Importação de unidades, operação multirregional, relatórios nacionais, continuidade aprimorada | E02, E06, E13 (evoluções) | Governança e capacidade nacional demonstradas |

Na fase 1, o registro de presença é feito pelo portal Web responsivo no celular; os apps nativos chegam na fase 3.

## Dependências críticas
- Autorização formal da CCB.
- Hierarquia definida ([ADR-0017](../06-arquitetura/decisoes/ADR-0017-hierarquia-e-ministerios.md)); falta a validação institucional.
- Nomeação de responsável pelo produto e de encarregado de dados.
- Comprovação de responsáveis definida ([ADR-0018](../06-arquitetura/decisoes/ADR-0018-comprovacao-de-responsavel.md)).
- Política de proteção infantil.
- Titularidade das contas das lojas (D-07).
- Dimensionamento de infraestrutura e orçamento (D-11, volumetria em [RNF-04](../01-requisitos/nao-funcionais.md)).
